<?php

namespace Tests\Feature;

use App\Domain\Pedidos\OrderStatus\Actions\RegisterDelivery;
use App\Livewire\Administracion\DispatchAuthorizationManager;
use App\Models\OrderStatus;
use App\Models\OrderStatusItem;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SaleOrder;
use App\Models\User;
use App\Services\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class DispatchAuthorizationManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_sees_and_approves_pending_dispatch_request(): void
    {
        $user = $this->userWithPermissions([
            'dispatch_authorization.view',
            'dispatch_authorization.approve',
        ]);
        $item = $this->orderStatusItem(OrderStatusService::STATUS_DISPATCH_AUTHORIZATION_REQUESTED);

        Livewire::actingAs($user)
            ->test(DispatchAuthorizationManager::class)
            ->assertSee('Autorizar salidas parciales')
            ->assertSee('LF17483')
            ->call('authorizeItem', $item->id)
            ->assertHasNoErrors();

        $this->assertSame(
            OrderStatusService::STATUS_APPROVED_PARTIAL_DISPATCH,
            $item->fresh()->status
        );
    }

    public function test_delivered_item_cannot_register_delivery_again(): void
    {
        $item = $this->orderStatusItem(OrderStatusService::STATUS_DELIVERED);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Esta partida ya fue entregada');

        app(RegisterDelivery::class)->handle($item, 1);
    }

    private function orderStatusItem(string $status): OrderStatusItem
    {
        $order = SaleOrder::query()->create([
            'reference' => 'OC-TEST-'.uniqid(),
            'date' => now()->toDateString(),
            'client_name' => 'Cliente prueba',
            'payment_type' => 'Contado',
            'status' => 'confirmed',
            'currency' => 'USD',
        ]);
        $orderStatus = OrderStatus::query()->create(['sale_order_id' => $order->id]);

        return $orderStatus->items()->create([
            'part_name' => 'Filtro de aceite',
            'part_number' => 'LF17483',
            'quantity_ordered' => 1,
            'quantity_kardex_entry' => 1,
            'quantity_kardex_output' => 0,
            'quantity_delivered' => $status === OrderStatusService::STATUS_DELIVERED ? 1 : 0,
            'status' => $status,
        ]);
    }

    private function userWithPermissions(array $permissions): User
    {
        $role = Role::query()->create(['name' => 'Operaciones prueba', 'slug' => 'operaciones-prueba']);
        $permissionIds = collect($permissions)->map(fn (string $permission) => Permission::query()->firstOrCreate(
            ['slug' => $permission],
            ['name' => $permission, 'module' => 'Order Status']
        )->id);
        $role->permissions()->sync($permissionIds);

        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);

        return $user;
    }
}
