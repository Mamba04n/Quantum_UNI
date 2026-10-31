<?php

namespace Tests\Feature;

use App\Models\Entity;
use App\Models\ClientMachine;
use App\Models\Permission;
use App\Models\Product;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceiptItem;
use App\Models\QuotationRequest;
use App\Models\QuotationRequestItem;
use App\Models\Role;
use App\Models\SupplierBidResponse;
use App\Models\SupplierBidResponseItem;
use App\Models\SupplierBidSelection;
use App\Models\User;
use App\Livewire\Ventas\CustomerRequestForm;
use App\Services\InventoryReservationService;
use App\Services\PurchaseOrderService;
use App\Services\PurchaseReceiptService;
use App\Services\RfqPdfDataMapper;
use App\Services\SupplierBidResponseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class QuotationRequestItemNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_rfq_mapper_prefers_normalized_item_fields(): void
    {
        $request = $this->quotation();
        $item = QuotationRequestItem::create([
            'quotation_request_id' => $request->id,
            'part_name' => 'Bomba hidraulica',
            'part_number' => 'HP-100',
            'brand' => 'Parker',
            'model' => 'M4',
            'serial_number' => 'SER-1',
            'description' => 'Texto largo legacy',
            'quantity' => 2,
            'unit' => 'unidad',
            'status' => 'pending_review',
        ]);

        $mapped = app(RfqPdfDataMapper::class)->mapRfqItem($item);

        $this->assertSame('Bomba hidraulica', $mapped['part_name']);
        $this->assertSame('HP-100', $mapped['reference']);
        $this->assertSame('Parker', $mapped['brand']);
        $this->assertSame('M4', $mapped['model']);
        $this->assertSame(2, $mapped['quantity']);
    }

    public function test_customer_request_form_creates_multiple_request_items(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $role = $this->role('ventas-multiple-items-test', ['customer_request.create']);
        $user->roles()->sync([$role->id]);

        $this->actingAs($user);

        Livewire::test(CustomerRequestForm::class)
            ->set('quoteToName', 'Cliente Multiproducto')
            ->set('quoteToEmail', 'cliente.multi@example.test')
            ->set('machinery', 'Excavadora')
            ->set('machineBrand', 'Komatsu')
            ->set('machineModel', 'PC200')
            ->set('equipmentSerial', 'EQ-200')
            ->set('description', 'Solicitud con multiples productos para prueba.')
            ->set('items.0.part_name', 'Filtro hidraulico')
            ->set('items.0.part_code', 'FIL-100')
            ->set('items.0.part_brand', 'Donaldson')
            ->set('items.0.quantity', 2)
            ->set('items.0.unit', 'unidad')
            ->call('addItem')
            ->set('items.1.part_name', 'Alternador')
            ->set('items.1.part_code', 'ALT-500')
            ->set('items.1.part_brand', 'Bosch')
            ->set('items.1.quantity', 1)
            ->set('items.1.unit', 'unidad')
            ->set('evidenceAttachment', UploadedFile::fake()->image('evidencia.jpg'))
            ->call('save')
            ->assertHasNoErrors();

        $quotation = QuotationRequest::where('quote_to_name', 'Cliente Multiproducto')->firstOrFail();

        $this->assertSame(2, $quotation->items()->count());
        $this->assertSame(3, $quotation->fresh()->request_quantity);
        $this->assertDatabaseHas('quotation_request_items', [
            'quotation_request_id' => $quotation->id,
            'part_name' => 'Filtro hidraulico',
            'part_number' => 'FIL-100',
            'quantity' => 2,
        ]);
        $this->assertDatabaseHas('quotation_request_items', [
            'quotation_request_id' => $quotation->id,
            'part_name' => 'Alternador',
            'part_number' => 'ALT-500',
            'quantity' => 1,
        ]);
    }

    public function test_two_customer_request_forms_opened_together_receive_distinct_references_on_save(): void
    {
        Storage::fake('private');

        $user = User::factory()->create();
        $role = $this->role('ventas-concurrent-requests-test', ['customer_request.create']);
        $user->roles()->sync([$role->id]);
        $this->actingAs($user);

        $first = Livewire::test(CustomerRequestForm::class);
        $second = Livewire::test(CustomerRequestForm::class);

        $this->assertSame($first->get('reference'), $second->get('reference'));

        foreach ([$first, $second] as $index => $component) {
            $component
                ->set('quoteToName', 'Cliente simultaneo '.($index + 1))
                ->set('machinery', 'Excavadora')
                ->set('description', 'Solicitud concurrente de prueba.')
                ->set('items.0.part_name', 'Filtro '.($index + 1))
                ->set('items.0.part_code', 'CONCURRENT-'.($index + 1))
                ->set('evidenceAttachment', UploadedFile::fake()->image('evidencia-'.$index.'.jpg'))
                ->call('save')
                ->assertHasNoErrors();
        }

        $requests = QuotationRequest::where('quote_to_name', 'like', 'Cliente simultaneo %')
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $requests);
        $this->assertSame(2, $requests->pluck('reference')->unique()->count());
        $this->assertSame(2, $requests->pluck('submission_token')->unique()->count());
    }

    public function test_customer_request_autofills_client_machine_and_product_lines(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $role = $this->role('ventas-autofill-request-test', ['customer_request.create']);
        $user->roles()->sync([$role->id]);

        $client = Entity::create([
            'name' => 'Agroservicios San Miguel',
            'type' => 'cliente',
            'email' => 'compras@agro.test',
            'status' => 'active',
        ]);

        $machine = ClientMachine::create([
            'entity_id' => $client->id,
            'name' => 'Excavadora CAT 320',
            'machine_type' => 'Excavadora',
            'brand' => 'CAT',
            'model' => '320',
            'serial_number' => 'CAT-320-001',
            'status' => 'active',
        ]);

        $filter = Product::factory()->create([
            'name' => 'Filtro hidraulico',
            'sku' => 'SKU-FH-100',
            'part_number' => 'FH-100',
            'brand' => 'Donaldson',
            'active' => true,
        ]);

        $alternator = Product::factory()->create([
            'name' => 'Alternador',
            'sku' => 'SKU-ALT-500',
            'part_number' => 'ALT-500',
            'brand' => 'Bosch',
            'active' => true,
        ]);

        $this->actingAs($user);

        Livewire::test(CustomerRequestForm::class)
            ->set('selectedClientId', (string) $client->id)
            ->assertSet('quoteToName', 'Agroservicios San Miguel')
            ->assertSet('quoteToEmail', 'compras@agro.test')
            ->assertSet('clientMachineId', (string) $machine->id)
            ->assertSet('machinery', 'Excavadora')
            ->assertSet('machineBrand', 'CAT')
            ->assertSet('machineModel', '320')
            ->assertSet('equipmentSerial', 'CAT-320-001')
            ->set('description', 'Cliente solicita piezas para mantenimiento.')
            ->set('items.0.product_id', (string) $filter->id)
            ->assertSet('items.0.part_name', 'Filtro hidraulico')
            ->assertSet('items.0.part_code', 'FH-100')
            ->assertSet('items.0.part_brand', 'Donaldson')
            ->set('items.0.quantity', 2)
            ->call('addItem')
            ->set('items.1.product_id', (string) $alternator->id)
            ->assertSet('items.1.part_name', 'Alternador')
            ->assertSet('items.1.part_code', 'ALT-500')
            ->assertSet('items.1.part_brand', 'Bosch')
            ->set('items.1.quantity', 1)
            ->set('evidenceAttachment', UploadedFile::fake()->image('evidencia.jpg'))
            ->call('save')
            ->assertHasNoErrors();

        $quotation = QuotationRequest::where('quote_to_name', 'Agroservicios San Miguel')->firstOrFail();

        $this->assertSame($machine->id, $quotation->client_machine_id);
        $this->assertSame(2, $quotation->items()->count());
        $this->assertDatabaseHas('quotation_request_items', [
            'quotation_request_id' => $quotation->id,
            'product_id' => $filter->id,
            'client_machine_id' => $machine->id,
            'part_name' => 'Filtro hidraulico',
            'part_number' => 'FH-100',
            'brand' => 'Donaldson',
            'quantity' => 2,
        ]);
        $this->assertDatabaseHas('quotation_request_items', [
            'quotation_request_id' => $quotation->id,
            'product_id' => $alternator->id,
            'client_machine_id' => $machine->id,
            'part_name' => 'Alternador',
            'part_number' => 'ALT-500',
            'brand' => 'Bosch',
            'quantity' => 1,
        ]);
    }

    public function test_inventory_reservation_uses_product_id_and_blocks_duplicate_reserved_stock(): void
    {
        $user = User::factory()->create();
        $role = $this->role('bodega-test', ['direct_order.reserve_stock']);
        $user->roles()->sync([$role->id]);

        $product = Product::factory()->create([
            'sku' => 'INV-100',
            'part_number' => 'INV-100',
            'stock_on_hand' => 2,
        ]);

        $first = $this->quotation('SC-INV-001');
        QuotationRequestItem::create([
            'quotation_request_id' => $first->id,
            'product_id' => $product->id,
            'part_name' => 'Filtro',
            'part_number' => 'INV-100',
            'brand' => 'Donaldson',
            'description' => 'Filtro',
            'quantity' => 2,
            'unit' => 'unidad',
            'status' => 'pending_review',
        ]);

        app(InventoryReservationService::class)->reserveForQuotation($first, $user);

        $second = $this->quotation('SC-INV-002');
        QuotationRequestItem::create([
            'quotation_request_id' => $second->id,
            'product_id' => $product->id,
            'part_name' => 'Filtro',
            'part_number' => 'INV-100',
            'brand' => 'Donaldson',
            'description' => 'Filtro',
            'quantity' => 1,
            'unit' => 'unidad',
            'status' => 'pending_review',
        ]);

        $this->expectException(ValidationException::class);

        app(InventoryReservationService::class)->reserveForQuotation($second, $user);
    }

    public function test_supplier_response_requires_generated_or_sent_rfq(): void
    {
        $user = User::factory()->create();
        $role = $this->role('compras-test', ['supplier_bid.add_supplier_response']);
        $user->roles()->sync([$role->id]);

        $supplier = Entity::create([
            'name' => 'Proveedor Test',
            'type' => 'proveedor',
        ]);

        $request = $this->quotation('SC-RFQ-BLOCK');
        $item = QuotationRequestItem::create([
            'quotation_request_id' => $request->id,
            'part_name' => 'Motor de arranque',
            'part_number' => 'ST-100',
            'description' => 'Motor de arranque',
            'quantity' => 1,
            'unit' => 'unidad',
            'status' => 'pending_review',
        ]);

        $this->expectException(ValidationException::class);

        app(SupplierBidResponseService::class)->saveResponse($request, [
            'supplier_id' => $supplier->id,
            'currency' => 'USD',
            'items' => [
                $item->id => [
                    'quantity' => 1,
                    'unit_cost' => 100,
                    'delivery_time' => '15 dias',
                ],
            ],
        ], $user);
    }

    public function test_purchase_order_is_generated_from_supplier_selection_and_creates_product_if_missing(): void
    {
        $user = User::factory()->create();
        $role = $this->role('compras-flow-test', ['purchases.order.create']);
        $user->roles()->sync([$role->id]);

        $supplier = Entity::create([
            'name' => 'Proveedor Seleccionado',
            'type' => 'proveedor',
        ]);

        $request = $this->quotation('SC-PO-001');
        $request->update(['status' => QuotationRequest::STATUS_APPROVED_TOTAL]);

        $item = QuotationRequestItem::create([
            'quotation_request_id' => $request->id,
            'part_name' => 'Alternador',
            'part_number' => 'ALT-500',
            'description' => 'Alternador',
            'quantity' => 1,
            'unit' => 'unidad',
            'status' => 'pending_review',
        ]);

        $response = SupplierBidResponse::create([
            'quotation_request_id' => $request->id,
            'supplier_id' => $supplier->id,
            'currency' => 'USD',
            'status' => 'received',
            'created_by' => $user->id,
        ]);

        $responseItem = SupplierBidResponseItem::create([
            'supplier_bid_response_id' => $response->id,
            'quotation_request_item_id' => $item->id,
            'quoted_part_number' => 'ALT-500-A',
            'quoted_description' => 'Alternador alternativo',
            'quoted_brand' => 'Bosch',
            'quoted_model' => 'B500',
            'quoted_quantity' => 1,
            'unit_cost' => 250,
            'currency' => 'USD',
            'delivery_time' => '10 dias',
            'item_status' => 'alternative',
            'is_alternative' => true,
        ]);

        $selection = SupplierBidSelection::create([
            'quotation_request_id' => $request->id,
            'quotation_request_item_id' => $item->id,
            'supplier_bid_response_item_id' => $responseItem->id,
            'selected_supplier_id' => $supplier->id,
            'selected_quantity' => 1,
            'selected_unit_cost' => 250,
            'currency' => 'USD',
            'delivery_time' => '10 dias',
            'is_alternative' => true,
            'selected_by' => $user->id,
            'selected_at' => now(),
        ]);

        $orders = app(PurchaseOrderService::class)->createOrdersFromQuotationSelections($request->fresh(), $user);

        $this->assertCount(1, $orders);
        $this->assertDatabaseHas('purchase_orders', [
            'id' => $orders->first()->id,
            'supplier_id' => $supplier->id,
            'quotation_request_id' => $request->id,
            'source_type' => 'supplier_selection',
            'status' => 'ordered',
        ]);
        $this->assertDatabaseHas('purchase_order_items', [
            'supplier_bid_selection_id' => $selection->id,
            'part_number' => 'ALT-500-A',
            'brand' => 'Bosch',
            'model' => 'B500',
        ]);
        $this->assertNotNull(PurchaseOrderItem::where('supplier_bid_selection_id', $selection->id)->first()->product_id);
        $this->assertSame('purchase_ordered', $item->fresh()->status);
    }

    public function test_purchase_receipt_creates_kardex_entry_and_updates_statuses(): void
    {
        $user = User::factory()->create();
        $role = $this->role('bodega-receipt-test', ['purchase_order.receive']);
        $user->roles()->sync([$role->id]);

        $supplier = Entity::create([
            'name' => 'Proveedor Recepcion',
            'type' => 'proveedor',
        ]);

        $product = Product::factory()->create([
            'sku' => 'RCV-100',
            'part_number' => 'RCV-100',
            'stock_on_hand' => 0,
            'cost' => 50,
            'total_cost' => 0,
        ]);

        $order = app(PurchaseOrderService::class)->createOrderWithPayable([
            'supplier_id' => $supplier->id,
            'reference_number' => 'PO-RCV-001',
            'total_fob' => 100,
            'order_date' => now()->toDateString(),
            'expected_date' => now()->addDays(5)->toDateString(),
            'items' => [[
                'product_id' => $product->id,
                'part_name' => 'Sensor',
                'part_number' => 'RCV-100',
                'quantity' => 2,
                'unit_price' => 50,
                'total_price' => 100,
            ]],
        ]);

        $item = $order->items->first();
        $receipt = app(PurchaseReceiptService::class)->receive($order, [$item->id => 2], $user);

        $this->assertDatabaseHas('purchase_receipts', [
            'id' => $receipt->id,
            'purchase_order_id' => $order->id,
            'status' => 'received',
        ]);
        $this->assertSame('received', $order->fresh()->status);
        $this->assertSame('received', $item->fresh()->status);
        $this->assertSame('2.00', (string) $item->fresh()->received_quantity);
        $this->assertEquals(2.0, (float) $product->fresh()->stock_on_hand);

        $receiptItem = PurchaseReceiptItem::where('purchase_order_item_id', $item->id)->first();
        $this->assertNotNull($receiptItem->kardex_entry_id);
        $this->assertDatabaseHas('stock_moves', [
            'id' => $receiptItem->kardex_entry_id,
            'purchase_order_id' => $order->id,
            'purchase_order_item_id' => $item->id,
            'purchase_receipt_id' => $receipt->id,
            'purchase_receipt_item_id' => $receiptItem->id,
            'movement_type' => 'in',
        ]);
    }

    public function test_purchase_receipt_blocks_over_receiving(): void
    {
        $user = User::factory()->create();
        $role = $this->role('bodega-over-receipt-test', ['purchase_order.receive']);
        $user->roles()->sync([$role->id]);

        $supplier = Entity::create([
            'name' => 'Proveedor Sobre Recepcion',
            'type' => 'proveedor',
        ]);

        $product = Product::factory()->create([
            'sku' => 'RCV-200',
            'part_number' => 'RCV-200',
            'stock_on_hand' => 0,
        ]);

        $order = app(PurchaseOrderService::class)->createOrderWithPayable([
            'supplier_id' => $supplier->id,
            'reference_number' => 'PO-RCV-002',
            'total_fob' => 25,
            'order_date' => now()->toDateString(),
            'expected_date' => now()->addDays(5)->toDateString(),
            'items' => [[
                'product_id' => $product->id,
                'part_name' => 'Sello',
                'part_number' => 'RCV-200',
                'quantity' => 1,
                'unit_price' => 25,
                'total_price' => 25,
            ]],
        ]);

        $this->expectException(ValidationException::class);

        app(PurchaseReceiptService::class)->receive($order, [$order->items->first()->id => 2], $user);
    }

    private function quotation(string $reference = 'SC-NORM-001'): QuotationRequest
    {
        return QuotationRequest::create([
            'reference' => $reference,
            'date' => now()->toDateString(),
            'quote_to_name' => 'Cliente Test',
            'requested_by' => 'Tester',
            'department' => 'ventas',
            'status' => QuotationRequest::STATUS_RECEIVED,
            'subtotal' => 0,
            'total_amount' => 0,
        ]);
    }

    private function role(string $slug, array $permissions): Role
    {
        $role = Role::firstOrCreate(
            ['slug' => $slug],
            ['name' => ucfirst($slug)]
        );

        $permissionIds = collect($permissions)->map(function (string $permission) {
            return Permission::firstOrCreate(
                ['slug' => $permission],
                ['name' => $permission, 'module' => str($permission)->before('.')->toString()]
            )->id;
        });

        $role->permissions()->sync($permissionIds);

        return $role;
    }
}
