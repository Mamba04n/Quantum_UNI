<?php

namespace App\Exports;

use App\Models\StockMove;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class KardexExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        private ?int $productId = null,
        private ?string $sku = null,
        private ?string $type = null,
        private ?string $dateFrom = null,
        private ?string $dateTo = null,
    )
    {
    }

    public function collection()
    {
        return StockMove::with('product')
            ->when($this->productId, fn ($q) => $q->where('product_id', $this->productId))
            ->when($this->sku, fn ($q) => $q->whereHas('product', fn ($product) => $product->where('sku', 'like', '%' . $this->sku . '%')))
            ->when($this->type, fn ($q) => $q->where('movement_type', $this->type))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('movement_date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('movement_date', '<=', $this->dateTo))
            ->orderBy('movement_date')
            ->orderBy('id')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Fecha',
            'Producto',
            'SKU',
            'Tipo',
            'Cantidad',
            'Saldo Resultante',
            'Costo Entrada',
            'Costo Salida',
            'Costo Unitario',
            'Costo Total Producto',
            'Referencia',
            'Adjunto',
        ];
    }

    public function map($move): array
    {
        return [
            optional($move->movement_date)->format('Y-m-d') ?: optional($move->created_at)->format('Y-m-d'),
            $move->product?->name,
            $move->product?->sku,
            $move->movement_type?->label() ?? (((float) $move->quantity) >= 0 ? 'Entrada' : 'Salida'),
            $move->quantity,
            $move->balance_after,
            $move->cost_in,
            $move->cost_out,
            $move->unit_cost,
            $move->total_cost_after,
            $move->reference,
            $move->attachment_path,
        ];
    }
}
