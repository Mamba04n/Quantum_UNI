@extends('pdf.layouts.base-compact')

@section('title', 'RFQ ' . $request->reference)
@section('doc_type', 'SOLICITUD DE cotización (RFQ)')

@php
    $responseRequirements = $documentData['response_requirements']
        ?? $rfqDocument?->effective_response_requirements
        ?? \App\Models\SupplierRfqDocument::DEFAULT_RESPONSE_REQUIREMENTS;
    $hasSupplier = filled($supplier?->id);
@endphp

@push('styles')
    <style>
        @page { margin: 20px 28px 142px 28px; }
        .header-table { margin-bottom: 8px; }
        .document-logo-fixed { top: 78px; right: 82px; width: 190px; z-index: 0; }
        .header-left { width: 64%; text-align: left; }
        .header-right { width: 32%; text-align: right; }
        .document-title { font-size: 21px; margin: 8px 0 96px 0; text-align: left; }
        .rfq-intro { margin: 0 0 7px 0; font-size: 8.5px; }
        .items-table { margin-top: 4px; }
        .items-table th {
            font-size: 8px;
            padding: 5px 4px;
        }
        .items-table td {
            font-size: 8.8px;
            padding: 5px 4px;
            line-height: 1.35;
        }
        .items-table .rfq-info-row td {
            background: #ffffff;
            border: 1px solid #d7dce4;
            color: #1f2937;
            font-size: 8px;
            padding: 6px 8px;
            line-height: 1.45;
        }
        .items-table .rfq-info-row .rfq-info-label {
            color: #64748b;
            display: block;
            font-size: 6.7px;
            font-weight: bold;
            letter-spacing: .35px;
            margin-bottom: 3px;
            text-transform: uppercase;
        }
        .items-table .rfq-info-row .rfq-info-value {
            display: block;
            font-weight: bold;
            line-height: 1.35;
        }
        .items-table .rfq-info-section td {
            background: #f8fafc;
            border: 1px solid #d7dce4;
            color: #991b1b;
            font-size: 7px;
            font-weight: bold;
            letter-spacing: .8px;
            padding: 4px 6px;
            text-transform: uppercase;
        }
        .rfq-requirements {
            background: #fafafa;
            border: 1px solid #d7dce4;
            border-left: 4px solid #991b1b;
            bottom: 18px;
            font-size: 7.8px;
            left: 0;
            line-height: 1.35;
            padding: 7px 8px;
            position: fixed;
            right: 0;
        }
        .rfq-requirements-title {
            color: #1f2937;
            display: block;
            font-size: 7.6px;
            font-weight: bold;
            letter-spacing: .5px;
            margin-bottom: 3px;
            text-transform: uppercase;
        }
    </style>
@endpush

@section('header_left')
    <div class="slogan">{{ $company['slogan'] ?? 'Repuestos - Servicio - Maquinaria' }}</div>
    <div class="document-title">@yield('doc_type', 'DOCUMENTO')</div>
@endsection

@section('header_right')
    <span style="font-size: 1px; color: #ffffff;">.</span>
@endsection

@section('header_meta')
    <tr><td class="meta-label">RFQ No.</td><td class="meta-value">{{ $rfqId }}</td></tr>
    <tr><td class="meta-label">Fecha</td><td class="meta-value">{{ $documentData['date'] ?? now()->format('d/m/Y') }}</td></tr>
    <tr><td class="meta-label">Ref. interna</td><td class="meta-value">{{ $request->reference }}</td></tr>
@endsection

@section('content')
    <p class="rfq-intro">
        Estimados señores, solicitamos cotización de las siguientes partidas. Este RFQ no incluye precios ni condiciones finales de venta.
    </p>
    @if(filled($rfqDocument?->rfq_specifications))
        <p class="rfq-intro"><strong>Especificaciones:</strong> {{ $rfqDocument->rfq_specifications }}</p>
    @endif

    <table class="items-table">
        <tbody>
            <tr class="rfq-info-section"><td colspan="6">Datos del RFQ</td></tr>
            <tr class="rfq-info-row">
                <td colspan="2"><span class="rfq-info-label">RFQ No.</span><span class="rfq-info-value">{{ $rfqId }}</span></td>
                <td colspan="2"><span class="rfq-info-label">Fecha</span><span class="rfq-info-value">{{ $documentData['date'] ?? now()->format('d/m/Y') }}</span></td>
                <td colspan="2"><span class="rfq-info-label">Referencia interna</span><span class="rfq-info-value">{{ $request->reference }}</span></td>
            </tr>
            <tr class="rfq-info-section"><td colspan="6">Proveedor y consignatario</td></tr>
            <tr class="rfq-info-row">
                @if($hasSupplier)
                    <td colspan="2"><span class="rfq-info-label">Proveedor</span><span class="rfq-info-value">{{ $supplier->name }}</span></td>
                    <td colspan="2"><span class="rfq-info-label">Atencion</span><span class="rfq-info-value">{{ $supplier->contact_name ?: '-' }}</span></td>
                    <td colspan="2"><span class="rfq-info-label">Email</span><span class="rfq-info-value">{{ $supplier->email ?: '-' }}</span></td>
                </tr>
                <tr class="rfq-info-row">
                    <td colspan="2"><span class="rfq-info-label">Telefono</span><span class="rfq-info-value">{{ $supplier->phone_mobile ?? $supplier->phone_home ?? $supplier->phone ?? '-' }}</span></td>
                    <td colspan="4"><span class="rfq-info-label">Estado</span><span class="rfq-info-value">Proveedor definido para este RFQ</span></td>
                @else
                    <td colspan="6"><span class="rfq-info-label">Proveedor</span><span class="rfq-info-value">A quien corresponda - RFQ abierto, proveedor por confirmar.</span></td>
                @endif
            </tr>
            <tr class="rfq-info-row">
                <td colspan="2"><span class="rfq-info-label">Cotizar a nombre de</span><span class="rfq-info-value">{{ $rfqDocument?->quote_to_name ?: 'Admin' }}</span></td>
                <td colspan="2"><span class="rfq-info-label">Consignatario</span><span class="rfq-info-value">{{ $rfqDocument?->consignee_name ?: '-' }}</span></td>
                <td colspan="2"><span class="rfq-info-label">Contacto consignee</span><span class="rfq-info-value">{{ $rfqDocument?->consignee_phone ?: '-' }}</span></td>
            </tr>
            <tr class="rfq-info-row">
                <td colspan="4"><span class="rfq-info-label">Direccion consignatario</span><span class="rfq-info-value">{{ trim(($rfqDocument?->consignee_address_1 ?: '').' '.($rfqDocument?->consignee_address_2 ?: '')) ?: '-' }}</span></td>
                <td colspan="2"><span class="rfq-info-label">Ciudad / pais</span><span class="rfq-info-value">{{ trim(($rfqDocument?->consignee_city ?: '-').' / '.($rfqDocument?->consignee_country ?: '-')) }}</span></td>
            </tr>
            @if($rfqDocument?->consignee_emails || $rfqDocument?->consignee_comment || $rfqDocument?->consignee_urgent_comment)
                <tr class="rfq-info-row">
                    <td colspan="3"><span class="rfq-info-label">Correos consignatario</span><span class="rfq-info-value">{{ $rfqDocument?->consignee_emails ?: '-' }}</span></td>
                    <td colspan="3"><span class="rfq-info-label">Comentario</span><span class="rfq-info-value">{{ $rfqDocument?->consignee_urgent_comment ?: ($rfqDocument?->consignee_comment ?: '-') }}</span></td>
                </tr>
            @endif
        </tbody>
        <thead>
            <tr>
                <th style="width: 5%; text-align: center;">No.</th>
                <th style="width: 29%;">Referencia / numero de parte / serie</th>
                <th style="width: 30%;">Descripcion</th>
                <th style="width: 13%;">Marca</th>
                <th style="width: 8%; text-align: center;">Cant.</th>
                <th style="width: 15%;">Modelo</th>
            </tr>
        </thead>
        <tbody>
            @foreach($mappedItems as $index => $item)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="font-bold">{{ $item['reference'] }}</td>
                    <td>
                        {{ $item['part_name'] }}
                        @if(filled($item['specifications'] ?? null) && ($item['specifications'] ?? '-') !== '-')
                            <br><span style="font-size: 7.5px; color: #64748b;"><strong>Especificaciones:</strong> {{ $item['specifications'] }}</span>
                        @endif
                    </td>
                    <td>{{ $item['brand'] }}</td>
                    <td class="text-center font-bold">{{ number_format($item['quantity'], 0) }}</td>
                    <td>{{ $item['model'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="rfq-requirements">
        <span class="rfq-requirements-title">SE SOLICITA INCLUIR EN SU RESPUESTA</span>
        <div class="preserve-lines">{{ $responseRequirements }}</div>
    </div>
@endsection
