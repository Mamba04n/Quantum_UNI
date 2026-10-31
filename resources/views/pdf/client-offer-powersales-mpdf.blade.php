@php
    $logo = $logoBase64 ?? null;
    $rawCurrency = strtoupper((string) ($currency ?? $request->currency ?? 'NIO'));
    $currencyLabel = in_array($rawCurrency, ['NIO', 'C$', 'CORDOBA', 'CORDOBAS'], true) ? 'C$' : '$';
    $currencyText = $currencyLabel === 'C$' ? 'Córdobas (NIO)' : 'Dólares (USD)';
    
    $rawDate = optional($request->date) ?: now();
    $meses = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];
    $dateSpanish = $rawDate->format('d').' de '.($meses[(int)$rawDate->format('m')] ?? $rawDate->format('F')).' del '.$rawDate->format('Y');

    $validity = $request->validity_days ? (is_numeric($request->validity_days) ? $request->validity_days.' días' : $request->validity_days) : '15 días';
    $paymentTerms = $request->payment_terms ?: 'Contado';
    $deliveryTime = $request->delivery_time ?: '10 Días';

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

    // SVGs con estilo vectorial elegante
    $iconPin = '<svg width="9" height="9" viewBox="0 0 24 24" fill="#0B2545" style="vertical-align: -1px; margin-right: 4px;"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 110-5 2.5 2.5 0 010 5z"/></svg>';
    $iconMail = '<svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#0B2545" stroke-width="2" style="vertical-align: -1px; margin-right: 4px;"><rect x="3" y="5" width="18" height="14" rx="2"/><polyline points="3 7 12 13 21 7"/></svg>';
    $iconPhone = '<svg width="9" height="9" viewBox="0 0 24 24" fill="#0B2545" style="vertical-align: -1px; margin-right: 4px;"><path d="M6.62 10.79a15.05 15.05 0 006.59 6.59l2.2-2.2a1 1 0 011.02-.24c1.12.37 2.33.57 3.57.57a1 1 0 011 1V20a1 1 0 01-1 1A17 17 0 013 4a1 1 0 011-1h3.5a1 1 0 011 1c0 1.25.2 2.45.57 3.57a1 1 0 01-.25 1.02l-2.2 2.2z"/></svg>';
    $iconGlobe = '<svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#0B2545" stroke-width="2" style="vertical-align: -1px; margin-right: 4px;"><circle cx="12" cy="12" r="10"/><ellipse cx="12" cy="12" rx="4" ry="10"/><line x1="2" y1="12" x2="22" y2="12"/></svg>';
    $iconRuc = '<svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#0B2545" stroke-width="2" style="vertical-align: -1px; margin-right: 4px;"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cotización Comercial Power Sales {{ $request->reference }}</title>
    <style>
        @page {
            margin: 12mm 14mm 15mm 14mm;
            footer: html_powersales_footer;
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
        .company-name { font-size: 14px; font-weight: bold; color: #0B2545; letter-spacing: 0.5px; margin-bottom: 2px; }
        .company-slogan { font-size: 7.5px; font-weight: bold; color: #0284c7; letter-spacing: 0.8px; margin-bottom: 4px; text-transform: uppercase; }

        /* Barra de Título y Número con espaciado formal */
        .doc-bar {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .doc-bar td {
            vertical-align: bottom;
            padding-bottom: 8px;
            border-bottom: 1.5px solid #0B2545;
        }
        .doc-title { font-size: 15px; font-weight: bold; color: #0B2545; letter-spacing: 0.5px; margin-bottom: 3px; }
        .doc-number { font-size: 9px; color: #64748b; font-weight: 500; }

        /* 2 Columnas de metadatos abiertas */
        .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .meta-table td { vertical-align: top; }
        .section-heading {
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #0B2545;
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

        /* Tabla de partidas formal */
        .items-table { width: 100%; border-collapse: collapse; font-size: 8px; margin-bottom: 16px; }
        .items-table thead { display: table-header-group; }
        .items-table th {
            background: #0B2545;
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
        .part-number { font-weight: bold; color: #0B2545; }
        .part-name { font-weight: bold; color: #0f172a; margin-bottom: 2px; }
        .part-desc { font-size: 7.2px; color: #64748b; line-height: 1.3; }

        /* Zona inferior: Pago/Bancos y Totales */
        .bottom-layout { width: 100%; border-collapse: collapse; margin-top: 4px; page-break-inside: avoid; }
        .bottom-layout td { vertical-align: top; }
        .payment-col { width: 55%; padding-right: 16px; }
        .totals-col { width: 45%; }

        .totals-table { width: 100%; border-collapse: collapse; font-size: 8.5px; }
        .totals-table td { padding: 4px 6px; }
        .total-row td {
            font-size: 12px;
            font-weight: bold;
            color: #0B2545;
            border-top: 1.5px solid #0B2545;
            border-bottom: 2px solid #0B2545;
            padding-top: 6px;
            padding-bottom: 6px;
        }

        /* Línea de firma ejecutiva */
        .signature-line {
            border-top: 1px solid #0B2545;
            width: 170px;
            margin-bottom: 4px;
        }

        /* Pie de página corporativo */
        .minimal-footer {
            border-top: 0.8px solid #cbd5e1;
            padding-top: 6px;
            font-size: 7.5px;
            color: #64748b;
        }
        .minimal-footer table { width: 100%; border-collapse: collapse; font-size: 7.5px; color: #64748b; }
        .minimal-footer td { vertical-align: middle; }

        .page-break { page-break-before: always; }
        .terms-title { font-size: 13px; font-weight: bold; color: #0B2545; margin: 10px 0 12px; border-bottom: 1px solid #cbd5e1; padding-bottom: 4px; }
        .terms-sub { font-size: 8.5px; font-weight: bold; color: #0B2545; margin-top: 8px; margin-bottom: 2px; }
        .terms-p { font-size: 7.5px; color: #475569; line-height: 1.4; text-align: justify; margin-bottom: 6px; }

        .brands-grid { width: 100%; border-collapse: separate; border-spacing: 8px; margin-top: 16px; }
        .brand-pill {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 12px 6px;
            text-align: center;
            font-size: 10px;
            font-weight: bold;
            color: #1e293b;
        }
    </style>
</head>
<body>

    {{-- FOOTER MINIMALISTA --}}
    <htmlpagefooter name="powersales_footer">
        <div class="minimal-footer">
            <table>
                <tr>
                    <td style="width: 35%;">
                        {!! $iconPhone !!} +505 8456-7408 &nbsp;|&nbsp; {!! $iconMail !!} Powersales.products@outlook.com
                    </td>
                    <td style="width: 35%; text-align: center;">
                        {!! $iconGlobe !!} Powersalesnic.com
                    </td>
                    <td style="width: 30%; text-align: right;">
                        Página {PAGENO} de {nbpg}
                    </td>
                </tr>
            </table>
        </div>
    </htmlpagefooter>
    <sethtmlpagefooter name="powersales_footer" value="on" />

    {{-- ENCABEZADO ABIERTO --}}
    <table class="top-header">
        <tr>
            <td class="logo-box">
                @if($logo)
                    <img src="{{ $logo }}" style="max-width: 140px; height: auto;" alt="Power Sales">
                @else
                    <div style="font-size: 18px; font-weight: bold; color: #0B2545;">POWERSALES</div>
                    <div class="company-slogan">The Best Option</div>
                @endif
            </td>
            <td class="company-contact-box">
                <div class="company-name">POWER SALES</div>
                <div class="company-slogan">THE BEST OPTION</div>
                <div class="meta-line">{!! $iconPin !!} Colonia Centroamérica, de los Semáforos 1c al N, 1c al E, Managua</div>
                <div class="meta-line">{!! $iconMail !!} Ventas02@powersalesnic.com</div>
                <div class="meta-line">{!! $iconPhone !!} +505 8326-2783 &nbsp;·&nbsp; +505 8456-7408</div>
                <div class="meta-line">{!! $iconRuc !!} RUC: 2831402011000W &nbsp;|&nbsp; {!! $iconGlobe !!} Powersalesnic.com</div>
            </td>
        </tr>
    </table>

    {{-- BARRA DE TITULO FORMAL --}}
    <table class="doc-bar">
        <tr>
            <td style="width: 55%;">
                <div class="doc-title">COTIZACIÓN COMERCIAL</div>
                <div class="doc-number">No. Documento: <span class="bold" style="color: #0B2545;">{{ $request->reference }}</span></div>
            </td>
            <td style="width: 45%; text-align: right;">
                <div class="meta-label">Fecha de expedición:</div>
                <div class="bold" style="color: #0f172a; font-size: 8.8px;">{{ $dateSpanish }}</div>
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
                <div class="meta-line"><span class="meta-label">Condición de pago:</span> <span class="bold" style="color: #0284c7;">{{ $paymentTerms }}</span></div>
                <div class="meta-line"><span class="meta-label">Tiempo de entrega estimado:</span> <span class="bold" style="color: #0f172a;">{{ $deliveryTime }}</span></div>
                <div class="meta-line"><span class="meta-label">Término de entrega:</span> DDP (Puesto en instalaciones del cliente)</div>
                <div class="meta-line"><span class="meta-label">Moneda de facturación:</span> {{ $currencyText }}</div>
            </td>
        </tr>
    </table>

    {{-- TABLA DE PARTIDAS CON HEADER AZUL MARINO Y NOMBRES FORMALES --}}
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
                            <div style="font-size: 7px; color: #0284c7; font-weight: bold; margin-top: 1px;">Marca: {{ $item['brand'] }}</div>
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
                    <span style="font-size: 9.5px; font-weight: bold; color: #0B2545;">Katya Mirbania Leiva Tórrez</span>
                </div>
                <div class="meta-line" style="margin-top: 5px;">
                    <span class="meta-label">Cuenta BAC Dólares (USD):</span> <span class="bold" style="color: #0B2545; font-family: monospace;">371001983</span><br>
                    <span class="meta-label">Cuenta BAC Córdobas (NIO):</span> <span class="bold" style="color: #0B2545; font-family: monospace;">372092205</span>
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
                    <div class="bold" style="font-size: 8.5px; color: #0B2545;">Firma Autorizada</div>
                    <div style="font-size: 7.2px; color: #64748b;">Dpto. Comercial y Ventas · Power Sales</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- PAGINA 2: TERMINOS Y CONDICIONES --}}
    <div class="page-break"></div>

    <table style="width: 100%; border-collapse: collapse; margin-bottom: 12px; border-bottom: 1px solid #cbd5e1; padding-bottom: 8px;">
        <tr>
            <td>
                <span style="font-size: 14px; font-weight: bold; color: #0B2545;">POWER SALES</span>
                <span style="font-size: 8px; color: #0284c7; font-weight: bold; margin-left: 6px;">THE BEST OPTION</span>
            </td>
            <td style="text-align: right; font-size: 8px; color: #64748b;">
                Términos del Servicio · Oferta {{ $request->reference }}
            </td>
        </tr>
    </table>

    <div class="terms-title">Términos y Condiciones Generales</div>

    <div class="terms-sub">1. Órdenes y pagos</div>
    <div class="terms-p">
        Todos los pedidos están sujetos a disponibilidad. Al colocar una orden formal con Power Sales, el cliente acepta los términos y métodos de pago descritos en este documento. Los pagos deben efectuarse por las vías bancarias indicadas y las facturas fiscales serán entregadas junto con la recepción del producto.
    </div>

    <div class="terms-sub">2. Transporte y entrega</div>
    <div class="terms-p">
        Los costos de envío y tiempos de entrega son estimados durante el proceso de emisión. Nos esforzamos rigurosamente en cumplir con las fechas acordadas; demoras fortuitas ajenas a la logística directa no representarán cargos indemnizatorios a Power Sales.
    </div>

    <div class="terms-sub">3. Garantía limitada de calidad</div>
    <div class="terms-p">
        Nuestros componentes cuentan con garantía estándar de 90 días a partir de la fecha de entrega contra defectos de fabricación o 250 horas de motor (lo que ocurra primero). Para proceder con un reclamo de garantía se requiere el reporte técnico de mantenimiento preventivo y evidencias operativas.
    </div>

    <div class="terms-sub">4. Confidencialidad y soporte</div>
    <div class="terms-p">
        Power Sales maneja sus referencias y requerimientos con estricta confidencialidad. Para asistencia inmediata y seguimiento técnico de órdenes, nuestro equipo permanece a su entera disposición a través de nuestros canales oficiales.
    </div>

    <div style="margin-top: 18px; font-size: 7.2px; color: #94a3b8;">
        Vigencia de políticas: Septiembre 2026 · Power Sales
    </div>

    {{-- PAGINA 3: MARCAS --}}
    <div class="page-break"></div>

    <div style="text-align: center; margin-top: 15px; margin-bottom: 15px;">
        @if($logo)
            <img src="{{ $logo }}" style="max-width: 120px; height: auto; margin: 0 auto 8px;" alt="Power Sales">
        @endif
        <div style="font-size: 14px; font-weight: bold; color: #0B2545;">Marcas y Suministros Globales</div>
        <div style="font-size: 8px; color: #64748b; margin-top: 2px;">Componentes certificados y repuestos de alta durabilidad</div>
    </div>

    <table class="brands-grid">
        <tr>
            <td class="brand-pill" style="width: 33%;">XCMG</td>
            <td class="brand-pill" style="width: 33%;">SINOTRUK</td>
            <td class="brand-pill" style="width: 33%;">SANY</td>
        </tr>
        <tr>
            <td class="brand-pill">DEUTZ</td>
            <td class="brand-pill" style="color: #d97706;">CAT</td>
            <td class="brand-pill">FRD FURUKAWA</td>
        </tr>
        <tr>
            <td class="brand-pill">PARKER</td>
            <td class="brand-pill" style="color: #ca8a04;">EPIROC</td>
            <td class="brand-pill" style="color: #dc2626;">MONTABERT</td>
        </tr>
        <tr>
            <td class="brand-pill">REXROTH (Bosch)</td>
            <td class="brand-pill">EATON</td>
            <td class="brand-pill" style="color: #16a34a;">SCHNEIDER ELECTRIC</td>
        </tr>
    </table>

    <div style="text-align: center; margin-top: 30px; font-size: 8px; color: #94a3b8;">
        Powersalesnic.com · Calidad garantizada en maquinaria y repuestos
    </div>

</body>
</html>
