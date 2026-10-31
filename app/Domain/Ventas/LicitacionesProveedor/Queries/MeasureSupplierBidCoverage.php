<?php

namespace App\Domain\Ventas\LicitacionesProveedor\Queries;

use App\Models\QuotationRequest;
use App\Models\QuotationRequestItem;
use App\Models\SupplierBidResponseItem;

class MeasureSupplierBidCoverage
{
    public function execute(QuotationRequest $request): array
    {
        $request->loadMissing('items.supplierBidResponseItems.response.supplier');

        $items = $request->items->map(function (QuotationRequestItem $item) {
            $validOptions = $item->supplierBidResponseItems
                ->filter(fn (SupplierBidResponseItem $responseItem) => $responseItem->is_selectable)
                ->values();
            $alternativeOptions = $validOptions
                ->filter(fn (SupplierBidResponseItem $responseItem) => (bool) $responseItem->is_alternative)
                ->values();

            return [
                'id' => $item->id,
                'label' => $item->part_name ?: ($item->part_number ?: ($item->description ?: 'Item')),
                'part_number' => $item->part_number,
                'requested_quantity' => (float) $item->quantity,
                'valid_options' => $validOptions->count(),
                'alternative_options' => $alternativeOptions->count(),
                'supplier_names' => $validOptions
                    ->map(fn (SupplierBidResponseItem $responseItem) => $responseItem->response?->supplier?->name)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
                'status' => $validOptions->isEmpty()
                    ? 'pending'
                    : ($alternativeOptions->isNotEmpty() && $alternativeOptions->count() === $validOptions->count() ? 'alternative' : 'responded'),
            ];
        })->values();

        $responded = $items->whereIn('status', ['responded', 'alternative'])->count();

        return [
            'total' => $items->count(),
            'responded' => $responded,
            'pending' => max(0, $items->count() - $responded),
            'with_alternatives' => $items->where('status', 'alternative')->count(),
            'with_multiple_options' => $items->filter(fn (array $item) => $item['valid_options'] > 1)->count(),
            'items' => $items->all(),
        ];
    }
}
