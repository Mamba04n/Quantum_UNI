@extends('pdf.layouts.base-compact')

@section('title', 'OC proveedor ' . ($data['document']['code'] ?? $approval->id))
@section('doc_type', 'ORDEN DE COMPRA / PEDIDO PROVEEDOR')

@push('styles')
    <style>
        .items-table th { background: #111827; border-color: #111827; font-size: 6.8px; }
        .items-table td { font-size: 7.2px; padding: 2.8px 2.6px; }
        .compact td { border: none; padding: 1px 0; }
    </style>
@endpush

@section('header_meta')
    <tr><td class="meta-label">OC</td><td class="meta-value">{{ $data['document']['code'] ?? 'OC-'.$approval->id }}</td></tr>
    <tr><td class="meta-label">Seguimiento</td><td class="meta-value">{{ $data['document']['tracking_code'] ?? '-' }}</td></tr>
    <tr><td class="meta-label">Fecha</td><td class="meta-value">{{ $data['document']['date'] ?? now()->format('d/m/Y') }}</td></tr>
    <tr><td class="meta-label">Responsable</td><td class="meta-value">{{ $generatedBy?->name ?? 'Sistema ERP' }}</td></tr>
@endsection

@section('content')
    <table class="two-col">
        <tr>
            <td>
                <div class="box">
                    <div class="box-title">Proveedor</div>
                    <table class="compact">
                        <tr><td class="label" style="width: 32%;">Nombre</td><td>{{ $data['supplier']['name'] ?: 'Proveedor seleccionado' }}</td></tr>
                        <tr><td class="label">Contacto</td><td>{{ $data['supplier']['contact'] ?: '-' }}</td></tr>
                        <tr><td class="label">Email</td><td>{{ $data['supplier']['email'] ?: '-' }}</td></tr>
                        <tr><td class="label">Telefono</td><td>{{ $data['supplier']['phone'] ?: '-' }}</td></tr>
                    </table>
                </div>
            </td>
            <td>
                <div class="box">
                    <div class="box-title">Datos de compra</div>
                    <table class="compact">
                        <tr><td class="label" style="width: 38%;">Ordenar a</td><td>{{ $data['document']['quote_to_name'] }}</td></tr>
                        <tr><td class="label">Solicitud</td><td>{{ $quotation?->reference ?: '-' }}</td></tr>
                        <tr><td class="label">Moneda</td><td>{{ $data['totals']['currency'] }}</td></tr>
                        <tr><td class="label">Pago</td><td>{{ $data['shipping']['terms'] ?: 'Segun acuerdo proveedor' }}</td></tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <table class="two-col">
        <tr>
            <td>
                <div class="box">
                    <div class="box-title">Consignatario</div>
                    <table class="compact">
                        <tr><td class="label" style="width: 32%;">Nombre</td><td>{{ $data['consignee']['name'] }}</td></tr>
                        <tr><td class="label">Direccion 1</td><td>{{ $data['consignee']['address_1'] }}</td></tr>
                        <tr><td class="label">Direccion 2</td><td>{{ $data['consignee']['address_2'] ?: '-' }}</td></tr>
                        <tr><td class="label">Pais / ciudad</td><td>{{ $data['consignee']['country'] }} / {{ $data['consignee']['city'] }}</td></tr>
                        <tr><td class="label">Celular</td><td>{{ $data['consignee']['phone'] }}</td></tr>
                        <tr><td class="label">Correos</td><td>{{ $data['consignee']['emails'] ?: '-' }}</td></tr>
                    </table>
                    @if($data['consignee']['urgent_comment'])
                        <div class="urgent">URGENTE: {{ $data['consignee']['urgent_comment'] }}</div>
                    @endif
                </div>
            </td>
            <td>
                <div class="box">
                    <div class="box-title">Condiciones de envio</div>
                    <table class="compact">
                        <tr><td class="label" style="width: 38%;">Metodo</td><td>{{ $data['shipping']['method'] ?: '-' }}</td></tr>
                        <tr><td class="label">Incoterm</td><td>{{ $data['shipping']['incoterm'] ?: '-' }}</td></tr>
                        <tr><td class="label">Transportista</td><td>{{ $data['shipping']['carrier'] ?: '-' }}</td></tr>
                        <tr><td class="label">Fecha envio</td><td>{{ $data['shipping']['estimated_shipping_date'] ?: '-' }}</td></tr>
                        <tr><td class="label">Fecha llegada</td><td>{{ $data['shipping']['estimated_arrival_date'] ?: '-' }}</td></tr>
                    </table>
                    @if($data['shipping']['comments'])
                        <div class="small" style="margin-top: 3px;"><strong>Comentarios:</strong> {{ $data['shipping']['comments'] }}</div>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    @if($data['comments']['special_instructions'])
        <div class="urgent">INSTRUCCIONES ESPECIALES: {{ $data['comments']['special_instructions'] }}</div>
    @endif

    <div class="section-title">Productos acordados</div>
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 4%; text-align: center;">No.</th>
                <th style="width: 13%;">Referencia / P.N.</th>
                <th style="width: 31%;">Pieza</th>
                <th style="width: 10%;">Marca</th>
                <th style="width: 10%;">Modelo</th>
                <th style="width: 6%; text-align: center;">Cant.</th>
                <th style="width: 10%; text-align: right;">P. Unit.</th>
                <th style="width: 11%; text-align: right;">Subtotal</th>
                <th style="width: 5%; text-align: center;">ETA</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $index => $item)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="font-bold">{{ $item['part_number'] ?: '-' }}</td>
                    <td>
                        <span class="font-bold">{{ $item['part_name'] ?: '-' }}</span>
                        <br><span class="small">
                            Proveedor: {{ $item['supplier_name'] ?: '-' }}
                            @if($item['serial_number']) | Serie: {{ $item['serial_number'] }} @endif
                            @if($item['estimated_arrival_date']) | ETA: {{ optional($item['estimated_arrival_date'])->format('d/m/Y') }} @endif
                            @if($item['description']) | {{ $item['description'] }} @endif
                        </span>
                    </td>
                    <td>{{ $item['brand'] ?: '-' }}</td>
                    <td>{{ $item['model'] ?: '-' }}</td>
                    <td class="text-center">{{ number_format((float) $item['quantity'], 0) }}</td>
                    <td class="text-right">{{ $item['currency'] }} {{ number_format((float) $item['unit_cost'], 2) }}</td>
                    <td class="text-right">{{ $item['currency'] }} {{ number_format((float) $item['subtotal'], 2) }}</td>
                    <td class="text-center">{{ $item['estimated_arrival_date'] ? optional($item['estimated_arrival_date'])->format('d/m') : '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals-table">
        <tr><td class="text-right label">Subtotal proveedor</td><td class="text-right">{{ $data['totals']['currency'] }} {{ number_format($data['totals']['subtotal'], 2) }}</td></tr>
        <tr><td class="text-right label">Descuento</td><td class="text-right">{{ $data['totals']['currency'] }} {{ number_format($data['totals']['discount'], 2) }}</td></tr>
        <tr><td class="text-right label">Envio</td><td class="text-right">{{ $data['totals']['currency'] }} {{ number_format($data['totals']['shipping'], 2) }}</td></tr>
        <tr><td class="text-right label">Impuestos</td><td class="text-right">{{ $data['totals']['currency'] }} {{ number_format($data['totals']['taxes'], 2) }}</td></tr>
        <tr class="total-row"><td class="text-right">TOTAL</td><td class="text-right">{{ $data['totals']['currency'] }} {{ number_format($data['totals']['total'], 2) }}</td></tr>
    </table>

    @if($data['comments']['supplier'] || $data['consignee']['comment'] || $data['comments']['footer'])
        <div style="clear: both;"></div>
        <div class="notes-box">
            @if($data['comments']['supplier'])<div><strong>Proveedor:</strong> {{ $data['comments']['supplier'] }}</div>@endif
            @if($data['consignee']['comment'])<div><strong>Consignatario:</strong> {{ $data['consignee']['comment'] }}</div>@endif
            @if($data['comments']['footer'])<div><strong>Nota:</strong> {{ $data['comments']['footer'] }}</div>@endif
        </div>
    @endif
@endsection
