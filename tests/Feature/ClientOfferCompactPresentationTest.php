<?php

namespace Tests\Feature;

use App\Domain\Ventas\LicitacionesProveedor\Actions\ManageSupplierBidSelection;
use App\Livewire\Ventas\Cotizaciones\OfertaCliente\GestorOfertaCliente;
use App\Models\Entity;
use App\Models\QuotationRequest;
use App\Models\QuotationRequestItem;
use App\Models\Role;
use App\Models\SupplierBidResponse;
use App\Models\SupplierBidResponseItem;
use App\Models\SupplierRfqDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClientOfferCompactPresentationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private QuotationRequest $quote;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'sales_compact@example.com',
            'is_active' => true,
        ]);
        $adminRole = Role::firstOrCreate(
            ['slug' => User::ROLE_ADMIN],
            ['name' => 'Administrador']
        );
        $this->user->roles()->attach($adminRole->id);

        $this->quote = QuotationRequest::factory()->create([
            'user_id' => $this->user->id,
            'reference' => 'SC-COMPACT-TEST',
            'status' => QuotationRequest::STATUS_CLIENT_OFFER_GENERATED,
            'commercial_phase' => 'client_offer',
            'currency' => 'NIO',
            'tax_percent' => 0,
            'notes' => 'Comentario previo de la solicitud',
        ]);
    }

    public function test_client_offer_panel_does_not_contain_rate_cards_or_quick_item_form(): void
    {
        $supplier = Entity::create([
            'name' => 'Proveedor Test',
            'type' => 'proveedor',
            'status' => 'activo',
        ]);

        $item = QuotationRequestItem::create([
            'quotation_request_id' => $this->quote->id,
            'part_number' => 'PART-001',
            'part_name' => 'Bomba Hidráulica',
            'description' => 'Bomba Hidráulica',
            'quantity' => 2,
            'unit_cost' => 100,
            'unit_price' => 5000.00,
            'line_total' => 10000.00,
            'is_approved' => false,
        ]);

        $rfq = SupplierRfqDocument::create([
            'quotation_request_id' => $this->quote->id,
            'supplier_id' => $supplier->id,
            'rfq_number' => 'RFQ-TEST-001',
            'status' => SupplierRfqDocument::STATUS_SUPPLIER_RESPONSE_RECEIVED,
        ]);

        $response = SupplierBidResponse::create([
            'quotation_request_id' => $this->quote->id,
            'supplier_id' => $supplier->id,
            'response_code' => 'RESP-001',
            'currency' => 'USD',
            'created_by' => $this->user->id,
            'general_delivery_time' => '15 días',
            'general_warranty' => '1 año',
            'status' => 'received',
            'preliquidation' => [
                'settings' => ['transport' => 'air', 'margin' => 20, 'exchange' => 37.0],
                'weights' => [],
            ],
        ]);

        $responseItem = SupplierBidResponseItem::create([
            'supplier_bid_response_id' => $response->id,
            'quotation_request_item_id' => $item->id,
            'customer_request_item_id' => $item->id,
            'quoted_part_number' => 'PART-001',
            'quoted_description' => 'Bomba Hidráulica',
            'quoted_quantity' => 2,
            'unit_cost' => 100,
            'currency' => 'USD',
            'preliquidated_unit_price' => 135.14,
            'preliquidated_unit_price_nio' => 5000.00,
            'delivery_time' => '15 días',
            'warranty' => '1 año',
            'item_status' => 'quoted',
        ]);

        $manageSelection = app(ManageSupplierBidSelection::class);
        $manageSelection->selectItem($responseItem, $this->user);
        $manageSelection->sendSelectionsToClientOffer($this->quote, $this->user);

        $test = Livewire::actingAs($this->user)
            ->test(GestorOfertaCliente::class, ['quoteId' => $this->quote->id]);

        $html = $test->html();

        // 1. Unwanted rate cards and notes must not be present
        $this->assertStringNotContainsString('Tasa aplicada', $html);
        $this->assertStringNotContainsString('Estado tasa', $html);
        $this->assertStringNotContainsString('Usando una tasa almacenada previamente', $html);

        // 2. Unwanted quick item creation card must not be present
        $this->assertStringNotContainsString('Agregar partida rápida', $html);
        $this->assertStringNotContainsString('Agregar partida y proveedor', $html);

        // 3. Client price is read-only formatted text
        $this->assertStringContainsString('C$ 5,000.00', $html);
        $this->assertStringNotContainsString('wire:change="updateClientPrice', $html);

        // 4. Preliquidation total equals Offer Subtotal, and 15% sales IVA is applied
        $this->assertEquals(15.00, (float) $this->quote->fresh()->tax_percent);
        $this->assertEquals(10000.00, (float) $this->quote->fresh()->subtotal);
        $this->assertEquals(11500.00, (float) $this->quote->fresh()->total_amount);
        $this->assertStringContainsString('Subtotal', $html);
        $this->assertStringContainsString('C$ 10,000.00', $html);
        $this->assertStringContainsString('IVA (15%)', $html);
        $this->assertStringContainsString('C$ 1,500.00', $html);
        $this->assertStringContainsString('Total Oferta Cliente', $html);
        $this->assertStringContainsString('C$ 11,500.00', $html);

        // 5. Offer notes is empty by default (does not leak prior request notes)
        $this->assertEquals('', $test->get('offerNotes'));
        $test->set('offerNotes', 'Garantía extendida 2 años.')
            ->call('saveClientOfferNotes');
        $this->assertEquals('Garantía extendida 2 años.', $this->quote->fresh()->offer_notes);
        $this->assertEquals('Comentario previo de la solicitud', $this->quote->fresh()->notes);

        // 6. Supplier preliquidation selection is visible and available even with single response
        $this->assertStringContainsString('selectSupplierResponseForItem', $html);
        $this->assertStringContainsString('Proveedor Test', $html);
    }

    public function test_can_select_and_switch_supplier_preliquidation_for_client_offer(): void
    {
        $supplierA = Entity::create([
            'name' => 'Proveedor Alfa',
            'type' => 'proveedor',
            'status' => 'activo',
        ]);
        $supplierB = Entity::create([
            'name' => 'Proveedor Beta',
            'type' => 'proveedor',
            'status' => 'activo',
        ]);

        $item = QuotationRequestItem::create([
            'quotation_request_id' => $this->quote->id,
            'part_number' => 'FILT-01',
            'part_name' => 'Filtro Primario',
            'description' => 'Filtro Primario',
            'quantity' => 1,
            'unit_cost' => 50,
            'unit_price' => 2000.00,
            'line_total' => 2000.00,
            'is_approved' => true,
        ]);

        $respA = SupplierBidResponse::create([
            'quotation_request_id' => $this->quote->id,
            'supplier_id' => $supplierA->id,
            'response_code' => 'RESP-A',
            'currency' => 'USD',
            'created_by' => $this->user->id,
            'status' => 'received',
            'preliquidation' => [
                'settings' => ['transport' => 'air', 'margin' => 20, 'exchange' => 37.0],
                'weights' => [],
            ],
        ]);
        $itemA = SupplierBidResponseItem::create([
            'supplier_bid_response_id' => $respA->id,
            'quotation_request_item_id' => $item->id,
            'customer_request_item_id' => $item->id,
            'quoted_part_number' => 'FILT-01',
            'quoted_quantity' => 1,
            'unit_cost' => 50,
            'currency' => 'USD',
            'preliquidated_unit_price' => 70.00,
            'preliquidated_unit_price_nio' => 2590.00,
            'item_status' => 'quoted',
        ]);

        $respB = SupplierBidResponse::create([
            'quotation_request_id' => $this->quote->id,
            'supplier_id' => $supplierB->id,
            'response_code' => 'RESP-B',
            'currency' => 'USD',
            'created_by' => $this->user->id,
            'status' => 'received',
            'preliquidation' => [
                'settings' => ['transport' => 'sea', 'margin' => 15, 'exchange' => 37.0],
                'weights' => [],
            ],
        ]);
        $itemB = SupplierBidResponseItem::create([
            'supplier_bid_response_id' => $respB->id,
            'quotation_request_item_id' => $item->id,
            'customer_request_item_id' => $item->id,
            'quoted_part_number' => 'FILT-01',
            'quoted_quantity' => 1,
            'unit_cost' => 40,
            'currency' => 'USD',
            'preliquidated_unit_price' => 50.00,
            'preliquidated_unit_price_nio' => 1850.00,
            'item_status' => 'quoted',
        ]);

        $test = Livewire::actingAs($this->user)
            ->test(GestorOfertaCliente::class, ['quoteId' => $this->quote->id]);

        $html = $test->html();
        $this->assertStringContainsString('Proveedor Alfa', $html);
        $this->assertStringContainsString('Proveedor Beta', $html);

        // Switch to Supplier B
        $test->call('selectSupplierResponseForItem', $item->id, $itemB->id, $item->version);
        $this->assertEquals(1850.00, (float) $item->fresh()->unit_price);
        $this->assertEquals($supplierB->id, $item->fresh()->selected_supplier_id);

        // Bulk apply Supplier A
        $test->call('applySupplierResponseToAllItems', $respA->id);
        $this->assertEquals(2590.00, (float) $item->fresh()->unit_price);
        $this->assertEquals($supplierA->id, $item->fresh()->selected_supplier_id);
    }

    public function test_can_select_and_update_warranty_for_client_offer_item(): void
    {
        $item = QuotationRequestItem::create([
            'quotation_request_id' => $this->quote->id,
            'part_number' => 'CAT-1',
            'part_name' => 'Filtro Hidráulico',
            'description' => 'Filtro Hidráulico',
            'quantity' => 2,
            'unit_cost' => 100,
            'unit_price' => 2500.00,
            'line_total' => 5000.00,
            'is_approved' => true,
        ]);

        $test = Livewire::actingAs($this->user)
            ->test(GestorOfertaCliente::class, ['quoteId' => $this->quote->id]);

        $html = $test->html();
        $this->assertStringContainsString('Garantía', $html);
        $this->assertStringContainsString('updateWarrantyType', $html);
        $this->assertStringContainsString('A1 (6-12 meses)', $html);
        $this->assertStringContainsString('B1 (6 meses)', $html);

        // Update warranty to B1
        $test->call('updateWarrantyType', $item->id, 'B1', $item->version);
        $this->assertEquals('B1', $item->fresh()->warranty_type);

        // Apply plus extension
        $test->call('updateWarrantyExtension', $item->id, true, $item->fresh()->version);
        $this->assertTrue((bool) $item->fresh()->has_warranty_extension);

        // Remove plus extension
        $test->call('updateWarrantyExtension', $item->id, false, $item->fresh()->version);
        $this->assertFalse((bool) $item->fresh()->has_warranty_extension);

        // Set to no warranty
        $test->call('updateWarrantyType', $item->id, '', $item->fresh()->version);
        $this->assertNull($item->fresh()->warranty_type);
        $this->assertFalse((bool) $item->fresh()->has_warranty_extension);
    }
}
