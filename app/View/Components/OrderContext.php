<?php

namespace App\View\Components;

use App\Models\OrderStatus;
use App\Models\OrderTracker;
use App\Models\PurchaseOrder;
use App\Models\QuotationRequest;
use App\Models\SaleOrder;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class OrderContext extends Component
{
    public function __construct(
        public ?QuotationRequest $quotation = null,
        public ?SaleOrder $saleOrder = null,
        public ?PurchaseOrder $purchaseOrder = null,
        public ?OrderStatus $orderStatus = null,
        public ?OrderTracker $tracker = null,
        public bool $compact = false,
    ) {
        $this->resolveContext();
    }

    public function render(): View
    {
        $reference = $this->tracker?->tracking_code
            ?: $this->tracker?->main_reference
            ?: $this->saleOrder?->reference
            ?: $this->quotation?->reference
            ?: $this->purchaseOrder?->reference_number;

        $customer = $this->tracker?->customer?->name
            ?: $this->saleOrder?->client_name
            ?: $this->quotation?->quote_to_name
            ?: $this->orderStatus?->customer?->name;

        $url = $this->tracker
            ? route('explorer.index', ['registro_tipo' => 'tracker', 'registro' => $this->tracker->id])
            : ($this->quotation
                ? route('explorer.index', ['registro_tipo' => 'quotation', 'registro' => $this->quotation->id])
                : null);

        return view('components.order-context', [
            'reference' => $reference,
            'customer' => $customer,
            'url' => $url,
            'isProvisional' => ! $this->tracker,
        ]);
    }

    private function resolveContext(): void
    {
        $this->saleOrder ??= $this->purchaseOrder?->saleOrder;
        $this->quotation ??= $this->purchaseOrder?->quotationRequest ?: $this->saleOrder?->sourceQuotationRequest;
        $this->orderStatus ??= $this->saleOrder?->orderStatus;
        $this->tracker ??= $this->orderStatus?->tracker;

        if (! $this->tracker && $this->quotation) {
            $this->tracker = OrderTracker::query()
                ->with(['customer', 'supplier'])
                ->whereHas('references', function ($references): void {
                    $references
                        ->where(function ($source): void {
                            $source->whereIn('source_module', ['quotation_request', 'quotation_requests'])
                                ->where('source_id', $this->quotation->id);
                        })
                        ->orWhere('reference_code', $this->quotation->reference);
                })
                ->latest()
                ->first();
        }

        $this->tracker?->loadMissing(['customer', 'supplier']);
        $this->saleOrder?->loadMissing(['sourceQuotationRequest', 'orderStatus.tracker']);
        $this->purchaseOrder?->loadMissing(['quotationRequest', 'saleOrder']);
        $this->orderStatus?->loadMissing(['customer', 'tracker']);
    }
}
