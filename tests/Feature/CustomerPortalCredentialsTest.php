<?php

namespace Tests\Feature;

use App\Domain\Clientes\Services\CustomerService;
use App\Models\Entity;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerPortalCredentialsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_update_customer_portal_credentials(): void
    {
        Role::query()->firstOrCreate(
            ['slug' => User::ROLE_CLIENTE_TIENDA],
            ['name' => 'Cliente tienda']
        );
        $customer = Entity::query()->create(['name' => 'Cliente Portal', 'type' => 'cliente', 'email' => 'cliente@example.com']);
        $service = app(CustomerService::class);

        $customer = $service->syncPortalAccess($customer, 'cliente@example.com', 'ClaveInicial2026!', true);

        $this->assertNotNull($customer->user_id);
        $this->assertTrue($customer->user->is_active);
        $this->assertNotNull($customer->user->email_verified_at);
        $this->assertTrue(Hash::check('ClaveInicial2026!', $customer->user->password));
        $this->assertSame([User::ROLE_CLIENTE_TIENDA], $customer->user->roleSlugs());

        $customer = $service->syncPortalAccess($customer, 'nuevo@example.com', 'ClaveCambiada2026!', true);
        $this->assertSame('nuevo@example.com', $customer->user->email);
        $this->assertTrue(Hash::check('ClaveCambiada2026!', $customer->user->password));

        $customer = $service->disablePortalAccess($customer);
        $this->assertFalse($customer->user->is_active);
    }
}
