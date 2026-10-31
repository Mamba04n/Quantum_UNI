<?php

namespace App\Domain\Ventas\LicitacionesProveedor\Support;

use App\Models\QuotationRequest;
use App\Models\QuotationRequestItem;
use App\Models\SupplierBidResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SupplierBidResponsePayload
{
    public function validate(Collection $items, array $rows, SupplierBidResponse $response, array $attachments = [], bool $allowIncomplete = false): void
    {
        if ((bool) config('sales.require_supplier_proforma', false) && empty($attachments) && blank($response->attachment_path)) {
            throw ValidationException::withMessages(['supplierProformas' => 'Adjunta el PDF/proforma del proveedor antes de guardar.']);
        }

        $hasValidRow = false;
        foreach ($items as $item) {
            $row = $rows[$item->id] ?? [];
            $status = $this->normalizeItemStatus($row);

            if ($status === 'not_quoted') {
                continue;
            }

            $quantity = (float) ($row['quoted_quantity'] ?? $row['quantity'] ?? 0);
            $unitCost = (float) ($row['unit_cost'] ?? 0);

            if ($quantity <= 0) {
                throw ValidationException::withMessages(["supplierBidItems.{$item->id}.quantity" => 'La cantidad cotizada debe ser mayor que 0.']);
            }

            if (! $allowIncomplete && $unitCost <= 0) {
                throw ValidationException::withMessages(["supplierBidItems.{$item->id}.unit_cost" => 'Ingresa precio proveedor mayor que 0 para esta partida.']);
            }

            if ($unitCost < 0) {
                throw ValidationException::withMessages(["supplierBidItems.{$item->id}.unit_cost" => 'El precio proveedor no puede ser negativo.']);
            }

            if (! $allowIncomplete && blank($row['estimated_arrival_date'] ?? null) && blank($row['delivery_time'] ?? null)) {
                throw ValidationException::withMessages(["supplierBidItems.{$item->id}.delivery_time" => 'Indica el tiempo o la fecha estimada de entrega para esta partida.']);
            }

            $hasValidRow = $hasValidRow || $unitCost > 0;
        }

        if (! $allowIncomplete && !$hasValidRow) {
            throw ValidationException::withMessages(['supplierBidItems' => 'Debes registrar al menos una partida cotizada o alternativa con precio.']);
        }
    }

    public function itemsForResponse(QuotationRequest $request, mixed $itemIds): Collection
    {
        $ids = collect($itemIds ?: [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        if ($ids->isEmpty()) {
            return $request->items;
        }

        return $request->items
            ->filter(fn (QuotationRequestItem $item) => $ids->contains((int) $item->id))
            ->values();
    }

    public function normalizeItemStatus(array $row): string
    {
        if (! empty($row['not_quoted'])) {
            return 'not_quoted';
        }

        $status = $row['item_status'] ?? 'quoted';

        return in_array($status, ['quoted', 'alternative', 'not_quoted'], true) ? $status : 'quoted';
    }
}
