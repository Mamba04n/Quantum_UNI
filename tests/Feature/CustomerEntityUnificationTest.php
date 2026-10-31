<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Entity;
use App\Models\Permission;
use App\Models\User;
use App\Services\CustomerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerEntityUnificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_canonical_api_creates_an_entity_without_creating_a_portal_user(): void
    {
        $user = $this->crmManager();
        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/v1/erp/clients', [
            'name' => 'Cliente API',
            'ruc' => '00102030405060',
            'phone' => '+505 2222 2222',
            'email' => 'cliente.api@example.test',
            'address' => 'Managua',
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Cliente API')
            ->assertJsonPath('data.ruc', '00102030405060')
            ->assertHeaderMissing('Deprecation');

        $this->assertDatabaseHas('entities', [
            'name' => 'Cliente API',
            'type' => 'cliente',
            'rfc_ruc' => '00102030405060',
        ]);
        $this->assertDatabaseMissing('users', ['email' => 'cliente.api@example.test']);
    }

    public function test_legacy_api_is_a_deprecated_alias_of_the_canonical_endpoint(): void
    {
        Sanctum::actingAs($this->crmManager(), ['*']);

        $this->postJson('/api/clients', [
            'name' => 'Cliente legado',
            'ruc' => '00102030405061',
        ])->assertCreated()
            ->assertHeader('Deprecation', 'true')
            ->assertHeader('Sunset', 'Sun, 31 Jan 2027 23:59:59 GMT')
            ->assertHeader('Link', '<'.route('api.v1.erp.clients.store').'>; rel="successor-version"');

        $this->assertSame(1, Entity::where('type', 'cliente')->count());
    }

    public function test_customer_token_cannot_create_directory_clients(): void
    {
        $customer = User::factory()->create();
        $permission = Permission::where('slug', 'customer_request.create')->firstOrFail();
        $customer->permissions()->sync([$permission->id]);
        Sanctum::actingAs($customer, ['*']);

        $this->postJson('/api/v1/erp/clients', [
            'name' => 'Cliente no autorizado',
            'ruc' => '00102030405062',
        ])->assertForbidden();
    }

    public function test_duplicate_tax_identity_is_rejected_and_keeps_one_entity(): void
    {
        app(CustomerService::class)->createFromAdmin([
            'name' => 'Cliente original',
            'rfc_ruc' => '001-020304-0506A',
        ]);

        $this->expectException(ValidationException::class);

        try {
            app(CustomerService::class)->createFromAdmin([
                'name' => 'Cliente duplicado',
                'rfc_ruc' => '0010203040506A',
            ]);
        } finally {
            $this->assertSame(1, Entity::where('type', 'cliente')->count());
        }
    }

    public function test_legacy_client_model_reads_and_writes_the_entities_table_only(): void
    {
        $client = Client::create([
            'name' => 'Compatibilidad temporal',
            'ruc' => '00102030405063',
            'phone' => '+505 7777 7777',
            'status' => 'active',
        ]);
        Entity::create(['name' => 'Proveedor', 'type' => 'proveedor']);

        $this->assertSame('cliente', $client->type);
        $this->assertSame('00102030405063', $client->fresh()->ruc);
        $this->assertSame(1, Client::count());
        $this->assertFalse(Schema::hasTable('clients'));
    }

    private function crmManager(): User
    {
        $user = User::factory()->create();
        $permission = Permission::where('slug', 'crm.clients.manage')->firstOrFail();
        $user->permissions()->sync([$permission->id]);

        return $user;
    }
}
