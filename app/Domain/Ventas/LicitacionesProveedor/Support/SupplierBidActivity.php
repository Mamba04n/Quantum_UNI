<?php

namespace App\Domain\Ventas\LicitacionesProveedor\Support;

use App\Models\ActivityLog;
use App\Models\QuotationRequestItem;

class SupplierBidActivity
{
    public function logGlobal(string $action, string $description, array $metadata = []): void
    {
        ActivityLog::log('ventas', $action, $description, $metadata);
    }

    public function lineLabel(QuotationRequestItem $item): string
    {
        return $item->part_number ?: ($item->part_name ?: 'sin referencia');
    }
}
