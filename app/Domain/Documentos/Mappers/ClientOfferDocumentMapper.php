<?php

namespace App\Domain\Documentos\Mappers;

use App\Models\QuotationRequest;
use App\Models\QuotationRequestItem;

class ClientOfferDocumentMapper
{
    public function map(QuotationRequest $request): array
    {
        return [
            'items' => $request->items->map(fn (QuotationRequestItem $item) => $this->mapItem($item))->values()->all(),
        ];
    }

    public function mapItem(QuotationRequestItem $item): array
    {
        $selection = $item->supplierBidSelection;
        $responseItem = $selection?->responseItem;

        $quantity = (float) ($item->quantity ?? $selection?->selected_quantity ?? 0);
        $unitPrice = (float) ($item->unit_price ?? 0);

        $partName = $this->firstFilled([
            $item->part_name ?? null,
            data_get($item, 'product.name'),
            $responseItem?->quoted_description,
            $item->description ?? null,
        ]);
        $description = $this->firstFilled([
            $item->description ?? null,
            $responseItem?->quoted_description,
            $partName,
        ]);
        $partNumber = $this->firstFilled([
            $item->part_number ?? null,
            data_get($item, 'product.part_number'),
            data_get($item, 'product.sku'),
            $responseItem?->quoted_part_number,
        ]);
        $brand = $this->firstFilled([
            $responseItem?->quoted_brand,
            $item->brand ?? null,
            data_get($item, 'product.brand'),
        ]);
        $model = $this->firstFilled([
            $responseItem?->quoted_model,
            $item->model ?? null,
            $item->machine_model ?? null,
        ]);
        $serialNumber = $this->firstFilled([
            $responseItem?->quoted_serial_number,
            $responseItem?->quoted_serial,
            $item->serial_number ?? null,
        ]);

        return [
            'part_name' => $partName,
            'description' => $description,
            'part_number' => $partNumber,
            'brand' => $brand,
            'model' => $model,
            'serial_number' => $serialNumber,
            'quantity' => $quantity,
            'unit' => $item->unit ?: 'unidad',
            'unit_price' => $unitPrice,
            'line_total' => round($quantity * $unitPrice, 2),
            'delivery_time' => $selection?->delivery_time ?: $item->supplier_delivery_time ?: optional($selection?->estimated_arrival_date)->format('d/m/Y'),
            'warranty' => $selection?->warranty,
            'warranty_type' => $item->warranty_type,
            'has_warranty_extension' => $item->has_warranty_extension,
        ];
    }

    private function firstFilled(array $values): string
    {
        foreach ($values as $value) {
            if (filled($value)) {
                return (string) $value;
            }
        }

        return '-';
    }
}
