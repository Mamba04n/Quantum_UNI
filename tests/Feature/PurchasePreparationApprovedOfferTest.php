<?php

namespace Tests\Feature;

use App\Models\ClientOfferVersion;
use App\Models\Entity;
use App\Models\PurchasePreparationItem;
use App\Models\QuotationRequest;
use App\Models\QuotationRequestItem;
use App\Models\Role;
use App\Models\SupplierBidResponse;
use App\Models\SupplierBidResponseItem;
use App\Models\SupplierBidSelection;
use App\Models\User;
use App\Services\ApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchasePreparationApprovedOfferTest extends TestCase
{
    use RefreshDatabase;

    public function test_total_approval_automatically_prepares_exact_offer_version_after_item_replacement(): void
    {
        $admin = User::factory()->create();
        $adminRole = Role::firstOrCreate(['slug' => User::ROLE_ADMIN], ['name' => 'Administrador']);
        $admin->roles()->attach($adminRole);

        $supplier = Entity::create([
            'name' => 'Proveedor prueba',
            'type' => 'proveedor',
            'status' => 'activo',
        ]);

        $quotation = QuotationRequest::create([
            'reference' => 'SC-REPLACEMENT-001',
            'date' => now()->toDateString(),
            'quote_to_name' => 'Cliente prueba',
            'status' => QuotationRequest::STATUS_CLIENT_RESPONSE_PENDING,
            'client_offer_pdf_path' => 'offers/replacement.pdf',
            'subtotal' => 300,
            'total_amount' => 345,
        ]);

        $kept = $this->quotationItem($quotation, 'Pieza original vigente', false);
        $removed = $this->quotationItem($quotation, 'Pieza retirada', false);
        $replacement = $this->quotationItem($quotation, 'Pieza sustituta', true);

        foreach ([$kept, $replacement] as $item) {
            $this->supplierSelection($quotation, $item, $supplier, $admin);
        }

        $offer = ClientOfferVersion::create([
            'quotation_request_id' => $quotation->id,
            'version_number' => 2,
            'reference' => 'SC-REPLACEMENT-001-V2',
            'status' => 'sent_to_customer',
            'currency' => 'NIO',
            'subtotal' => 300,
            'tax_amount' => 45,
            'total_amount' => 345,
            'created_by' => $admin->id,
        ]);

        foreach ([$kept, $replacement] as $item) {
            $offer->items()->create([
                'quotation_request_item_id' => $item->id,
                'description' => $item->part_name,
                'quantity' => 1,
                'unit_cost' => 50,
                'unit_price' => 150,
                'line_total' => 150,
            ]);
        }

        app(ApprovalService::class)->registerClientApproval($quotation, $admin, 'total');

        $this->assertTrue($kept->fresh()->is_approved);
        $this->assertFalse($removed->fresh()->is_approved);
        $this->assertTrue($replacement->fresh()->is_approved);
        $this->assertEqualsCanonicalizing(
            [$kept->id, $replacement->id],
            PurchasePreparationItem::query()
                ->where('quotation_request_id', $quotation->id)
                ->where('status', '!=', PurchasePreparationItem::STATUS_EXCLUDED)
                ->pluck('quotation_request_item_id')
                ->all(),
        );
    }

    private function quotationItem(QuotationRequest $quotation, string $name, bool $approved): QuotationRequestItem
    {
        return QuotationRequestItem::create([
            'quotation_request_id' => $quotation->id,
            'part_name' => $name,
            'description' => $name,
            'quantity' => 1,
            'unit_cost' => 50,
            'unit_price' => 150,
            'line_total' => $name === 'Pieza retirada' ? 0 : 150,
            'is_approved' => $approved,
            'status' => 'quoted',
        ]);
    }

    private function supplierSelection(
        QuotationRequest $quotation,
        QuotationRequestItem $item,
        Entity $supplier,
        User $user,
    ): void {
        $response = SupplierBidResponse::create([
            'quotation_request_id' => $quotation->id,
            'supplier_id' => $supplier->id,
            'currency' => 'USD',
            'status' => 'received',
            'created_by' => $user->id,
        ]);

        $responseItem = SupplierBidResponseItem::create([
            'supplier_bid_response_id' => $response->id,
            'quotation_request_item_id' => $item->id,
            'quoted_description' => $item->part_name,
            'quoted_quantity' => 1,
            'unit_cost' => 50,
            'currency' => 'USD',
            'item_status' => 'quoted',
        ]);

        SupplierBidSelection::create([
            'quotation_request_id' => $quotation->id,
            'quotation_request_item_id' => $item->id,
            'supplier_bid_response_id' => $response->id,
            'supplier_bid_response_item_id' => $responseItem->id,
            'selected_supplier_id' => $supplier->id,
            'selected_quantity' => 1,
            'selected_unit_cost' => 50,
            'currency' => 'USD',
            'selected_by' => $user->id,
            'selected_at' => now(),
        ]);
    }
}
