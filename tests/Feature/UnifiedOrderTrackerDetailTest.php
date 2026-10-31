<?php

namespace Tests\Feature;

use App\Livewire\Ventas\TrackerDetailModal;
use App\Models\OrderStatus;
use App\Models\OrderStatusItem;
use App\Models\OrderTracker;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SaleOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UnifiedOrderTrackerDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_detail_recalculates_stale_operational_tracker_without_creating_another_one(): void
    {
        $permission = Permission::firstOrCreate(['slug' => 'tracker.view'], ['name' => 'Ver tracker', 'module' => 'tracker']);
        $role = Role::firstOrCreate(['slug' => 'logistica-test'], ['name' => 'Logística test']);
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user = User::factory()->create(['role' => 'logistica-test']);
        $user->roles()->sync([$role->id]);
        $order = SaleOrder::create([
            'reference' => 'VD-TRACKER-001', 'date' => today(), 'client_name' => 'Cliente Tracker',
            'payment_type' => 'Contado', 'status' => 'solicitado_proveedor', 'currency' => 'NIO',
            'subtotal' => 100, 'tax_amount' => 15, 'total_amount' => 115,
        ]);
        $tracker = OrderTracker::create([
            'operational_tracking_code' => 'TRK-TEST-001', 'operational_phase' => 'pedido_extranjero',
            'operational_status' => 'pendiente_oc', 'main_reference' => $order->reference,
        ]);
        $tracker->references()->create([
            'reference_type' => 'foreign_order', 'reference_code' => $order->reference,
            'source_module' => 'sale_orders', 'source_id' => $order->id,
        ]);
        $status = OrderStatus::create([
            'sale_order_id' => $order->id, 'order_tracker_id' => $tracker->id,
            'administrative_phase' => 'pedido_extranjero', 'administrative_status' => 'pendiente_oc',
        ]);
        OrderStatusItem::create([
            'order_status_id' => $status->id, 'part_name' => 'Filtro', 'quantity_ordered' => 1,
            'quantity_delivered' => 1, 'status' => 'entregado_total',
        ]);

        Livewire::actingAs($user)->test(TrackerDetailModal::class)
            ->call('showDetails', $order->id, 'order')
            ->assertSet('showModal', true)
            ->assertSet('selectedItem.id', $order->id)
            ->call('closeModal')
            ->assertSet('showModal', false)
            ->assertSet('selectedItem', null);

        $this->assertSame(1, OrderTracker::count());
        $this->assertSame('entregado_total', $tracker->fresh()->operational_status);
    }
}
