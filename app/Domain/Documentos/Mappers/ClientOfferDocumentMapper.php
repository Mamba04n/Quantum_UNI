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

        $warrantyInfo = $this->warrantyInfo($item);

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
            'warranty_label' => $warrantyInfo['label'],
            'warranty_code' => $warrantyInfo['code'],
            'warranty_badge_bg' => $warrantyInfo['badge_bg'],
            'warranty_badge_text' => $warrantyInfo['badge_text'],
        ];
    }

    private function warrantyInfo(QuotationRequestItem $item): array
    {
        $type = strtoupper(trim((string) $item->warranty_type));
        if (in_array($type, ['', 'NONE', 'NINGUNA', 'SIN GARANTÍA'], true)) {
            return [
                'label' => 'Sin garantía',
                'code' => '—',
                'badge_bg' => '#f1f5f9',
                'badge_text' => '#94a3b8',
            ];
        }

        $info = match ($type) {
            'A1' => ['label' => 'A1 (6-12 meses)', 'bg' => '#0284c7', 'text' => '#ffffff'],
            'A2' => ['label' => 'A2 (6 meses)',    'bg' => '#bae6fd', 'text' => '#0369a1'],
            'B1' => ['label' => 'B1 (6 meses)',    'bg' => '#0d9488', 'text' => '#ffffff'],
            'B2' => ['label' => 'B2 (2-3 meses)',  'bg' => '#99f6e4', 'text' => '#0f766e'],
            'C1' => ['label' => 'C1 (2 meses)',    'bg' => '#d97706', 'text' => '#ffffff'],
            'C2' => ['label' => 'C2 (1 mes)',      'bg' => '#fde68a', 'text' => '#92400e'],
            default => ['label' => $type, 'bg' => '#e2e8f0', 'text' => '#475569'],
        };

        $code = $type;
        $label = $info['label'];
        $bg = $info['bg'];
        $text = $info['text'];

        if ($item->has_warranty_extension) {
            $label .= ' · Extensión Plus (+50%)';
        }

        return [
            'label' => $label,
            'code' => $code,
            'badge_bg' => $bg,
            'badge_text' => $text,
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
