@php
    $companyName = $company['name'] ?? 'Quantum Soluciones S.A.';
    $companyPhone = $company['phone'] ?? '+505 7669 8164';
    $companyEmail = $company['email'] ?? 'ventas@quantumsolucionesnic.com';
    $companyWebsite = $company['website'] ?? 'quantumsolucionesnic.com';
    $companyAddress = $company['address'] ?? 'Rotonda Jean Paul Genie 100m al Este, Edificio Quantum, Managua';
    $companyRuc = $company['ruc'] ?? 'J0310000475024';
    $logo = $logoBase64 ?? null;

    $rawCurrency = strtoupper((string) ($currency ?? $request->currency ?? 'USD'));
    $currencyLabel = in_array($rawCurrency, ['NIO', 'C$', 'CORDOBA', 'CORDOBAS'], true) ? 'C$' : '$';
    $currencyName = $currencyLabel === 'C$' ? 'Córdobas (NIO)' : 'Dólares (USD)';
    $date = optional($request->date)->format('d/m/Y') ?: now()->format('d/m/Y');
    $validity = $request->validity_days ? (is_numeric($request->validity_days) ? $request->validity_days.' días' : $request->validity_days) : '15 días';
    $paymentTerms = $request->payment_terms ?: 'Contado';
    $deliveryTime = $request->delivery_time ?: 'Por confirmar';

    $entity = null;
    if ($request->clientMachine) {
        $entity = $request->clientMachine->entity;
    } elseif ($request->quote_to_ruc) {
        $entity = \App\Models\Entity::where('rfc_ruc', $request->quote_to_ruc)->first();
    } elseif ($request->quote_to_name) {
        $entity = \App\Models\Entity::where('name', $request->quote_to_name)->first();
    }

    $customerName = $request->quote_to_name ?: 'No especificado';
    $customerContact = $request->customer_contact_name ?: ($entity->contact_name ?? '');
    $customerRuc = $request->quote_to_ruc ?: ($entity->rfc_ruc ?? 'No especificado');
    $customerEmail = $request->quote_to_email ?: ($entity->email ?? '');
    $customerPhone = $entity->phone_mobile ?? ($entity->whatsapp ?? ($entity->phone_home ?? ''));
    $customerAddress = $request->consignee_address ?: ($entity->address ?? 'Managua, Nicaragua');
    $notes = trim((string) ($request->offer_notes ?? ''));
    $warrantyText = $warranty ?: 'Garantía según especificaciones del fabricante.';

    // Iconos vectoriales minimalistas
    $iconPin = '<svg width="9" height="9" viewBox="0 0 24 24" fill="#991B1B" style="vertical-align: -1px; margin-right: 4px;"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 110-5 2.5 2.5 0 010 5z"/></svg>';
    $iconMail = '<svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#991B1B" stroke-width="2" style="vertical-align: -1px; margin-right: 4px;"><rect x="3" y="5" width="18" height="14" rx="2"/><polyline points="3 7 12 13 21 7"/></svg>';
    $iconPhone = '<svg width="9" height="9" viewBox="0 0 24 24" fill="#991B1B" style="vertical-align: -1px; margin-right: 4px;"><path d="M6.62 10.79a15.05 15.05 0 006.59 6.59l2.2-2.2a1 1 0 011.02-.24c1.12.37 2.33.57 3.57.57a1 1 0 011 1V20a1 1 0 01-1 1A17 17 0 013 4a1 1 0 011-1h3.5a1 1 0 011 1c0 1.25.2 2.45.57 3.57a1 1 0 01-.25 1.02l-2.2 2.2z"/></svg>';
    $iconGlobe = '<svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#991B1B" stroke-width="2" style="vertical-align: -1px; margin-right: 4px;"><circle cx="12" cy="12" r="10"/><ellipse cx="12" cy="12" rx="4" ry="10"/><line x1="2" y1="12" x2="22" y2="12"/></svg>';
    $iconRuc = '<svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#991B1B" stroke-width="2" style="vertical-align: -1px; margin-right: 4px;"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Oferta Comercial Quantum {{ $request->reference }}</title>
    <style>
        @page {
            margin: 12mm 14mm 15mm 14mm;
            footer: html_pdf_footer;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8.5px;
            color: #1e293b;
            line-height: 1.4;
            margin: 0;
        }
        * { box-sizing: border-box; }
        .bold { font-weight: bold; }
        .right { text-align: right; }
        .center { text-align: center; }

        /* Encabezado */
        .top-header { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .top-header td { vertical-align: top; }
        .logo-box { width: 44%; }
        .company-contact-box { width: 56%; text-align: right; font-size: 8px; color: #475569; line-height: 1.5; }
        .company-name { font-size: 14px; font-weight: bold; color: #0f172a; letter-spacing: 0.5px; margin-bottom: 2px; }
        .company-slogan { font-size: 7.5px; font-weight: bold; color: #991B1B; letter-spacing: 0.8px; margin-bottom: 4px; text-transform: uppercase; }

        /* Barra de Título formal con espaciado */
        .doc-bar {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .doc-bar td {
            vertical-align: bottom;
            padding-bottom: 8px;
            border-bottom: 1.5px solid #991B1B;
        }
        .doc-title { font-size: 15px; font-weight: bold; color: #0f172a; letter-spacing: 0.5px; margin-bottom: 3px; }
        .doc-number { font-size: 9px; color: #64748b; font-weight: 500; }

        /* 2 Columnas de metadatos abiertas */
        .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .meta-table td { vertical-align: top; }
        .section-heading {
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #991B1B;
            margin-bottom: 6px;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 3px;
        }
        .client-name-highlight {
            font-size: 11px;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 4px;
        }
        .meta-line { margin-bottom: 3.5px; font-size: 8px; color: #475569; }
        .meta-label { color: #64748b; font-weight: bold; }

        /* Tabla de partidas */
        .items-table { width: 100%; border-collapse: collapse; font-size: 8px; margin-bottom: 16px; }
        .items-table thead { display: table-header-group; }
        .items-table th {
            background: #991B1B;
            color: #ffffff;
            font-size: 7.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 8px 6px;
            border: none;
        }
        .items-table td {
            padding: 8px 6px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
            color: #1e293b;
        }
        .items-table tbody tr:nth-child(even) td {
            background: #f8fafc;
        }
        .part-number { font-weight: bold; color: #991B1B; }
        .part-name { font-weight: bold; color: #0f172a; margin-bottom: 2px; }
        .part-desc { font-size: 7.2px; color: #64748b; line-height: 1.3; }

        /* Totales y Observaciones */
        .bottom-layout { width: 100%; border-collapse: collapse; margin-top: 4px; page-break-inside: avoid; }
        .bottom-layout td { vertical-align: top; }
        .payment-col { width: 55%; padding-right: 16px; }
        .totals-col { width: 45%; }

        .totals-table { width: 100%; border-collapse: collapse; font-size: 8.5px; }
        .totals-table td { padding: 4px 6px; }
        .total-row td {
            font-size: 12px;
            font-weight: bold;
            color: #991B1B;
            border-top: 1.5px solid #991B1B;
            border-bottom: 2px solid #991B1B;
            padding-top: 6px;
            padding-bottom: 6px;
        }

        /* Firma ejecutiva */
        .signature-line {
            border-top: 1px solid #991B1B;
            width: 170px;
            margin-bottom: 4px;
        }

        /* Footer */
        .minimal-footer {
            border-top: 0.8px solid #cbd5e1;
            padding-top: 6px;
            font-size: 7.5px;
            color: #64748b;
        }
        .minimal-footer table { width: 100%; border-collapse: collapse; font-size: 7.5px; color: #64748b; }
        .minimal-footer td { vertical-align: middle; }
    </style>
</head>
<body>

    {{-- FOOTER MINIMALISTA --}}
    <htmlpagefooter name="pdf_footer">
        <div class="minimal-footer">
            <table>
                <tr>
                    <td style="width: 45%;">
                        {!! $iconPhone !!} {{ $companyPhone }} &nbsp;|&nbsp; {!! $iconMail !!} {{ $companyEmail }}
                    </td>
                    <td style="width: 25%; text-align: center;">
                        RUC: {{ $companyRuc }}
                    </td>
                    <td style="width: 30%; text-align: right;">
                        Página {PAGENO} de {nbpg}
                    </td>
                </tr>
            </table>
        </div>
    </htmlpagefooter>
    <sethtmlpagefooter name="pdf_footer" value="on" />

    {{-- ENCABEZADO ABIERTO --}}
    <table class="top-header">
        <tr>
            <td class="logo-box">
                @if($logo)
                    <img src="{{ $logo }}" style="max-width: 145px; height: auto;" alt="Quantum Soluciones">
                @else
                    <div style="font-size: 18px; font-weight: bold; color: #0f172a;">{{ $companyName }}</div>
                    <div class="company-slogan">Repuestos - Servicio - Maquinaria</div>
                @endif
            </td>
            <td class="company-contact-box">
                <div class="company-name">{{ $companyName }}</div>
                <div class="company-slogan">REPUESTOS · SERVICIO · MAQUINARIA</div>
                <div class="meta-line">{!! $iconPin !!} {{ $companyAddress }}</div>
                <div class="meta-line">{!! $iconMail !!} {{ $companyEmail }}</div>
                <div class="meta-line">{!! $iconPhone !!} {{ $companyPhone }}</div>
                <div class="meta-line">{!! $iconRuc !!} RUC: {{ $companyRuc }} &nbsp;|&nbsp; {!! $iconGlobe !!} {{ $companyWebsite }}</div>
            </td>
        </tr>
    </table>

    {{-- BARRA DE TITULO FORMAL --}}
    <table class="doc-bar">
        <tr>
            <td style="width: 55%;">
                <div class="doc-title">OFERTA COMERCIAL</div>
                <div class="doc-number">No. Documento: <span class="bold" style="color: #991B1B;">{{ $request->reference }}</span></div>
            </td>
            <td style="width: 45%; text-align: right;">
                <div class="meta-label">Fecha de expedición:</div>
                <div class="bold" style="color: #0f172a; font-size: 8.8px;">{{ $date }}</div>
            </td>
        </tr>
    </table>

    {{-- INFORMACION CLIENTE Y CONDICIONES (NOMBRES FORMALES) --}}
    <table class="meta-table">
        <tr>
            <td style="width: 52%; padding-right: 14px;">
                <div class="section-heading">DATOS DEL CLIENTE / FACTURAR A</div>
                <div class="client-name-highlight">{{ $customerName }}</div>
                <div class="meta-line">{!! $iconPin !!} <span class="meta-label">Dirección:</span> {{ $customerAddress }}</div>
                @if($customerContact)
                    <div class="meta-line"><span class="meta-label">Atención / Contacto:</span> {{ $customerContact }}</div>
                @endif
                @if($customerEmail)
                    <div class="meta-line">{!! $iconMail !!} <span class="meta-label">Correo electrónico:</span> {{ $customerEmail }}</div>
                @endif
                @if($customerPhone)
                    <div class="meta-line">{!! $iconPhone !!} <span class="meta-label">Teléfono:</span> {{ $customerPhone }}</div>
                @endif
                <div class="meta-line">{!! $iconRuc !!} <span class="meta-label">No. RUC / Cédula:</span> {{ $customerRuc }}</div>
            </td>
            <td style="width: 48%; padding-left: 14px;">
                <div class="section-heading">CONDICIONES COMERCIALES DE LA OFERTA</div>
                <div class="meta-line"><span class="meta-label">Validez de la oferta:</span> <span class="bold" style="color: #0f172a;">{{ $validity }}</span></div>
                <div class="meta-line"><span class="meta-label">Condición de pago:</span> <span class="bold" style="color: #991B1B;">{{ $paymentTerms }}</span></div>
                <div class="meta-line"><span class="meta-label">Tiempo de entrega estimado:</span> <span class="bold" style="color: #0f172a;">{{ $deliveryTime }}</span></div>
                <div class="meta-line"><span class="meta-label">Garantía:</span> {{ $warrantyText }}</div>
                <div class="meta-line"><span class="meta-label">Moneda de facturación:</span> {{ $currencyName }}</div>
            </td>
        </tr>
    </table>

    {{-- TABLA DE PARTIDAS CON HEADER ROJO QUANTUM Y NOMBRES FORMALES --}}
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%;" class="center">Ítem</th>
                <th style="width: 18%;">Código / Referencia</th>
                <th style="width: 41%;">Descripción del Producto / Servicio</th>
                <th style="width: 8%;" class="center">Cant.</th>
                <th style="width: 13%;" class="right">Precio Unitario</th>
                <th style="width: 15%;" class="right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($mappedItems as $index => $item)
                <tr>
                    <td class="center bold" style="color: #64748b;">{{ $index + 1 }}</td>
                    <td class="part-number">{{ $item['part_number'] }}</td>
                    <td>
                        <div class="part-name">{{ $item['part_name'] }}</div>
                        @if(($item['description'] ?? '-') !== '-' && ($item['description'] ?? '') !== ($item['part_name'] ?? ''))
                            <div class="part-desc">{{ $item['description'] }}</div>
                        @endif
                        @if(($item['brand'] ?? '-') !== '-')
                            <div style="font-size: 7px; color: #991B1B; font-weight: bold; margin-top: 1px;">Marca: {{ $item['brand'] }}</div>
                        @endif
                    </td>
                    <td class="center bold">{{ number_format((float) ($item['quantity'] ?? 1), 0) }}</td>
                    <td class="right font-mono">{{ $currencyLabel }} {{ number_format((float) ($item['unit_price'] ?? 0), 2) }}</td>
                    <td class="right bold font-mono">{{ $currencyLabel }} {{ number_format((float) ($item['line_total'] ?? 0), 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- CONDICIONES Y TOTALES --}}
    <table class="bottom-layout">
        <tr>
            <td class="payment-col">
                <div class="section-heading">OBSERVACIONES Y CONDICIONES COMERCIALES</div>
                @if($notes)
                    <div class="meta-line" style="color: #334155;">{{ $notes }}</div>
                @else
                    <div class="meta-line" style="color: #334155;">Precios sujetos a confirmación de existencias. Suministro garantizado bajo especificaciones del fabricante.</div>
                @endif
                <div class="meta-line" style="margin-top: 6px; font-size: 7.2px; color: #64748b;">
                    Emitir cheque o transferencia a la orden de: <span class="bold" style="color: #0f172a;">Quantum Soluciones S.A.</span>
                </div>
            </td>
            <td class="totals-col">
                <table class="totals-table">
                    <tr>
                        <td style="color: #475569;" class="bold">SUBTOTAL NETO:</td>
                        <td class="right bold font-mono">{{ $currencyLabel }} {{ number_format($subtotal, 2) }}</td>
                    </tr>
                    @if($taxPercent > 0)
                        <tr>
                            <td style="color: #475569;">I.V.A. ({{ number_format($taxPercent, 0) }}%):</td>
                            <td class="right font-mono">{{ $currencyLabel }} {{ number_format($iva, 2) }}</td>
                        </tr>
                    @endif
                    <tr class="total-row">
                        <td>TOTAL GENERAL:</td>
                        <td class="right font-mono">{{ $currencyLabel }} {{ number_format($total, 2) }}</td>
                    </tr>
                </table>

                {{-- Línea de firma ejecutiva formal --}}
                <div style="margin-top: 26px; text-align: right;">
                    <div class="signature-line" style="margin-left: auto;"></div>
                    <div class="bold" style="font-size: 8.5px; color: #991B1B;">Firma Autorizada</div>
                    <div style="font-size: 7.2px; color: #64748b;">Quantum Soluciones S.A. · Dpto. Comercial</div>
                </div>
            </td>
        </tr>
    </table>

</body>
</html>
