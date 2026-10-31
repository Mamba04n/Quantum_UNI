<?php

namespace Tests\Feature;

use App\Livewire\Administracion\Usuarios\GestorUsuarios;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_and_search_internal_users(): void
    {
        $admin = $this->userWithPermissions(['admin.users.manage']);
        $internalUser = User::factory()->create([
            'name' => 'Empleado Interno Test',
            'email' => 'empleado.interno.test@example.com',
            'role' => User::ROLE_VENTAS,
        ]);

        $storeCustomer = User::factory()->create([
            'name' => 'Cliente Tienda Test',
            'email' => 'cliente.tienda.test@example.com',
            'role' => User::ROLE_CLIENTE_TIENDA,
        ]);

        $customerRole = Role::firstOrCreate(
            ['slug' => User::ROLE_CLIENTE_TIENDA],
            ['name' => 'Cliente tienda']
        );
        $storeCustomer->roles()->sync([$customerRole->id]);

        Livewire::actingAs($admin)
            ->test(GestorUsuarios::class)
            ->assertSee('Empleado Interno Test')
            ->assertDontSee('Cliente Tienda Test')
            ->set('search', 'empleado.interno.test@example.com')
            ->assertSee('Empleado Interno Test')
            ->assertDontSee('Cliente Tienda Test');
    }

    public function test_admin_can_open_create_user_modal(): void
    {
        $admin = $this->userWithPermissions(['admin.users.manage']);

        Livewire::actingAs($admin)
            ->test(GestorUsuarios::class)
            ->call('openCreate')
            ->assertSet('showModal', true)
            ->assertSet('role', User::ROLE_VENTAS)
            ->assertSet('selectedRoles', [User::ROLE_VENTAS]);
    }

    private function userWithPermissions(array $permissions): User
    {
        $role = Role::firstOrCreate(
            ['slug' => 'user-manager-test'],
            ['name' => 'User manager test']
        );

        $permissionIds = collect($permissions)->map(function (string $permission) {
            return Permission::firstOrCreate(
                ['slug' => $permission],
                ['name' => $permission, 'module' => str($permission)->before('.')->toString()]
            )->id;
        });

        $role->permissions()->sync($permissionIds);

        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $user->roles()->sync([$role->id]);

        return $user;
    }
}
