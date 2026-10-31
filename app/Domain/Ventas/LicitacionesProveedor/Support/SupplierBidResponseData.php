<?php

namespace App\Domain\Ventas\LicitacionesProveedor\Support;

use App\Models\Entity;
use App\Models\QuotationRequestItem;
use App\Models\SupplierBid;
use App\Models\SupplierBidResponse;
use App\Models\User;

class SupplierBidResponseData
{
    public function responseAttributes(?SupplierBid $supplierBid, Entity $supplier, array $data, SupplierBidResponse $response, User $user): array
    {
        return [
            'supplier_bid_id' => $supplierBid?->id,
            'supplier_id' => $supplier->id,
            'contact_name' => $data['contact_name'] ?? null,
            'currency' => strtoupper($data['currency'] ?? 'USD'),
            'payment_terms' => $data['payment_terms'] ?? null,
            'general_warranty' => $data['general_warranty'] ?? null,
            'general_delivery_time' => $data['general_delivery_time'] ?? null,
            'valid_until' => $data['valid_until'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => 'received',
            'created_by' => $response->created_by ?: $user->id,
        ];
    }

    public function itemAttributes(QuotationRequestItem $item, array $row, string $status, array $data, bool $notQuoted = false): array
    {
        $quantity = $notQuoted ? 0 : (float) ($row['quoted_quantity'] ?? $row['quantity'] ?? $item->quantity ?? 1);
        $unitCost = $notQuoted ? null : (float) ($row['unit_cost'] ?? 0);

        return [
            'customer_request_item_id' => $item->id,
            'quoted_part_number' => $row['quoted_part_number'] ?? $item->part_number,
            'quoted_description' => $row['quoted_description'] ?? $item->part_name ?? $item->description,
            'quoted_model' => $row['quoted_model'] ?? $row['part_model'] ?? $item->model,
            'quoted_serial' => $row['quoted_serial'] ?? $row['serial_number'] ?? $item->serial_number,
            'quoted_serial_number' => $row['quoted_serial'] ?? $row['serial_number'] ?? $item->serial_number,
            'quoted_brand' => $row['quoted_brand'] ?? $row['brand'] ?? $item->brand,
            'quoted_quantity' => $quantity,
            'unit_cost' => $unitCost,
            'currency' => strtoupper($row['currency'] ?? $data['currency'] ?? 'USD'),
            'estimated_arrival_date' => $row['estimated_arrival_date'] ?? null,
            'delivery_time' => $row['delivery_time'] ?? $data['general_delivery_time'] ?? null,
            'warranty' => $row['warranty'] ?? $data['general_warranty'] ?? null,
            'payment_terms' => $row['payment_terms'] ?? $data['payment_terms'] ?? null,
            'internal_comment' => $row['internal_comment'] ?? $row['notes'] ?? null,
            'item_status' => $status,
            'alternative_group_key' => $status === 'alternative' ? ($row['alternative_group_key'] ?? $item->part_number ?? 'item-'.$item->id) : null,
            'is_alternative' => $status === 'alternative',
        ];
    }
}
