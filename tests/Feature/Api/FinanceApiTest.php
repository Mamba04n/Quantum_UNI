<?php

namespace Tests\Feature\Api;

use App\Domain\Finanzas\ExchangeRates\Enums\ExchangeRateSourceType;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Entity;
use App\Models\ExchangeRate;
use App\Models\FinanceDeviceEnrollment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinanceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_device_can_issue_and_replace_a_least_privilege_token(): void
    {
        $user = $this->financeUser(['finance.documents.view_all']);
        $deviceId = (string) Str::uuid();
        $payload = $this->tokenPayload($user, $deviceId, [
            'finance:payables:read',
            'finance:documents:read',
        ]);

        $first = $this->postJson(route('api.v1.finance.tokens.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.device_id', $deviceId)
            ->assertJsonPath('data.device_name', 'Contabilidad principal')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.abilities', fn (array $abilities) => collect($abilities)->sort()->values()->all() === [
                'finance:documents:read',
                'finance:payables:read',
                'finance:tokens',
            ]);

        $firstToken = $first->json('data.access_token');
        $this->assertIsString($firstToken);

        $secondToken = $this->postJson(
            route('api.v1.finance.tokens.store'),
            $this->tokenPayload($user, $deviceId, [
                'finance:payables:read',
                'finance:documents:read',
            ])
        )
            ->assertCreated()
            ->json('data.access_token');

        $this->assertNotSame($firstToken, $secondToken);
        $this->assertSame(1, PersonalAccessToken::query()->where('device_id', $deviceId)->count());
    }

    public function test_assigned_scope_does_not_leak_another_users_payable_and_money_is_a_string(): void
    {
        $owner = $this->financeUser(['finance.documents.view_assigned']);
        $other = User::factory()->create();
        $owned = $this->payable($owner, '125.50', '25.25');
        $foreign = $this->payable($other, '900.00', '0.00');
        $token = $this->issueToken($owner, ['finance:payables:read']);

        $response = $this->withToken($token)->getJson(route('api.v1.finance.payables.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $owned->id)
            ->assertJsonPath('data.0.amounts.total', '125.50')
            ->assertJsonPath('data.0.amounts.paid', '25.25')
            ->assertJsonPath('data.0.amounts.balance', '100.25');

        $this->assertFalse(collect($response->json('data'))->pluck('id')->contains($foreign->id));

        $this->withToken($token)
            ->getJson(route('api.v1.finance.payables.show', $foreign))
            ->assertForbidden();
    }

    public function test_token_ability_is_required_in_addition_to_user_permissions(): void
    {
        $user = $this->financeUser(['finance.documents.view_all']);
        $token = $this->issueToken($user, ['finance:rates:read']);

        $this->withToken($token)
            ->getJson(route('api.v1.finance.payables.index'))
            ->assertForbidden();
    }

    public function test_all_read_contracts_are_versioned_and_return_decimal_strings(): void
    {
        $user = $this->financeUser(['finance.documents.view_all']);
        $entity = Entity::query()->create(['name' => 'Entidad API', 'type' => 'cliente']);
        $receivable = AccountReceivable::query()->create([
            'entity_id' => $entity->id,
            'concept' => 'CXC API',
            'total_amount' => '80.00',
            'paid_amount' => '10.00',
            'currency' => 'NIO',
            'due_date' => today()->addMonth(),
            'status' => 'parcial',
            'created_by' => $user->id,
        ]);
        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-API-1',
            'entity_id' => $entity->id,
            'date' => today(),
            'description' => 'Factura API',
            'amount_nio' => '75.00',
            'total' => '75.00',
            'currency' => 'NIO',
            'status' => 'Pendiente',
            'generated_by' => $user->id,
        ]);
        Payment::query()->create([
            'payment_code' => 'PAY-API-1',
            'payable_type' => AccountReceivable::class,
            'payable_id' => $receivable->id,
            'accounts_receivable_id' => $receivable->id,
            'invoice_id' => $invoice->id,
            'amount' => '10.00',
            'currency' => 'NIO',
            'payment_date' => today(),
            'registered_by' => $user->id,
        ]);
        ExchangeRate::query()->create([
            'base_currency' => 'USD',
            'quote_currency' => 'NIO',
            'rate' => '36.500000',
            'rate_date' => today(),
            'source' => 'Banco Central',
            'source_type' => ExchangeRateSourceType::Api,
        ]);
        $token = $this->issueToken($user, [
            'finance:receivables:read',
            'finance:invoices:read',
            'finance:payments:read',
            'finance:rates:read',
        ]);

        $this->withToken($token)->getJson(route('api.v1.finance.receivables.index'))
            ->assertOk()->assertJsonPath('data.0.amounts.balance', '70.00');
        $this->withToken($token)->getJson(route('api.v1.finance.invoices.index'))
            ->assertOk()->assertJsonPath('data.0.amounts.total', '75.00');
        $this->withToken($token)->getJson(route('api.v1.finance.payments.index'))
            ->assertOk()->assertJsonPath('data.0.amounts.amount', '10.00');
        $this->withToken($token)->getJson(route('api.v1.finance.exchange-rates.index'))
            ->assertOk()->assertJsonPath('data.0.rate', '36.500000');
    }

    public function test_private_document_requires_token_ability_and_record_policy(): void
    {
        Storage::fake('private');
        $owner = $this->financeUser(['finance.documents.view_assigned']);
        $payable = $this->payable($owner);
        $path = 'finance/cxp/api-proof.pdf';
        Storage::disk('private')->put($path, 'api-private-proof');
        $payable->forceFill(['support_document_path' => $path])->save();
        $token = $this->issueToken($owner, ['finance:documents:read']);

        $response = $this->withToken($token)->get(route('api.v1.finance.documents.show', [
            'type' => 'cxp',
            'id' => $payable->id,
        ]));

        $response->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame('api-private-proof', $response->streamedContent());
    }

    public function test_device_revocation_invalidates_its_bearer_token(): void
    {
        $user = $this->financeUser(['finance.documents.view_all']);
        $deviceId = (string) Str::uuid();
        $token = $this->issueToken($user, ['finance:payables:read'], $deviceId);

        $this->withToken($token)
            ->deleteJson(route('api.v1.finance.tokens.destroy', $deviceId))
            ->assertNoContent();

        $this->assertNull(PersonalAccessToken::findToken($token));
        Auth::forgetGuards();

        $this->withToken($token)
            ->getJson(route('api.v1.finance.payables.index'))
            ->assertUnauthorized();
    }

    public function test_credentials_and_finance_permission_are_both_required_to_issue_tokens(): void
    {
        $user = User::factory()->create(['password' => Hash::make('DesktopPassword123!')]);

        $this->postJson(route('api.v1.finance.tokens.store'), $this->tokenPayload(
            $user,
            (string) Str::uuid(),
            ['finance:payables:read']
        ))->assertForbidden();

        $payload = $this->tokenPayload($user, (string) Str::uuid(), ['finance:payables:read']);
        $payload['password'] = 'IncorrectPassword123!';
        $this->postJson(route('api.v1.finance.tokens.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_token_abilities_are_rejected_without_the_equivalent_record_permission(): void
    {
        $user = $this->financeUser();

        $this->postJson(route('api.v1.finance.tokens.store'), $this->tokenPayload(
            $user,
            (string) Str::uuid(),
            ['finance:payables:read']
        ))->assertForbidden();
    }

    public function test_enrollment_code_is_single_use_and_bound_to_the_device(): void
    {
        $user = $this->financeUser(['finance.documents.view_all']);
        $deviceId = (string) Str::uuid();
        $payload = $this->tokenPayload($user, $deviceId, ['finance:payables:read']);

        $this->postJson(route('api.v1.finance.tokens.store'), $payload)->assertCreated();
        $this->postJson(route('api.v1.finance.tokens.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('enrollment_code');
    }

    public function test_admin_command_creates_a_short_lived_device_approval(): void
    {
        $user = $this->financeUser();
        $deviceId = (string) Str::uuid();

        $this->artisan('finance:approve-device', [
            'email' => $user->email,
            'device_id' => $deviceId,
            'device_name' => 'Laptop aprobada',
            '--minutes' => 10,
        ])->assertSuccessful()
            ->expectsOutputToContain('El código se muestra una sola vez');

        $enrollment = FinanceDeviceEnrollment::query()
            ->where('user_id', $user->id)
            ->where('device_id', $deviceId)
            ->firstOrFail();

        $this->assertSame('Laptop aprobada', $enrollment->device_name);
        $this->assertNull($enrollment->consumed_at);
        $this->assertTrue($enrollment->expires_at->isFuture());
    }

    public function test_finance_reads_are_audited_with_a_correlation_id_and_all_devices_can_be_revoked(): void
    {
        $user = $this->financeUser(['finance.documents.view_all']);
        $first = $this->issueToken($user, ['finance:payables:read']);
        $second = $this->issueToken($user, ['finance:payables:read']);
        $requestId = (string) Str::uuid();

        $this->withToken($first)
            ->withHeader('X-Request-ID', $requestId)
            ->getJson(route('api.v1.finance.payables.index'))
            ->assertOk()
            ->assertHeader('X-Request-ID', $requestId);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'finance.api.read',
        ]);

        $this->withToken($first)
            ->deleteJson(route('api.v1.finance.tokens.destroy-all'))
            ->assertNoContent();

        $this->assertNull(PersonalAccessToken::findToken($first));
        $this->assertNull(PersonalAccessToken::findToken($second));
    }

    public function test_disabling_user_immediately_blocks_an_existing_device_token(): void
    {
        $user = $this->financeUser(['finance.documents.view_all']);
        $token = $this->issueToken($user, ['finance:payables:read']);
        $user->forceFill(['is_active' => false])->save();
        Auth::forgetGuards();

        $this->withToken($token)
            ->getJson(route('api.v1.finance.payables.index'))
            ->assertForbidden();
    }

    public function test_openapi_document_covers_every_public_finance_path(): void
    {
        $contract = file_get_contents(base_path('tests/Fixtures/contracts/openapi-finance-v1.yaml'));
        $this->assertIsString($contract);

        foreach ([
            '/tokens:',
            '/tokens/{deviceId}:',
            '/receivables:',
            '/receivables/{id}:',
            '/payables:',
            '/payables/{id}:',
            '/invoices:',
            '/invoices/{id}:',
            '/payments:',
            '/payments/{id}:',
            '/exchange-rates:',
            '/documents/{type}/{id}:',
        ] as $path) {
            $this->assertStringContainsString("  {$path}", $contract);
        }
    }

    /** @param list<string> $extraPermissions */
    private function financeUser(array $extraPermissions = []): User
    {
        $user = User::factory()->create(['password' => Hash::make('DesktopPassword123!')]);
        $permissionIds = collect(['finance.view', ...$extraPermissions])->map(function (string $slug) {
            return Permission::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => str($slug)->headline()->toString(), 'module' => 'finance']
            )->id;
        })->all();
        $user->syncPermissions($permissionIds);

        return $user;
    }

    private function payable(User $owner, string $total = '100.00', string $paid = '0.00'): AccountPayable
    {
        $entity = Entity::query()->create(['name' => 'Proveedor '.Str::random(6), 'type' => 'proveedor']);

        return AccountPayable::query()->create([
            'entity_id' => $entity->id,
            'concept' => 'Cuenta API',
            'total_amount' => $total,
            'paid_amount' => $paid,
            'currency' => 'NIO',
            'due_date' => today()->addMonth(),
            'status' => $paid === '0.00' ? 'pendiente' : 'parcial',
            'created_by' => $owner->id,
        ]);
    }

    /** @param list<string> $abilities */
    private function issueToken(User $user, array $abilities, ?string $deviceId = null): string
    {
        return $this->postJson(route('api.v1.finance.tokens.store'), $this->tokenPayload(
            $user,
            $deviceId ?? (string) Str::uuid(),
            $abilities
        ))->assertCreated()->json('data.access_token');
    }

    /**
     * @param  list<string>  $abilities
     * @return array<string, mixed>
     */
    private function tokenPayload(User $user, string $deviceId, array $abilities): array
    {
        $enrollmentCode = Str::random(48);
        FinanceDeviceEnrollment::query()->updateOrCreate(
            ['user_id' => $user->id, 'device_id' => $deviceId],
            [
                'device_name' => 'Contabilidad principal',
                'code_hash' => hash('sha256', $enrollmentCode),
                'expires_at' => now()->addMinutes(15),
                'consumed_at' => null,
            ]
        );

        return [
            'email' => $user->email,
            'password' => 'DesktopPassword123!',
            'enrollment_code' => $enrollmentCode,
            'device_id' => $deviceId,
            'device_name' => 'Contabilidad principal',
            'abilities' => $abilities,
        ];
    }
}
