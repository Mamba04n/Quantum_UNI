<?php

namespace Tests\Feature;

use App\Domain\Pedidos\OrderStatus\Services\OrderStatusService;
use App\Livewire\Pedidos\OrderStatusShow;
use App\Models\Entity;
use App\Models\OrderStatus;
use App\Models\OrderStatusItem;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SaleOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class OrderStatusCancellationWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    private function createUserWithPermissions(array $permissions): User
    {
        $role = Role::query()->create(['name' => 'Tester Rol', 'slug' => 'tester-rol-'.uniqid()]);
        $permissionIds = collect($permissions)->map(fn (string $permission) => Permission::query()->firstOrCreate(
            ['slug' => $permission],
            ['name' => $permission, 'module' => 'Order Status']
        )->id);
        $role->permissions()->sync($permissionIds);

        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function createOrderWithItems(): array
    {
        $order = SaleOrder::query()->create([
            'reference' => 'SO-TEST-'.uniqid(),
            'date' => now()->toDateString(),
            'client_name' => 'Cliente Test',
            'payment_type' => 'Contado',
            'status' => 'solicitado_proveedor',
            'currency' => 'USD',
        ]);

        $orderStatus = OrderStatus::query()->create([
            'sale_order_id' => $order->id,
            'administrative_status' => OrderStatusService::STATUS_WAITING_SUPPLIER,
        ]);

        $supplier = Entity::query()->create([
            'name' => 'Proveedor Primario S.A.',
            'type' => 'proveedor',
            'status' => 'activo',
        ]);

        $item1 = $orderStatus->items()->create([
            'part_name' => 'Bomba de inyección',
            'part_number' => 'BOMBA-01',
            'quantity_ordered' => 2,
            'quantity_pending' => 2,
            'supplier_id' => $supplier->id,
            'status' => OrderStatusService::STATUS_WAITING_SUPPLIER,
        ]);

        $item2 = $orderStatus->items()->create([
            'part_name' => 'Sensor de presión',
            'part_number' => 'SENSOR-02',
            'quantity_ordered' => 1,
            'quantity_pending' => 1,
            'supplier_id' => $supplier->id,
            'status' => OrderStatusService::STATUS_WAITING_SUPPLIER,
        ]);

        return [$order, $orderStatus, $supplier, $item1, $item2];
    }

    public function test_item_cancellation_requires_double_confirmation_and_links_supplier(): void
    {
        $user = $this->createUserWithPermissions(['order_status.view', 'supplier_cancellation.register']);
        [$order, $orderStatus, $supplier, $item1] = $this->createOrderWithItems();

        $altSupplier = Entity::query()->create([
            'name' => 'Proveedor Alterno S.A.',
            'type' => 'proveedor',
            'status' => 'activo',
        ]);

        Livewire::actingAs($user)
            ->test(OrderStatusShow::class, ['order' => $order])
            ->set('selectedItemId', $item1->id)
            ->call('requestSupplierCancellation')
            ->assertSet('showCancelConfirmation', true)
            ->assertSet('cancelSupplierId', $supplier->id)
            ->assertSet('cancelConfirmation', false)
            // Attempt without checking double confirmation
            ->set('cancelReason', 'Proveedor no cuenta con existencias en fábrica.')
            ->call('cancelBySupplier')
            ->assertHasErrors(['cancelConfirmation'])
            // Now confirm double verification and link alternative supplier
            ->set('cancelSupplierId', $altSupplier->id)
            ->set('cancelConfirmation', true)
            ->call('cancelBySupplier')
            ->assertHasNoErrors()
            ->assertSet('showCancelConfirmation', false)
            ->assertSet('showReplacementForm', false);

        $item1->refresh();
        $this->assertSame(OrderStatusService::STATUS_CANCELLED_SUPPLIER, $item1->status);
        $this->assertSame($altSupplier->id, $item1->supplier_id);
        $this->assertSame('Proveedor no cuenta con existencias en fábrica.', $item1->cancellation_reason);
    }

    public function test_full_order_cancellation_requires_double_confirmation_and_cancels_all_items(): void
    {
        $user = $this->createUserWithPermissions(['order_status.view', 'supplier_cancellation.register', 'order_status.manage']);
        [$order, $orderStatus, $supplier, $item1, $item2] = $this->createOrderWithItems();

        Livewire::actingAs($user)
            ->test(OrderStatusShow::class, ['order' => $order])
            ->call('requestFullOrderCancellation')
            ->assertSet('showFullOrderCancelConfirmation', true)
            ->assertSet('fullOrderCancelConfirmation', false)
            // Attempt without checking confirmation
            ->set('fullOrderCancelReason', 'Cliente cancela por retraso de entrega.')
            ->call('cancelFullOrder')
            ->assertHasErrors(['fullOrderCancelConfirmation'])
            // Double confirm
            ->set('fullOrderCancelConfirmation', true)
            ->call('cancelFullOrder')
            ->assertHasNoErrors()
            ->assertSet('showFullOrderCancelConfirmation', false);

        $item1->refresh();
        $item2->refresh();
        $order->refresh();
        $orderStatus->refresh();

        $this->assertSame(OrderStatusService::STATUS_CANCELLED_SUPPLIER, $item1->status);
        $this->assertSame(OrderStatusService::STATUS_CANCELLED_SUPPLIER, $item2->status);
        $this->assertSame('cancelado', $order->status);
        $this->assertSame('cancelado_total', $orderStatus->administrative_status);
    }
}
