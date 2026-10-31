@php
    $logo = $logoBase64 ?? null;
    $rawCurrency = strtoupper((string) ($currency ?? $request->currency ?? 'NIO'));
    $currencyLabel = in_array($rawCurrency, ['NIO', 'C$', 'CORDOBA', 'CORDOBAS'], true) ? 'C$' : '$';
    $currencyText = $currencyLabel === 'C$' ? 'Córdobas (NIO)' : 'Dólares (USD)';
    
    $rawDate = optional($request->date) ?: now();
    $dateFormatted = $rawDate->format('d/m/Y');

    $validity = $request->validity_days ? (is_numeric($request->validity_days) ? $request->validity_days.' días' : $request->validity_days) : '15 días';
    $paymentTerms = $request->payment_terms ?: 'Pago de contado';
    $deliveryTime = $request->delivery_time ?: '7-10 Días';

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

    // SVGs con el estilo exacto de los iconos vectoriales
    $iconPin = '<svg width="9" height="9" viewBox="0 0 24 24" fill="#2D472C" style="vertical-align: -1px; margin-right: 4px;"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 110-5 2.5 2.5 0 010 5z"/></svg>';
    $iconMail = '<svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#2D472C" stroke-width="2" style="vertical-align: -1px; margin-right: 4px;"><rect x="3" y="5" width="18" height="14" rx="2"/><polyline points="3 7 12 13 21 7"/></svg>';
    $iconPhone = '<svg width="9" height="9" viewBox="0 0 24 24" fill="#2D472C" style="vertical-align: -1px; margin-right: 4px;"><path d="M6.62 10.79a15.05 15.05 0 006.59 6.59l2.2-2.2a1 1 0 011.02-.24c1.12.37 2.33.57 3.57.57a1 1 0 011 1V20a1 1 0 01-1 1A17 17 0 013 4a1 1 0 011-1h3.5a1 1 0 011 1c0 1.25.2 2.45.57 3.57a1 1 0 01-.25 1.02l-2.2 2.2z"/></svg>';
    $iconRuc = '<svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#2D472C" stroke-width="2" style="vertical-align: -1px; margin-right: 4px;"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cotización Comercial Marcos Cecilio Leiva {{ $request->reference }}</title>
    <style>
        @page {
            margin: 12mm 14mm 15mm 14mm;
            footer: html_marcos_footer;
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
        .brand-col { width: 55%; }
        .contact-col { width: 45%; text-align: right; font-size: 8px; color: #475569; line-height: 1.5; }

        .brand-name {
            font-size: 16px;
            font-weight: bold;
            color: #2D472C;
            letter-spacing: 0.3px;
            line-height: 1.15;
            margin: 0 0 3px;
        }
        .brand-subtitle {
            font-size: 7.8px;
            color: #2D472C;
            font-weight: bold;
            letter-spacing: 0.6px;
            text-transform: uppercase;
        }

        /* Barra de Título formal con espaciado */
        .doc-bar {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .doc-bar td {
            vertical-align: bottom;
            padding-bottom: 8px;
            border-bottom: 1.5px solid #2D472C;
        }
        .doc-title { font-size: 15px; font-weight: bold; color: #2D472C; letter-spacing: 0.5px; margin-bottom: 3px; }
        .doc-number { font-size: 9px; color: #64748b; font-weight: 500; }

        /* 2 Columnas de metadatos abiertas */
        .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .meta-table td { vertical-align: top; }
        .section-heading {
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #2D472C;
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
            background: #2D472C;
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
        .part-number { font-weight: bold; color: #2D472C; }
        .part-name { font-weight: bold; color: #0f172a; margin-bottom: 2px; }
        .part-desc { font-size: 7.2px; color: #64748b; line-height: 1.3; }

        /* Totales y Datos Bancarios */
        .bottom-layout { width: 100%; border-collapse: collapse; margin-top: 4px; page-break-inside: avoid; }
        .bottom-layout td { vertical-align: top; }
        .payment-col { width: 55%; padding-right: 16px; }
        .totals-col { width: 45%; }

        .totals-table { width: 100%; border-collapse: collapse; font-size: 8.5px; }
        .totals-table td { padding: 4px 6px; }
        .total-row td {
            font-size: 12px;
            font-weight: bold;
            color: #2D472C;
            border-top: 1.5px solid #2D472C;
            border-bottom: 2px solid #2D472C;
            padding-top: 6px;
            padding-bottom: 6px;
        }

        /* Firma ejecutiva */
        .signature-line {
            border-top: 1px solid #2D472C;
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
    <htmlpagefooter name="marcos_footer">
        <div class="minimal-footer">
            <table>
                <tr>
                    <td style="width: 45%;">
                        {!! $iconPhone !!} +505 77955637 &nbsp;|&nbsp; {!! $iconMail !!} 73marcosleiva@gmail.com
                    </td>
                    <td style="width: 25%; text-align: center;">
                        RUC: 0052211730002M
                    </td>
                    <td style="width: 30%; text-align: right;">
                        Página {PAGENO} de {nbpg}
                    </td>
                </tr>
            </table>
        </div>
    </htmlpagefooter>
    <sethtmlpagefooter name="marcos_footer" value="on" />

    {{-- ENCABEZADO ABIERTO --}}
    <table class="top-header">
        <tr>
            <td class="brand-col">
                <div class="brand-name">Marcos Cecilio Leiva Ordoñez</div>
                <div class="brand-subtitle">Repuestos y suministros para mantenimiento</div>
            </td>
            <td class="contact-col">
                <div class="meta-line">{!! $iconPin !!} Donde fue el bar Canducha 1c al sur, Managua</div>
                <div class="meta-line">{!! $iconMail !!} 73marcosleiva@gmail.com</div>
                <div class="meta-line">{!! $iconPhone !!} +505 77955637</div>
                <div class="meta-line">{!! $iconRuc !!} RUC: 0052211730002M</div>
            </td>
        </tr>
    </table>

    {{-- BARRA DE TITULO FORMAL --}}
    <table class="doc-bar">
        <tr>
            <td style="width: 55%;">
                <div class="doc-title">COTIZACIÓN COMERCIAL</div>
                <div class="doc-number">No. Documento: <span class="bold" style="color: #2D472C;">{{ $request->reference }}</span></div>
            </td>
            <td style="width: 45%; text-align: right;">
                <div class="meta-label">Fecha de expedición:</div>
                <div class="bold" style="color: #0f172a; font-size: 8.8px;">{{ $dateFormatted }}</div>
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
                <div class="meta-line"><span class="meta-label">Condición de pago:</span> <span class="bold" style="color: #2D472C;">{{ $paymentTerms }}</span></div>
                <div class="meta-line"><span class="meta-label">Tiempo de entrega estimado:</span> <span class="bold" style="color: #0f172a;">{{ $deliveryTime }}</span></div>
                <div class="meta-line"><span class="meta-label">Término de entrega:</span> DDP (Puesto en instalaciones del cliente)</div>
                <div class="meta-line"><span class="meta-label">Moneda de facturación:</span> {{ $currencyText }}</div>
            </td>
        </tr>
    </table>

    {{-- TABLA DE PARTIDAS CON HEADER VERDE BOSQUE Y NOMBRES FORMALES --}}
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
                <div class="section-heading">INFORMACIÓN DE PAGO Y TRANSFERENCIA BANCARIA</div>
                <div class="meta-line">
                    <span class="meta-label">Emitir cheque o transferencia a la orden de:</span><br>
                    <span style="font-size: 9.5px; font-weight: bold; color: #2D472C;">"MARCOS CECILIO LEIVA ORDOÑEZ"</span>
                </div>
                <div class="meta-line" style="margin-top: 5px;">
                    <span class="meta-label">Cuenta Bancaria BAC Córdobas (NIO):</span> <span class="bold" style="color: #2D472C; font-family: monospace;">374120822</span><br>
                    <span style="font-size: 7.5px; color: #64748b;">Titular de la cuenta: MARCOS CECILIO LEIVA ORDOÑEZ</span>
                </div>
                <div class="meta-line" style="margin-top: 6px; font-size: 7.2px; color: #64748b; font-style: italic;">
                    * Precios en condición DDP nacionalizados entregados en oficinas o instalaciones del cliente.
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
                    <div class="bold" style="font-size: 8.5px; color: #2D472C;">Firma Autorizada</div>
                    <div style="font-size: 7.2px; color: #64748b;">Marcos Cecilio Leiva Ordoñez · Repuestos</div>
                </div>
            </td>
        </tr>
    </table>

</body>
</html>
