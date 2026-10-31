@extends('pdf.layouts.base')

@php
    $formatQuantity = function ($quantity) {
        return number_format((float) $quantity, 0);
    };
@endphp

@section('title', 'Cotización ' . $request->reference)
@section('doc_type', 'COTIZACIÓN')

@section('header_meta')
    <tr>
        <td class="meta-label">Cotización N°</td>
        <td class="meta-value">{{ $request->reference }}</td>
    </tr>
    <tr>
        <td class="meta-label">Fecha</td>
        <td class="meta-value">{{ optional($request->date)->format('d/m/Y') ?? now()->format('d/m/Y') }}</td>
    </tr>
    <tr>
        <td class="meta-label">Validez</td>
        <td class="meta-value">15 Días</td>
    </tr>
    <tr>
        <td class="meta-label">Pago</td>
        <td class="meta-value">Contado</td>
    </tr>
@endsection

@section('content')
    @if(!empty($isPreview))
        <div style="margin-bottom: 10px; border: 1px dashed #b91c1c; background: #fff5f5; padding: 8px 10px; text-align: center; font-size: 12px; font-weight: bold; color: #991b1b; letter-spacing: 1px;">
            BORRADOR / VISTA PREVIA
        </div>
    @endif

    <div class="section-title">Información del cliente</div>

    @php
        $entity = null;
        if ($request->clientMachine) {
            $entity = $request->clientMachine->entity;
        } elseif ($request->quote_to_ruc) {
            $entity = \App\Models\Entity::where('rfc_ruc', $request->quote_to_ruc)->first();
        } elseif ($request->quote_to_name) {
            $entity = \App\Models\Entity::where('name', $request->quote_to_name)->first();
        }
        $customerPhone = $entity->phone_mobile ?? ($entity->whatsapp ?? ($entity->phone_home ?? 'No especificado'));
        $customerContact = $request->customer_contact_name ?: ($entity->contact_name ?? 'No especificado');
        $customerEmail = $request->quote_to_email ?: ($entity->email ?? 'No especificado');
        $customerAddress = $request->consignee_address ?: ($entity->address ?? 'No especificada');
    @endphp

    <table class="info-block">
        <tr>
            <td class="info-label">Cliente:</td>
            <td style="font-weight: bold; color: #222;">{{ $request->quote_to_name }}</td>
            <td class="info-label">Teléfono:</td>
            <td>{{ $customerPhone }}</td>
        </tr>
        <tr>
            <td class="info-label">Contacto:</td>
            <td>{{ $customerContact }}</td>
            <td class="info-label">Email:</td>
            <td>{{ $customerEmail }}</td>
        </tr>
        <tr>
            <td class="info-label">Dirección:</td>
            <td colspan="3">{{ $customerAddress }}</td>
        </tr>
    </table>

    <p style="margin-bottom: 10px; font-size: 11px;">Es un placer extenderle nuestra cotización.</p>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%; text-align: center;">N°</th>
                <th style="width: 15%;">Referencia</th>
                <th style="width: 45%;">Descripción</th>
                <th style="width: 10%; text-align: center;">Cantidad</th>
                <th style="width: 12%; text-align: right;">Precio Unit.</th>
                <th style="width: 13%; text-align: right;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($mappedItems as $index => $item)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td style="font-weight: bold;">{{ $item['part_number'] }}</td>
                    <td>
                        {{ $item['part_name'] }}
                        @if(($item['description'] ?? '-') !== '-' && ($item['description'] ?? '') !== ($item['part_name'] ?? ''))
                            <br><span style="font-size: 9px; color: #666;">{{ $item['description'] }}</span>
                        @endif
                        @if($item['brand'] !== '-' || $item['model'] !== '-' || ($item['serial_number'] ?? '-') !== '-')
                            <br><span style="font-size: 9px; color: #666;">
                                @if($item['brand'] !== '-') Marca: {{ $item['brand'] }} @endif
                                @if($item['model'] !== '-') Modelo: {{ $item['model'] }} @endif
                                @if($item['model'] === '-' && ($item['serial_number'] ?? '-') !== '-') Serie: {{ $item['serial_number'] }} @endif
                            </span>
                        @endif
                    </td>
                    <td class="text-center">{{ $formatQuantity($item['quantity']) }}</td>
                    <td class="text-right">{{ $currency }} {{ number_format($item['unit_price'], 2) }}</td>
                    <td class="text-right">{{ $currency }} {{ number_format($item['line_total'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="clearfix">
        <table class="totals-table">
            <tr>
                <td class="totals-label">Subtotal:</td>
                <td class="totals-value">{{ $currency }} {{ number_format($subtotal, 2) }}</td>
            </tr>
            <tr>
                <td class="totals-label">IVA ({{ number_format($taxPercent ?? 15, 2) }}%):</td>
                <td class="totals-value">{{ $currency }} {{ number_format($iva, 2) }}</td>
            </tr>
            <tr class="total-row">
                <td class="totals-label">TOTAL:</td>
                <td class="totals-value">{{ $currency }} {{ number_format($total, 2) }}</td>
            </tr>
        </table>
    </div>

    <div style="clear: both;"></div>

    <div class="notes-box">
        <strong>INFORMACIÓN ADICIONAL</strong><br>
        <table style="width: 100%; margin-top: 5px; border: none;">
            <tr>
                <td style="width: 120px; font-weight: bold;">Moneda:</td>
                <td>{{ $currency === 'C$' ? 'Córdobas (NIO)' : 'Dólares (USD)' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Tasa de Cambio:</td>
                <td>{{ $exchangeRate }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Garantía:</td>
                <td>{{ $warranty ?? 'Según especificaciones del fabricante' }}</td>
            </tr>
            @if($request->notes)
            <tr>
                <td style="font-weight: bold; vertical-align: top;">Observaciones:</td>
                <td>{{ $request->notes }}</td>
            </tr>
            @endif
        </table>
    </div>
@endsection
