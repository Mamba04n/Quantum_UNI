<?php

namespace Tests\Feature;

use App\Livewire\Administracion\Auditoria\MonitorActividad;
use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ActivityMonitorTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_search_activity_logs(): void
    {
        $admin = $this->userWithPermissions(['admin.activity.view']);
        $worker = User::factory()->create(['name' => 'Operador Auditoria', 'role' => User::ROLE_BODEGA]);

        ActivityLog::create([
            'user_id' => $worker->id,
            'module' => 'bodega',
            'action' => 'stock.adjusted',
            'description' => 'Ajuste inventario bomba hidraulica',
            'metadata' => ['sku' => 'HYD-500'],
            'ip_address' => '127.0.0.1',
        ]);

        ActivityLog::create([
            'user_id' => $worker->id,
            'module' => 'ventas',
            'action' => 'quotation.created',
            'description' => 'Cotizacion comercial generica',
            'metadata' => null,
            'ip_address' => '127.0.0.1',
        ]);

        Livewire::actingAs($admin)
            ->test(MonitorActividad::class)
            ->assertSee('Ajuste inventario bomba hidraulica')
            ->set('search', 'bomba hidraulica')
            ->assertSee('Ajuste inventario bomba hidraulica')
            ->assertDontSee('Cotizacion comercial generica');
    }

    public function test_admin_can_filter_activity_by_module(): void
    {
        $admin = $this->userWithPermissions(['admin.activity.view']);
        $worker = User::factory()->create(['role' => User::ROLE_VENTAS]);

        ActivityLog::create([
            'user_id' => $worker->id,
            'module' => 'ventas',
            'action' => 'client_offer.sent',
            'description' => 'Oferta cliente enviada',
        ]);

        ActivityLog::create([
            'user_id' => $worker->id,
            'module' => 'finanzas',
            'action' => 'payment.registered',
            'description' => 'Pago registrado',
        ]);

        Livewire::actingAs($admin)
            ->test(MonitorActividad::class)
            ->set('filterModule', 'ventas')
            ->assertSee('Oferta cliente enviada')
            ->assertDontSee('Pago registrado');
    }

    private function userWithPermissions(array $permissions): User
    {
        $role = Role::firstOrCreate(
            ['slug' => 'activity-monitor-test'],
            ['name' => 'Activity monitor test']
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
