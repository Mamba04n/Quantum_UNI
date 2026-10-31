<?php

namespace Tests\Feature;

use App\Domain\Administracion\Auditoria\Queries\UnifiedActivityQuery;
use App\Http\Middleware\AuditBusinessRequest;
use App\Infrastructure\Audit\AuditContext;
use App\Models\ActivityLog;
use App\Models\AuditLog;
use App\Models\Entity;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class UnifiedActivityAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_feed_unifies_sources_and_filters_dates_user_and_module(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $payment = $this->payment();
        $audit = AuditLog::where('entity_type', Payment::class)->where('entity_id', $payment->id)->sole();
        $this->assertSame($user->id, $audit->user_id);
        $this->assertSame('finanzas', $audit->metadata['module']);
        $this->assertSame('25.50', $audit->metadata['changes']['amount']);
        ActivityLog::forceCreate(['user_id' => $user->id, 'module' => 'finanzas', 'action' => 'test.payment', 'description' => 'Prueba de conciliación', 'created_at' => now()->subDays(2)]);
        $feed = app(UnifiedActivityQuery::class);
        $page = $feed->paginate((string) $user->id, 'finanzas', null, null, 50);
        $this->assertSame(2, $page->total());
        $this->assertEqualsCanonicalizing(['actividad', 'auditoria'], $page->pluck('source')->all());
        $this->assertSame(1, $feed->paginate((string) $user->id, 'finanzas', null, null, 50, today()->toDateString())->total());
        $this->assertSame(1, $feed->paginate(null, null, null, 'conciliación', 50)->total());
    }

    public function test_update_records_before_and_after_and_rollback_leaves_no_audit(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $payment = $this->payment();
        $payment->update(['amount' => '30.00']);
        $event = AuditLog::where('entity_type', Payment::class)->where('action', 'updated')->sole();
        $this->assertSame('25.50', $event->metadata['original']['amount']);
        $this->assertSame('30.00', $event->metadata['changes']['amount']);
        $count = AuditLog::count();
        DB::beginTransaction();
        $payment->update(['amount' => '45.00']);
        DB::rollBack();
        $this->assertSame($count, AuditLog::count());
        $this->assertSame('30.00', $payment->fresh()->amount);
    }

    public function test_trait_is_not_duplicated_and_unauthenticated_background_changes_are_visible(): void
    {
        $entity = Entity::create(['name' => 'Auditoría de sistema', 'type' => 'cliente']);
        $audit = AuditLog::where('entity_type', Entity::class)->where('entity_id', $entity->id)->sole();
        $this->assertNull($audit->user_id);
        $this->assertSame('Auditoría de sistema', $audit->metadata['changes']['name']);
    }

    public function test_credentials_are_redacted_on_capture_and_on_historical_display(): void
    {
        $user = User::factory()->create(['password' => 'NeverShowThis123!']);
        $audit = AuditLog::where('entity_type', User::class)->where('entity_id', $user->id)->sole();
        $this->assertSame('[Protegido]', $audit->metadata['changes']['password']);
        AuditLog::create(['action' => 'legacy', 'metadata' => ['module' => 'security', 'nested' => ['token' => 'secret-value']], 'created_at' => now()]);
        $event = app(UnifiedActivityQuery::class)->paginate(null, 'security', null, 'legacy', 50)->sole();
        $this->assertSame('[Protegido]', $event->metadata['nested']['token']);
        $this->assertSame('reset-password/[Protegido]', AuditContext::path('reset-password/secret-value?token=abc'));
    }

    public function test_http_405_is_recorded_without_parameters(): void
    {
        $this->get('/livewire/update?token=private-value')->assertStatus(405);
        $audit = AuditLog::where('action', 'http.error')->sole();
        $this->assertSame(405, $audit->metadata['status']);
        $this->assertSame('GET', $audit->metadata['method']);
        $this->assertSame('livewire/update', $audit->metadata['path']);
        $this->assertStringNotContainsString('private-value', json_encode($audit->metadata));
    }

    public function test_authentication_events_do_not_capture_credentials(): void
    {
        $user = User::factory()->create();
        Event::dispatch(new Login('web', $user, false));
        Event::dispatch(new Failed('web', $user, ['password' => 'hidden-value']));
        $events = AuditLog::whereIn('action', ['auth.login', 'auth.failed'])->get();
        $this->assertCount(2, $events);
        $this->assertStringNotContainsString('hidden-value', $events->toJson());
    }

    public function test_finance_baseline_is_explicit_and_idempotent(): void
    {
        $payment = Payment::withoutEvents(fn () => $this->payment());
        $this->artisan('audit:finance-baseline')->expectsOutput('Registros sin auditoría: 1')->assertSuccessful();
        $this->assertFalse(AuditLog::where('entity_type', Payment::class)->exists());
        $this->artisan('audit:finance-baseline --commit')->expectsOutput('Instantáneas registradas: 1')->assertSuccessful();
        $this->artisan('audit:finance-baseline --commit')->expectsOutput('Instantáneas registradas: 0')->assertSuccessful();
        $this->assertSame('25.50', $payment->fresh()->amount);
    }

    public function test_livewire_actions_are_logged_but_polling_and_arguments_are_not(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $request = Request::create('/livewire/update', 'POST', ['components' => [['calls' => [['method' => 'saveDirectExpense', 'params' => ['private-detail']]]]]]);
        $request->headers->set('X-Livewire', 'true');
        $request->setUserResolver(fn () => $user);
        $middleware = app(AuditBusinessRequest::class);
        $middleware->handle($request, fn () => response()->json(['ok' => true]));
        $event = AuditLog::where('action', 'http.operation')->sole();
        $this->assertSame(['saveDirectExpense'], $event->metadata['calls']);
        $this->assertStringNotContainsString('private-detail', $event->toJson());
        $request->merge(['components' => [['calls' => [['method' => '$refresh', 'params' => []]]]]]);
        $middleware->handle($request, fn () => response()->json(['ok' => true]));
        $this->assertSame(1, AuditLog::where('action', 'http.operation')->count());
    }

    public function test_activity_monitor_remains_protected(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_VENTAS]))->get(route('admin.activity'))->assertForbidden();
    }

    public function test_caught_exceptions_are_recorded_without_sql_or_credentials(): void
    {
        report(new \RuntimeException('private SQL and credentials'));
        $event = AuditLog::where('action', 'application.exception')->sole();
        $this->assertSame(\RuntimeException::class, $event->metadata['exception']);
        $this->assertStringNotContainsString('private SQL', $event->toJson());
    }

    private function payment(): Payment
    {
        return Payment::create(['payment_code' => 'AUD-EGR-1', 'payment_type' => 'supplier_payment', 'payable_type' => Entity::class, 'payable_id' => 0, 'amount' => '25.50', 'currency' => 'NIO', 'payment_date' => today(), 'registered_by' => auth()->id(), 'notes' => 'Gasto operativo de prueba']);
    }
}
