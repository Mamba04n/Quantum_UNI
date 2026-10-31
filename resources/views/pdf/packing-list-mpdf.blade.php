@extends('pdf.layouts.base-compact')

@section('title', 'Lista de empaque ' . $order['reference'])

@section('header_right')
    <div class="document-title" style="font-size: 26px; letter-spacing: 4px; color: #111827;">LISTA DE EMPAQUE</div>
    <table class="meta-table">
        <tr><td class="meta-label">Orden</td><td class="meta-value">{{ $order['reference'] }}</td></tr>
        <tr><td class="meta-label">Fecha</td><td class="meta-value">{{ $order['date'] }}</td></tr>
        <tr><td class="meta-label">Fecha de salida</td><td class="meta-value">{{ now()->format('d/m/Y') }}</td></tr>
    </table>
@endsection

@push('styles')
    <style>
        .packing-label { color: #111827; font-weight: bold; font-size: 9px; }
        .packing-value { color: #111827; font-size: 9px; }
        .summary-title { border: 1px solid #d7dce4; border-bottom: none; padding: 7px 8px; font-weight: bold; color: #111827; text-transform: uppercase; }
        .items-table th { background: #991b1b; border-color: #991b1b; font-size: 7px; text-align: left; }
        .items-table td { height: 34px; font-size: 7.4px; padding: 3px; }
        .product-photo { width: 34px; height: 34px; object-fit: cover; border: 1px solid #d7dce4; }
        .control-note { margin-top: 6px; border: 1px solid #bfdbfe; background: #eff6ff; color: #1d4ed8; padding: 5px; font-size: 7.4px; font-weight: bold; }
        .thanks { text-align: right; font-size: 12px; line-height: 1.15; font-weight: bold; color: #111827; }
    </style>
@endpush

@section('content')
    <table style="margin-bottom: 10px;">
        <tr>
            <td style="width: 50%; padding-right: 14px;">
                <table>
                    <tr><td class="packing-label" style="width: 55px;">Origen:</td><td class="packing-value">{{ $company['name'] ?? 'Quantum Soluciones S.A.' }}</td></tr>
                    <tr><td></td><td class="packing-value">{{ $company['address'] ?? 'Managua, Nicaragua' }}</td></tr>
                    <tr><td class="packing-label">Telefono:</td><td class="packing-value">{{ $company['phone'] ?? '+505 7669 8164' }}</td></tr>
                    <tr><td class="packing-label">Correo:</td><td class="packing-value">{{ $company['email'] ?? 'ventas@quantumsolucionesnic.com' }}</td></tr>
                </table>
            </td>
            <td style="width: 50%; padding-left: 14px;">
                <table>
                    <tr><td class="packing-label" style="width: 58px;">Destino:</td><td class="packing-value">{{ $order['customer'] ?: '-' }}</td></tr>
                    <tr><td class="packing-label">Direccion</td><td class="packing-value">{{ $order['shipping_address'] ?: 'Pendiente de confirmar' }}</td></tr>
                    <tr><td class="packing-label">RUC:</td><td class="packing-value">-</td></tr>
                    <tr><td class="packing-label">Correo:</td><td class="packing-value">{{ $order['email'] ?: '-' }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table style="margin-bottom: 10px;">
        <tr>
            <td class="packing-label" style="width: 90px;">Seguimiento:</td>
            <td class="packing-value">{{ $shipment['tracking_number'] ?: $order['tracking_code'] ?: '-' }}</td>
            <td class="packing-label text-right" style="width: 90px;">Transportista:</td>
            <td class="packing-value text-right">{{ $shipment['carrier'] ?: '-' }}</td>
        </tr>
    </table>

    <div class="summary-title">Resumen de lista de empaque</div>
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 10%;">Foto</th>
                <th style="width: 16%;">Numero de parte</th>
                <th style="width: 34%;">Descripcion</th>
                <th style="width: 8%; text-align: center;">Cant.</th>
                <th style="width: 12%;">Reemplazo</th>
                <th style="width: 10%; text-align: center;">Peso (LB)</th>
                <th style="width: 10%; text-align: center;">LxAxH (IN)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
                <tr>
                    <td class="text-center">
                        @if(!empty($item['image_path']) && is_file($item['image_path']))
                            <img src="{{ $item['image_path'] }}" class="product-photo">
                        @elseif(!empty($item['image_data_uri']))
                            <img src="{{ $item['image_data_uri'] }}" class="product-photo">
                        @elseif(!empty($item['image_src']))
                            <img src="{{ $item['image_src'] }}" class="product-photo">
                        @else
                            <span class="small">Sin foto</span>
                        @endif
                    </td>
                    <td class="font-bold">{{ $item['part_number'] ?: '-' }}</td>
                    <td>
                        <span class="font-bold">{{ $item['description'] }}</span>
                        @if($item['brand'])<br><span class="small">Marca: {{ $item['brand'] }}</span>@endif
                        @if($item['model'])<span class="small"> | Modelo: {{ $item['model'] }}</span>@endif
                    </td>
                    <td class="text-center font-bold">{{ number_format($item['quantity_dispatched'], 0) }}</td>
                    <td>{{ $item['replacement_part'] ?? '-' }}</td>
                    <td class="text-center">{{ $item['weight_lb'] ?? '-' }}</td>
                    <td class="text-center">{{ $item['dimensions_in'] ?? '-' }}</td>
                </tr>
            @endforeach
            @for($i = count($items); $i < 10; $i++)
                <tr>
                    <td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td>
                </tr>
            @endfor
            <tr>
                <td colspan="5" style="border: none;"></td>
                <td class="font-bold text-right">peso total:</td>
                <td class="text-center">-</td>
            </tr>
        </tbody>
    </table>

    <div class="control-note">
        Control ERP: este packing list se genera automaticamente al registrar la salida desde bodega. El sistema exige Kardex entrada y Kardex salida antes del despacho.
    </div>

    <table style="margin-top: 12px;">
        <tr>
            <td style="width: 65%;">
                <div class="packing-label" style="font-size: 10px;">Instrucciones de entrega</div>
                <div class="packing-value" style="margin-top: 6px; line-height: 1.5;">
                    Entregar al cliente junto con factura y documentos de soporte. Verificar cantidades contra esta lista de empaque.
                </div>
            </td>
            <td style="width: 35%;" class="thanks">
                Gracias por<br>comprar con nosotros.
            </td>
        </tr>
    </table>
@endsection
