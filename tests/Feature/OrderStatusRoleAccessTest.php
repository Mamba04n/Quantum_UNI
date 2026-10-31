<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStatusRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $internalRoles = [
        'admin',
        'agente_finanzas',
        'operaciones',
        'ventas',
        'bodega',
        'soporte',
        'compras',
    ];

    public function test_all_internal_roles_receive_order_status_read_access(): void
    {
        foreach ($this->internalRoles as $roleSlug) {
            $role = Role::query()->where('slug', $roleSlug)->firstOrFail();

            $this->assertTrue(
                $role->permissions()->where('slug', 'order_status.view')->exists(),
                "El rol {$roleSlug} no tiene acceso de lectura a Order Status.",
            );
        }

        $customerRole = Role::query()->where('slug', User::ROLE_CLIENTE_TIENDA)->firstOrFail();

        $this->assertFalse($customerRole->permissions()->where('slug', 'order_status.view')->exists());
    }

    public function test_read_access_does_not_grant_order_status_management(): void
    {
        foreach (['agente_finanzas', 'operaciones', 'ventas', 'bodega', 'soporte'] as $roleSlug) {
            $role = Role::query()->where('slug', $roleSlug)->firstOrFail();

            $this->assertFalse(
                $role->permissions()->where('slug', 'order_status.manage')->exists(),
                "El rol {$roleSlug} recibio gestion operativa por error.",
            );
        }
    }

    public function test_warehouse_user_can_open_order_status_and_see_its_menu_entry(): void
    {
        $warehouseRole = Role::query()->where('slug', User::ROLE_BODEGA)->firstOrFail();
        $user = User::factory()->create();
        $user->roles()->sync([$warehouseRole->id]);

        $this->actingAs($user)
            ->get(route('orders.status.index'))
            ->assertOk()
            ->assertSee('Estado del pedido')
            ->assertSee('Order Status');
    }
}
