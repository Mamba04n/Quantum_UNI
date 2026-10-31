<?php

namespace App\Exports;

use App\Models\LogisticsShipment;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ShipmentsExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        return LogisticsShipment::with(['client', 'product', 'user'])->latest()->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Orden',
            'Cliente',
            'Producto Movido',
            'Cantidad',
            'Costo de Envío',
            'Moneda',
            'Registrado Por',
            'Fecha de Envío',
        ];
    }

    public function map($shipment): array
    {
        return [
            $shipment->id,
            $shipment->order_id,
            $shipment->client ? $shipment->client->name : 'N/A',
            $shipment->product ? $shipment->product->name : 'N/A',
            $shipment->quantity,
            $shipment->shipping_cost,
            $shipment->currency,
            $shipment->user ? $shipment->user->name : 'Sistema',
            $shipment->shipped_at ? $shipment->shipped_at->format('Y-m-d H:i') : '',
        ];
    }
}
