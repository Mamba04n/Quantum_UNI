<?php

namespace App\Livewire\Administracion\Concerns;

use App\Models\ApprovalRequest;
use App\Models\PurchaseOrder;
use App\Models\QuotationRequestItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

trait ManagesApprovalFinancials
{
    private function approvedItems(ApprovalRequest $approval): Collection
    {
        return $approval->quotationRequest?->items
            ?->filter(fn (QuotationRequestItem $item) => (bool) $item->is_approved)
            ->values() ?? collect();
    }

    private function excludedItems(ApprovalRequest $approval): Collection
    {
        return $approval->quotationRequest?->items
            ?->reject(fn (QuotationRequestItem $item) => (bool) $item->is_approved)
            ->values() ?? collect();
    }

    private function supplierGroups(ApprovalRequest $approval): Collection
    {
        return $this->approvedItems($approval)->groupBy(function (QuotationRequestItem $item) {
            return $item->supplierBidSelection?->supplier?->name
                ?? $item->selectedSupplier?->name
                ?? 'Proveedor no seleccionado';
        });
    }

    private function financialSummary(ApprovalRequest $approval): array
    {
        $items        = $this->approvedItems($approval);
        $exchangeRate = (float) ($approval->quotationRequest?->exchange_rate ?? 0);

        $costSubtotal = $items->sum(
            fn (QuotationRequestItem $item) =>
                (float) $item->quantity * $this->supplierCostInNio($item, $exchangeRate)
        );

        $salesTotal = $items->sum(
            fn (QuotationRequestItem $item) =>
                (float) $item->quantity * (float) $item->unit_price
        );

        $grossMargin = $salesTotal - $costSubtotal;

        return [
            'supplier_cost_subtotal'  => round($costSubtotal, 2),
            'logistics_estimate'      => 0,
            'tax_estimate'            => 0,
            'estimated_total_cost'    => round($costSubtotal, 2),
            'client_sales_total'      => round($salesTotal, 2),
            'gross_margin'            => round($grossMargin, 2),
            'margin_percent'          => $salesTotal > 0
                ? round(($grossMargin / $salesTotal) * 100, 2)
                : 0,
            'exchange_rate'           => $exchangeRate ?: null,
            'currency'                => $items->first()?->supplierBidSelection?->currency
                ?? $items->first()?->selectedSupplierBidItem?->currency
                ?? 'USD',
        ];
    }

    private function documents(ApprovalRequest $approval): Collection
    {
        $documents = collect();
        $quotation = $approval->quotationRequest;

        if ($quotation?->client_offer_pdf_path) {
            $documents->push((object) [
                'name' => 'Oferta cliente PDF',
                'type' => 'client_offer_pdf',
                'phase' => 'oferta_cliente',
                'date' => $quotation->updated_at,
                'user' => $quotation->user?->name ?? 'Sistema',
                'url' => route('sales.quotations.pdf', $quotation->id),
            ]);
        }

        foreach ($approval->tracker?->attachments ?? collect() as $attachment) {
            $documents->push((object) [
                'name' => $attachment->file_name,
                'type' => $attachment->file_type ?: 'archivo',
                'phase' => $attachment->phase,
                'date' => $attachment->created_at,
                'user' => $attachment->uploader?->name ?? 'Sistema',
                'url' => route('tracker.attachments.show', $attachment),
                'download_url' => route('tracker.attachments.show', $attachment).'?download=1',
            ]);
        }

        return $documents;
    }

    private function existingPurchaseOrders(ApprovalRequest $approval): Collection
    {
        if (! Schema::hasColumn('purchase_orders', 'approval_request_id')) {
            return collect();
        }

        return PurchaseOrder::with(['supplier', 'items.supplierResponseItem.response.supplier', 'items.quotationItem'])
            ->where('approval_request_id', $approval->id)
            ->get();
    }

    public function purchasePreparationForItem(ApprovalRequest $approval, QuotationRequestItem $item)
    {
        return $approval->quotationRequest?->purchasePreparationItems
            ?->firstWhere('quotation_request_item_id', $item->id);
    }

    /**
     * Supplier cost in the supplier's original currency (USD).
     */
    public function supplierCost(QuotationRequestItem $item): float
    {
        return (float) ($item->supplierBidSelection?->selected_unit_cost ?? $item->unit_cost ?? 0);
    }

    /**
     * Supplier cost converted to NIO for margin calculations.
     * Uses supplier_unit_cost_nio if already stored, otherwise multiplies by exchange_rate.
     */
    public function supplierCostInNio(QuotationRequestItem $item, float $exchangeRate = 0): float
    {
        // Prefer the pre-calculated NIO cost stored on the offer item
        if (!empty($item->supplier_unit_cost_nio) && (float) $item->supplier_unit_cost_nio > 0) {
            return (float) $item->supplier_unit_cost_nio;
        }

        $usdCost = $this->supplierCost($item);

        // If exchange rate is available, convert USD → NIO
        if ($exchangeRate > 0) {
            return $usdCost * $exchangeRate;
        }

        // Fallback: return raw cost (same currency assumed)
        return $usdCost;
    }

    public function supplierName(QuotationRequestItem $item): string
    {
        return $item->supplierBidSelection?->supplier?->name
            ?? $item->selectedSupplier?->name
            ?? 'Proveedor no seleccionado';
    }

    public function itemMargin(QuotationRequestItem $item): float
    {
        // unit_price is always in NIO; convert supplier cost to NIO for correct margin
        $exchangeRate = (float) ($item->quotationRequest?->exchange_rate ?? 0);
        return (float) $item->unit_price - $this->supplierCostInNio($item, $exchangeRate);
    }

    public function canViewCosts(): bool
    {
        return $this->canAny([
            'internal_approval.view_costs',
            'supplier_bid.view_costs',
            'order_approval.view_costs',
            'tracker.view_costs',
        ]);
    }

    public function canViewMargin(): bool
    {
        return $this->canAny([
            'internal_approval.view_margin',
            'order_approval.view_costs',
            'tracker.view_costs',
        ]);
    }

    public function canViewDocuments(): bool
    {
        return $this->canAny([
            'internal_approval.view_documents',
            'tracker.view_sensitive_documents',
            'tracker.view_supplier_quotes',
        ]);
    }

    private function canAny(array $permissions): bool
    {
        $user = auth()->user();

        return $user && collect($permissions)->contains(fn (string $permission) => $user->can($permission));
    }
}
