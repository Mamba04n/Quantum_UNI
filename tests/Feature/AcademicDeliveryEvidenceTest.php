<?php

namespace Tests\Feature;

use App\Domain\Ventas\Pedidos\Services\DirectInventorySaleService;
use App\Models\Entity;
use App\Models\Permission;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\SaleOrder;
use App\Models\StockMove;
use App\Models\User;
use App\Services\KpiService;
use App\Services\PurchaseReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Escenario académico aislado. No acredita ventas reales ni trazabilidad por lote. */
class AcademicDeliveryEvidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_receipt_and_inventory_sale_reconcile_documents_stock_and_kpis(): void
    {
        Storage::fake('private');
        $user = User::factory()->create();
        $permission = Permission::firstOrCreate(
            ['slug' => 'purchase_order.receive'],
            ['name' => 'Recibir compras', 'module' => 'compras'],
        );
        $user->syncPermissions([$permission->id]);
        $this->actingAs($user);
        $supplier = Entity::create(['name' => 'Proveedor académico E2', 'type' => 'proveedor', 'status' => 'active']);
        $customer = Entity::create(['name' => 'Cliente académico E2', 'type' => 'cliente', 'status' => 'active']);
        $product = Product::factory()->create([
            'sku' => 'E2-FILTRO-ACADEMICO', 'name' => 'Filtro de aceite académico',
            'type' => 'pieza', 'stock_on_hand' => 0, 'cost' => 0, 'total_cost' => 0,
        ]);
        $purchase = PurchaseOrder::create([
            'supplier_id' => $supplier->id, 'reference_number' => 'E2-OC-001',
            'status' => 'ordered', 'order_date' => today(), 'total_fob' => 1000,
        ]);
        $line = $purchase->items()->create([
            'product_id' => $product->id, 'quantity' => 10, 'unit_price' => 100,
            'total_price' => 1000, 'received_quantity' => 0,
        ]);
        $receipt = app(PurchaseReceiptService::class)->receive($purchase, [$line->id => 10], $user);
        $this->assertSame(10, (int) $product->fresh()->stock_on_hand);
        $this->assertSame('received', $purchase->fresh()->status);
        $this->assertDatabaseHas('stock_moves', ['purchase_receipt_id' => $receipt->id, 'quantity' => 10]);
        try {
            app(PurchaseReceiptService::class)->receive($purchase->fresh(), [$line->id => 1], $user);
            $this->fail('Una compra totalmente recibida no debe admitirse otra vez.');
        } catch (ValidationException) {
            $this->assertSame(10, (int) $product->fresh()->stock_on_hand);
            $this->assertSame(1, $purchase->receipts()->count());
        }
        $payload = [
            'customer_id' => $customer->id, 'currency' => 'NIO', 'payment_type' => 'Contado',
            'payment_method' => 'Efectivo', 'payment_reference' => 'E2-CAJA',
            'due_date' => today()->toDateString(), 'notes' => 'Caso académico, no operación real',
            'apply_tax' => false, 'idempotency_key' => (string) Str::uuid(),
            'items' => [['product_id' => $product->id, 'quantity' => 4, 'unit_price' => 150]],
        ];
        $sales = app(DirectInventorySaleService::class);
        $order = $sales->create($payload, $user);
        $retry = $sales->create($payload, $user);
        $this->assertSame($order->id, $retry->id);
        $this->assertSame(1, SaleOrder::count());
        $this->assertSame(6, (int) $product->fresh()->stock_on_hand);
        $this->assertSame(6, (int) StockMove::where('product_id', $product->id)->sum('quantity'));
        $this->assertDatabaseHas('stock_moves', ['sale_order_id' => $order->id, 'quantity' => -4]);
        $this->assertEquals(600, $order->invoices()->firstOrFail()->total);
        $kpi = app(KpiService::class);
        $this->assertEquals(600, $kpi->getSalesVolume([])['totalMoney']);
        $this->assertEquals(4, $kpi->getSalesVolume([])['units']);
        $this->assertEquals(600, $kpi->getInventoryTotalCost([])['totalValue']);
        $this->assertEquals(6, $kpi->getInventoryTotalCost([])['units']);
    }
}
