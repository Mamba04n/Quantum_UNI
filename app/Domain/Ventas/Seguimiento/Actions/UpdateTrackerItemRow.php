<?php

namespace App\Domain\Ventas\Seguimiento\Actions;

use App\Models\QuotationRequestItem;

class UpdateTrackerItemRow
{
    public function execute(int $itemId, string $field, mixed $value): void
    {
        $item = QuotationRequestItem::find($itemId);
        if (! $item) {
            return;
        }

        if (! in_array($field, ['quantity', 'unit_price'], true)) {
            return;
        }

        $item->$field = $value;
        if ($field === 'unit_price' || $field === 'quantity') {
            $item->line_total = (float) $item->quantity * (float) ($item->unit_price ?? 0);
        }
        $item->save();

        $parent = $item->quotationRequest;
        if ($parent) {
            $parent->subtotal = $parent->items->sum('line_total');
            $parent->total_amount = $parent->subtotal;
            $parent->save();
        }
    }
}
