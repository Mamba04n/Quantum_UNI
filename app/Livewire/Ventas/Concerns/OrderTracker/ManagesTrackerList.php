<?php

namespace App\Livewire\Ventas\Concerns\OrderTracker;

use App\Models\OrderTracker;
use App\Models\QuotationRequest;
use App\Models\SaleOrder;
use App\Services\OrderStatusService;

trait ManagesTrackerList
{
    public function getListItems($search = '')
    {
        $listItems = [];
        $trackerQuery = OrderTracker::with('references')
            ->latest('updated_at');

        if ($search) {
            $searchStr = '%'.$search.'%';
            $matchingQuoteIds = QuotationRequest::query()
                ->where(fn ($query) => $query->where('reference', 'like', $searchStr)->orWhere('quote_to_name', 'like', $searchStr))
                ->pluck('id');
            $matchingOrderIds = SaleOrder::query()
                ->where(fn ($query) => $query->where('reference', 'like', $searchStr)->orWhere('client_name', 'like', $searchStr))
                ->pluck('id');

            $trackerQuery->where(function ($q) use ($searchStr, $matchingQuoteIds, $matchingOrderIds) {
                $q->where('operational_tracking_code', 'like', $searchStr)
                    ->orWhere('main_reference', 'like', $searchStr)
                    ->orWhereHas('references', function ($references) use ($searchStr, $matchingQuoteIds, $matchingOrderIds) {
                        $references->where('reference_code', 'like', $searchStr)
                            ->orWhere(fn ($query) => $query->where('source_module', 'quotation_requests')->whereIn('source_id', $matchingQuoteIds))
                            ->orWhere(fn ($query) => $query->where('source_module', 'sale_orders')->whereIn('source_id', $matchingOrderIds));
                    });
            });
        }

        $trackers = $trackerQuery->limit(250)->get();
        $quoteIds = $trackers->flatMap(fn (OrderTracker $tracker) => $tracker->references->where('source_module', 'quotation_requests')->pluck('source_id'))->filter()->unique();
        $orderIds = $trackers->flatMap(fn (OrderTracker $tracker) => $tracker->references->where('source_module', 'sale_orders')->pluck('source_id'))->filter()->unique();
        $quotes = QuotationRequest::query()->whereIn('id', $quoteIds)->get()->keyBy('id');
        $orders = SaleOrder::query()->with([
            'orderStatus.items.product',
            'orderStatus.items.supplier',
            'orderStatus.items.saleOrderItem',
        ])->whereIn('id', $orderIds)->get()->keyBy('id');

        foreach ($trackers as $tracker) {
            $quoteRef = $tracker->references->firstWhere('source_module', 'quotation_requests');
            $orderRef = $tracker->references->firstWhere('source_module', 'sale_orders');
            $quote = $quoteRef ? $quotes->get($quoteRef->source_id) : null;
            $order = $orderRef ? $orders->get($orderRef->source_id) : null;

            if ($order?->orderStatus?->items?->isNotEmpty()) {
                foreach ($order->orderStatus->items as $orderItem) {
                    $listItems[] = [
                        'id' => $order->id,
                        'type' => 'order',
                        'order_status_item_id' => $orderItem->id,
                        'tracking_code' => $tracker->tracking_code,
                        'reference' => ($tracker->main_reference ?: $order->reference).' / '.($orderItem->part_number ?: 'Partida '.$orderItem->id),
                        'client' => $order->client_name ?? 'Sin cliente',
                        'phase' => $this->mapOrderStatusItemToStage($orderItem->status),
                        'total' => (float) ($orderItem->quantity_ordered ?? 0) * (float) ($orderItem->saleOrderItem?->unit_price ?? 0),
                        'status' => app(OrderStatusService::class)->humanStatus($orderItem->status),
                        'date' => $orderItem->updated_at,
                        'data' => $order,
                        'product' => $orderItem->part_name ?: $orderItem->product?->name,
                        'progress' => $this->itemProgressPercent($orderItem),
                    ];
                }

                continue;
            }

            $listItems[] = [
                'id' => $quote?->id ?? $order?->id ?? $tracker->id,
                'type' => $quote ? 'quote' : 'order',
                'tracking_code' => $tracker->tracking_code,
                'reference' => $tracker->main_reference ?: ($quote?->reference ?? $order?->reference),
                'client' => $quote?->quote_to_name ?? $order?->client_name ?? 'Sin cliente',
                'phase' => $tracker->operational_phase,
                'total' => $quote?->total_amount ?? $order?->total_amount ?? 0,
                'status' => $tracker->operational_status,
                'date' => $tracker->updated_at,
                'data' => $quote ?: $order,
            ];
        }

        return $listItems;
    }
}
