<?php

namespace Tests\Feature;

use App\Domain\Documentos\Mappers\ClientOfferDocumentMapper;
use App\Domain\Ventas\LicitacionesProveedor\Actions\ManageSupplierBidSelection;
use App\Livewire\Ventas\Cotizaciones\OfertaCliente\GestorOfertaCliente;
use App\Models\Entity;
use App\Models\QuotationRequest;
use App\Models\QuotationRequestItem;
use App\Models\SupplierBid;
use App\Models\SupplierBidResponse;
use App\Models\SupplierBidResponseItem;
use App\Models\SupplierBidSelection;
use App\Models\SupplierRfqDocument;
use App\Models\User;
use App\Services\PreliquidationCalculator;
use App\Services\SaveSupplierPreliquidation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClientOfferSupplierPriceSelectionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private QuotationRequest $quote;
    private QuotationRequestItem $item;
    private Entity $supplierA;
    private Entity $supplierB;
    private SupplierBidResponse $responseA;
    private SupplierBidResponse $responseB;
    private SupplierBidResponseItem $responseItemA;
    private SupplierBidResponseItem $responseItemB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'sales@example.com',
            'is_active' => true,
        ]);
        $adminRole = \App\Models\Role::firstOrCreate(
            ['slug' => User::ROLE_ADMIN],
            ['name' => 'Administrador']
        );
        $this->user->roles()->attach($adminRole->id);

        $this->quote = QuotationRequest::factory()->create([
            'user_id' => $this->user->id,
            'reference' => 'SC-TEST-PRELIQ',
            'status' => QuotationRequest::STATUS_OFFER_DRAFT,
            'commercial_phase' => 'client_offer',
            'currency' => 'NIO',
            'tax_percent' => 15,
        ]);

        $this->item = QuotationRequestItem::create([
            'quotation_request_id' => $this->quote->id,
            'part_number' => 'FIL-001',
            'part_name' => 'Filtro de Aceite',
            'description' => 'Filtro de Aceite Heavy Duty',
            'quantity' => 2,
            'unit' => 'PZA',
            'unit_price' => 0,
            'line_total' => 0,
            'status' => 'pending_review',
            'version' => 1,
        ]);

        $this->supplierA = Entity::create([
            'name' => 'Proveedor Alfa',
            'type' => 'proveedor',
            'status' => 'activo',
        ]);

        $this->supplierB = Entity::create([
            'name' => 'Proveedor Beta',
            'type' => 'proveedor',
            'status' => 'activo',
        ]);

        $bidA = SupplierBid::create([
            'quotation_request_id' => $this->quote->id,
            'supplier_id' => $this->supplierA->id,
            'reference' => 'BID-A',
            'status' => 'active',
            'currency' => 'USD',
            'created_by' => $this->user->id,
        ]);

        $bidB = SupplierBid::create([
            'quotation_request_id' => $this->quote->id,
            'supplier_id' => $this->supplierB->id,
            'reference' => 'BID-B',
            'status' => 'active',
            'currency' => 'USD',
            'created_by' => $this->user->id,
        ]);

        $this->responseA = SupplierBidResponse::create([
            'quotation_request_id' => $this->quote->id,
            'supplier_id' => $this->supplierA->id,
            'supplier_bid_id' => $bidA->id,
            'response_code' => 'RESP-A',
            'created_by' => $this->user->id,
            'status' => 'submitted',
            'currency' => 'USD',
        ]);

        $this->responseB = SupplierBidResponse::create([
            'quotation_request_id' => $this->quote->id,
            'supplier_id' => $this->supplierB->id,
            'supplier_bid_id' => $bidB->id,
            'response_code' => 'RESP-B',
            'created_by' => $this->user->id,
            'status' => 'submitted',
            'currency' => 'USD',
        ]);

        $this->responseItemA = SupplierBidResponseItem::create([
            'supplier_bid_response_id' => $this->responseA->id,
            'quotation_request_item_id' => $this->item->id,
            'quoted_part_number' => 'FIL-001-A',
            'quoted_description' => 'Filtro Alfa',
            'quoted_quantity' => 2,
            'unit_cost' => 50.00,
            'currency' => 'USD',
            'item_status' => 'quoted',
            'is_alternative' => false,
            'preliquidation_key' => 'row_a',
            'preliquidated_unit_price' => 85.00,
            'preliquidated_unit_price_nio' => 3145.00, // 85 * 37.00
            'ddp_unit_cost' => 58.50,
            'cif_unit_cost' => 54.00,
            'margin_percentage' => 31.18,
            'delivery_time' => '3 días',
            'warranty' => '1 año',
        ]);

        $this->responseItemB = SupplierBidResponseItem::create([
            'supplier_bid_response_id' => $this->responseB->id,
            'quotation_request_item_id' => $this->item->id,
            'quoted_part_number' => 'FIL-001-B',
            'quoted_description' => 'Filtro Beta',
            'quoted_quantity' => 2,
            'unit_cost' => 60.00,
            'currency' => 'USD',
            'item_status' => 'quoted',
            'is_alternative' => false,
            'preliquidation_key' => 'row_b',
            'preliquidated_unit_price' => 105.00,
            'preliquidated_unit_price_nio' => 3885.00, // 105 * 37.00
            'ddp_unit_cost' => 70.00,
            'cif_unit_cost' => 65.00,
            'margin_percentage' => 33.33,
            'delivery_time' => '7 días',
            'warranty' => '6 meses',
        ]);
    }

    public function test_save_supplier_preliquidation_computes_and_persists_prices_in_db(): void
    {
        SupplierRfqDocument::create([
            'quotation_request_id' => $this->quote->id,
            'supplier_id' => $this->supplierA->id,
            'status' => SupplierRfqDocument::STATUS_SENT_TO_SUPPLIER,
            'selected_item_ids' => [$this->item->id],
        ]);
        $this->quote->update([
            'status' => QuotationRequest::STATUS_SUPPLIER_RESPONSE_PENDING,
            'commercial_phase' => 'supplier_bid',
        ]);

        $calculatorDefaults = PreliquidationCalculator::defaults();
        $calculatorDefaults['exchange'] = 37.00;
        $calculatorDefaults['margin'] = 30.00;

        $rows = [
            'row_a' => [
                'response_item_id' => $this->responseItemA->id,
                'quoted_part_number' => 'FIL-001',
                'quoted_description' => 'Filtro Heavy Duty',
                'quoted_quantity' => 2,
                'unit_cost' => 50.00,
                'weight_lb' => 1.0,
                'quotation_request_item_id' => $this->item->id,
            ],
        ];

        $freshResponse = SupplierBidResponse::findOrFail($this->responseA->id);
        $revision = SaveSupplierPreliquidation::revision($freshResponse);

        app(SaveSupplierPreliquidation::class)->save(
            $this->quote,
            $this->supplierA,
            $this->responseA->id,
            $calculatorDefaults,
            $rows,
            [],
            $this->user,
            $revision
        );

        $this->responseItemA->refresh();

        $this->assertNotNull($this->responseItemA->preliquidated_unit_price);
        $this->assertGreaterThan(50.00, (float) $this->responseItemA->preliquidated_unit_price);
        $this->assertEqualsWithDelta(
            (float) $this->responseItemA->preliquidated_unit_price * 37.00,
            (float) $this->responseItemA->preliquidated_unit_price_nio,
            0.5
        );
        $this->assertNotNull($this->responseItemA->ddp_unit_cost);
    }

    public function test_manage_supplier_bid_selection_propagates_preliquidated_price_to_quote_item(): void
    {
        $action = app(ManageSupplierBidSelection::class);
        $action->selectItem($this->responseItemA, $this->user);

        $this->item->refresh();

        // Target currency of quote is NIO, so unit_price should be preliquidated NIO price
        $this->assertSame('3145.00', number_format((float) $this->item->unit_price, 2, '.', ''));
        $this->assertSame('6290.00', number_format((float) $this->item->line_total, 2, '.', '')); // 3145 * 2
        $this->assertSame($this->supplierA->id, $this->item->selected_supplier_id);

        $selection = SupplierBidSelection::where('quotation_request_item_id', $this->item->id)->first();
        $this->assertNotNull($selection);
        $this->assertSame('85.00', number_format((float) $selection->selected_unit_price, 2, '.', ''));
        $this->assertSame('3145.00', number_format((float) $selection->selected_unit_price_nio, 2, '.', ''));
    }

    public function test_client_offer_livewire_can_select_supplier_and_updates_price_and_totals(): void
    {
        Livewire::actingAs($this->user)
            ->test(GestorOfertaCliente::class, ['quoteId' => $this->quote->id])
            // Select Supplier B
            ->call('selectSupplierResponseForItem', $this->item->id, $this->responseItemB->id, $this->item->version)
            ->assertDispatched('notify');

        $this->item->refresh();
        $this->assertSame($this->supplierB->id, $this->item->selected_supplier_id);
        $this->assertSame('3885.00', number_format((float) $this->item->unit_price, 2, '.', ''));
        $this->assertSame('7770.00', number_format((float) $this->item->line_total, 2, '.', '')); // 3885 * 2

        $this->quote->refresh();
        $this->assertSame('7770.00', number_format((float) $this->quote->subtotal, 2, '.', ''));

        // Now switch back to Supplier A
        Livewire::actingAs($this->user)
            ->test(GestorOfertaCliente::class, ['quoteId' => $this->quote->id])
            ->call('selectSupplierResponseForItem', $this->item->id, $this->responseItemA->id, $this->item->version)
            ->assertDispatched('notify');

        $this->item->refresh();
        $this->assertSame($this->supplierA->id, $this->item->selected_supplier_id);
        $this->assertSame('3145.00', number_format((float) $this->item->unit_price, 2, '.', ''));
        $this->assertSame('6290.00', number_format((float) $this->item->line_total, 2, '.', ''));

        $this->quote->refresh();
        $this->assertSame('6290.00', number_format((float) $this->quote->subtotal, 2, '.', ''));
    }

    public function test_client_offer_document_mapper_maps_selected_preliquidated_price_for_pdf(): void
    {
        // Select Supplier A first
        app(ManageSupplierBidSelection::class)->selectItem($this->responseItemA, $this->user);

        $this->quote->refresh();
        $this->quote->loadMissing('items.supplierBidSelection.responseItem.response.supplier');

        $mapper = app(ClientOfferDocumentMapper::class);
        $data = $mapper->map($this->quote);

        $this->assertCount(1, $data['items']);
        $mappedItem = $data['items'][0];

        $this->assertSame(3145.0, (float) $mappedItem['unit_price']);
        $this->assertSame(6290.0, (float) $mappedItem['line_total']);
        $this->assertSame('3 días', $mappedItem['delivery_time']);
        $this->assertSame('1 año', $mappedItem['warranty']);

        // Now select Supplier B
        app(ManageSupplierBidSelection::class)->selectItem($this->responseItemB, $this->user);

        $this->quote->refresh();
        $this->quote->loadMissing('items.supplierBidSelection.responseItem.response.supplier');

        $data = $mapper->map($this->quote);
        $mappedItem = $data['items'][0];

        $this->assertSame(3885.0, (float) $mappedItem['unit_price']);
        $this->assertSame(7770.0, (float) $mappedItem['line_total']);
        $this->assertSame('7 días', $mappedItem['delivery_time']);
        $this->assertSame('6 meses', $mappedItem['warranty']);
    }

    public function test_manual_price_edit_in_client_offer_is_preserved(): void
    {
        // Start with Supplier A
        app(ManageSupplierBidSelection::class)->selectItem($this->responseItemA, $this->user);
        QuotationRequest::whereKey($this->quote->id)->update(['status' => QuotationRequest::STATUS_OFFER_DRAFT]);
        $freshItem = $this->item->fresh();

        // User overrides the unit price manually in Client Offer
        Livewire::actingAs($this->user)
            ->test(GestorOfertaCliente::class, ['quoteId' => $this->quote->id])
            ->call('updateClientPrice', $freshItem->id, '3500.00', (int) $freshItem->version)
            ->assertHasNoErrors();

        $freshItem->refresh();
        $this->assertSame('3500.00', number_format((float) $freshItem->unit_price, 2, '.', ''));
        $this->assertSame('7000.00', number_format((float) $freshItem->line_total, 2, '.', ''));
    }
}
