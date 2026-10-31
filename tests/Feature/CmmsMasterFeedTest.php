<?php

namespace Tests\Feature;

use App\Models\IntegrationClient;
use App\Models\User;
use App\Services\Integration\MasterFeed;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CmmsMasterFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_unexpected_errors_do_not_expose_internal_details(): void
    {
        $this->mock(MasterFeed::class)->shouldReceive('snapshot')->once()->andThrow(new \RuntimeException('PRIVATE SQL DETAIL'));
        $response = $this->withToken($this->token())->getJson('/api/v1/integration/customers')->assertStatus(503)
            ->assertJsonPath('error.code', 'integration.unavailable')->assertHeader('X-Request-ID');
        $this->assertStringNotContainsString('PRIVATE SQL DETAIL', $response->getContent());
    }

    private function token(array $abilities = ['customers:read', 'assets:read', 'employees:read']): string
    {
        return IntegrationClient::create(['name' => (string) Str::uuid()])->createToken('masters', $abilities, now()->addHour())->plainTextToken;
    }

    private function customer(string $name = 'Cliente', string $type = 'cliente'): int
    {
        return DB::table('entities')->insertGetId(['name' => $name, 'type' => $type, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function asset(int $customer): int
    {
        return DB::table('client_machines')->insertGetId(['entity_id' => $customer, 'name' => 'Equipo', 'status' => 'activa', 'internal_code' => 'A-1', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_scopes_whitelist_and_unavailable_locations(): void
    {
        $id = $this->customer();
        $supplier = $this->customer('Proveedor privado', 'proveedor');
        $this->withToken($this->token(['users:read']))->getJson('/api/v1/integration/customers')->assertForbidden();
        $this->withToken($this->token())->getJson('/api/v1/integration/customers')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $id)->assertJsonPath('data.0.active', true);
        $r = $this->getJson('/api/v1/integration/customers/'.$id)->assertOk();
        $this->assertEqualsCanonicalizing(['id', 'name', 'active', 'deleted', 'version', 'updated_at'], array_keys($r->json('data')));
        $this->getJson('/api/v1/integration/customers/'.$supplier)->assertNotFound();
        $this->getJson('/api/v1/integration/locations')->assertNotFound();
    }

    public function test_snapshot_survives_changes_deletion_and_new_rows_between_pages(): void
    {
        $first = $this->customer('Primero');
        $second = $this->customer('Segundo');
        $third = $this->customer('Tercero');
        $token = $this->token();
        $page = $this->withToken($token)->getJson('/api/v1/integration/customers?limit=1')->assertOk()->json();
        DB::table('entities')->where('id', $second)->update(['name' => 'Modificado']);
        DB::table('entities')->where('id', $third)->delete();
        $fourth = $this->customer('Nuevo');
        $ids = [$page['data'][0]['id']];
        $cursor = $page['meta']['next_cursor'];
        while ($cursor) {
            $next = $this->getJson('/api/v1/integration/customers?'.http_build_query(['cursor' => $cursor]))->assertOk()->json();
            $this->assertSame($page['meta']['snapshot_version'], $next['meta']['snapshot_version']);
            $this->assertSame(3, $next['meta']['total']);
            $this->assertFalse($next['data'][0]['deleted']);
            $ids[] = $next['data'][0]['id'];
            $cursor = $next['meta']['next_cursor'];
        }
        $this->assertSame(array_map('strval', [$first, $second, $third]), $ids);
        $current = $this->getJson('/api/v1/integration/customers')->assertOk()->json('data');
        $this->assertCount(4, $current);
        $deleted = collect($current)->firstWhere('id', (string) $third);
        $this->assertTrue($deleted['deleted']);
        $this->assertArrayNotHasKey('name', $deleted);
        $this->getJson('/api/v1/integration/customers/'.$third)->assertNotFound();
        $this->getJson('/api/v1/integration/customers/'.$third.'?snapshot_version='.$page['meta']['snapshot_version'])->assertOk();
        $this->assertContains((string) $fourth, array_column($current, 'id'));
    }

    public function test_cursor_is_bound_to_resource_consumer_and_filters(): void
    {
        $this->customer();
        $this->customer();
        $page = $this->withToken($this->token())->getJson('/api/v1/integration/customers?limit=1')->assertOk()->json();
        $cursor = http_build_query(['cursor' => $page['meta']['next_cursor']]);
        $this->getJson('/api/v1/integration/assets?'.$cursor)->assertUnprocessable();
        $this->getJson('/api/v1/integration/customers?'.$cursor.'&limit=2')->assertUnprocessable();
        $this->withToken($this->token())->getJson('/api/v1/integration/customers?'.$cursor)->assertUnprocessable();
        $this->getJson('/api/v1/integration/customers?cursor=tampered')->assertUnprocessable();
        $this->getJson('/api/v1/integration/customers?limit=201')->assertUnprocessable();
    }

    public function test_transfer_delete_and_customer_scope_change(): void
    {
        $a = $this->customer('A');
        $b = $this->customer('B');
        $asset = $this->asset($a);
        $this->withToken($this->token())->getJson('/api/v1/integration/customers/'.$a.'/assets')->assertOk()->assertJsonCount(1, 'data');
        DB::table('client_machines')->where('id', $asset)->update(['entity_id' => $b]);
        $oldOwner = $this->getJson('/api/v1/integration/customers/'.$a.'/assets')->assertOk()->assertJsonPath('data.0.deleted', true);
        $this->assertArrayNotHasKey('customer_id', $oldOwner->json('data.0'));
        $this->getJson('/api/v1/integration/assets/'.$asset)->assertOk()->assertJsonPath('data.customer_id', (string) $b)->assertJsonPath('data.location_id', null);
        DB::table('entities')->where('id', $b)->update(['type' => 'proveedor']);
        $this->getJson('/api/v1/integration/assets/'.$asset)->assertNotFound();
        DB::table('entities')->where('id', $b)->update(['type' => 'cliente']);
        $this->getJson('/api/v1/integration/assets/'.$asset)->assertOk();
        // The current ERP FK restricts deleting an owner with machinery.
        DB::table('client_machines')->where('id', $asset)->delete();
        DB::table('entities')->where('id', $b)->delete();
        $this->getJson('/api/v1/integration/assets')->assertOk()->assertJsonPath('data.0.deleted', true);
        $this->assertDatabaseMissing('client_machines', ['id' => $asset]);
    }

    public function test_employee_aggregate_tracks_user_changes_and_cascade_without_sensitive_fields(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $id = DB::table('employee_profiles')->insertGetId(['user_id' => $user->id, 'job_title' => 'Técnico', 'department' => 'Taller', 'identity_document' => 'SECRET', 'emergency_contact' => 'SECRET', 'status' => 'active']);
        $r = $this->withToken($this->token())->getJson('/api/v1/integration/employees')->assertOk()->assertJsonPath('data.0.id', (string) $id)->assertJsonPath('data.0.user_id', (string) $user->id);
        $version = $r->json('data.0.version');
        $this->assertStringNotContainsString('SECRET', $r->getContent());
        DB::table('users')->where('id', $user->id)->update(['is_active' => false, 'name' => 'Otro nombre']);
        $r = $this->getJson('/api/v1/integration/employees')->assertOk()->assertJsonPath('data.0.active', false)->assertJsonPath('data.0.name', 'Otro nombre');
        $this->assertGreaterThan($version, $r->json('data.0.version'));
        DB::table('users')->where('id', $user->id)->delete();
        $this->getJson('/api/v1/integration/employees')->assertOk()->assertJsonPath('data.0.deleted', true);
    }

    public function test_versions_and_tombstones_rollback_with_business_transaction(): void
    {
        $id = $this->customer();
        $clock = DB::table('integration_master_clock')->value('version');
        DB::beginTransaction();
        DB::table('entities')->where('id', $id)->delete();
        DB::rollBack();
        $this->assertSame($clock, DB::table('integration_master_clock')->value('version'));
        $this->withToken($this->token())->getJson('/api/v1/integration/customers/'.$id)->assertOk();
    }

    public function test_uncommitted_writer_serializes_other_writers_and_is_invisible_to_readers(): void
    {
        $this->customer('Uncommitted');
        $default = config('database.default');
        config(['database.connections.cmms_concurrency_test' => config('database.connections.'.$default)]);
        $other = DB::connection('cmms_concurrency_test');
        try {
            $this->assertLessThan(DB::table('integration_master_clock')->value('version'), $other->table('integration_master_clock')->value('version'));
            $other->statement('SET SESSION innodb_lock_wait_timeout = 1');
            try {
                $other->table('entities')->insert(['name' => 'Blocked writer', 'type' => 'cliente', 'status' => 'active']);
                $this->fail('The committed version sequence must serialize writers.');
            } catch (QueryException $e) {
                $this->assertSame(1205, $e->errorInfo[1]);
            }
            $this->assertSame(0, $other->table('entities')->where('name', 'Blocked writer')->count());
        } finally {
            DB::purge('cmms_concurrency_test');
        }
    }
}
