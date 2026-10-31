<?php

namespace App\Domain\Compras\PurchaseOrders\Documents;

use App\Models\ApprovalRequest;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderDocument;
use App\Models\QuotationRequest;
use App\Models\User;
use Illuminate\Support\Collection;

class PurchaseOrderProviderDocumentMapper
{
    public function map(PurchaseOrderDocument $document, ?ApprovalRequest $approval, ?QuotationRequest $quotation, PurchaseOrder $purchaseOrder, Collection $items, ?User $generatedBy = null): array
    {
        if ($approval) {
            $approval->loadMissing('tracker');
        }
        $purchaseOrder->loadMissing('supplier');
        $currency = $items->pluck('currency')->filter()->first() ?: 'USD';
        $subtotal = round((float) $items->sum(fn ($item) => (float) $item['subtotal']), 2);

        return [
            'approval' => $approval,
            'quotation' => $quotation,
            'generated_by' => $generatedBy,
            'document' => [
                'id' => $document->id,
                'status' => $document->status,
                'code' => $purchaseOrder->reference_number,
                'tracking_code' => $approval?->tracker?->tracking_code,
                'date' => now()->format('d/m/Y'),
                'quote_to_name' => $document->quote_to_name,
            ],
            'supplier' => [
                'name' => $purchaseOrder->supplier?->name,
                'contact' => $purchaseOrder->supplier?->contact_name,
                'email' => $purchaseOrder->supplier?->email,
                'phone' => $purchaseOrder->supplier?->phone_mobile,
            ],
            'consignee' => [
                'name' => $document->consignee_name,
                'comment' => $document->consignee_comment,
                'urgent_comment' => $document->consignee_urgent_comment,
                'address_1' => $document->consignee_address_1,
                'address_2' => $document->consignee_address_2,
                'country' => $document->consignee_country,
                'city' => $document->consignee_city,
                'phone' => $document->consignee_phone,
                'emails' => $document->consignee_emails,
            ],
            'shipping' => [
                'method' => $document->shipping_method,
                'terms' => $document->shipping_terms,
                'incoterm' => $document->incoterm,
                'carrier' => $document->carrier_name,
                'estimated_shipping_date' => optional($document->estimated_shipping_date)->format('d/m/Y'),
                'estimated_arrival_date' => optional($document->estimated_arrival_date)->format('d/m/Y'),
                'comments' => $document->shipping_comments,
            ],
            'items' => $items->values()->all(),
            'totals' => [
                'subtotal' => $subtotal,
                'discount' => 0,
                'shipping' => 0,
                'taxes' => 0,
                'total' => $subtotal,
                'currency' => $currency,
            ],
            'comments' => [
                'supplier' => $document->supplier_comments,
                'internal' => $document->internal_comments,
                'special_instructions' => $document->special_instructions,
                'footer' => $document->document_footer_comments,
            ],
        ];
    }
}
