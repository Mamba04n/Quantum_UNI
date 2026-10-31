@php
    $companyName = $company['name'] ?? 'Quantum Soluciones S.A.';
    $companyPhone = $company['phone'] ?? '+505 7669 8164';
    $companyEmail = $company['email'] ?? 'ventas@quantumsolucionesnic.com';
    $companyWebsite = $company['website'] ?? 'quantumsolucionesnic.com';
    $companyAddress = $company['address'] ?? 'Managua, Nicaragua';
    $companyRuc = $company['ruc'] ?? 'J0310000475024';
    $logo = $logoBase64 ?? null;

    $orderCode = $data['document']['code'] ?? 'OC-'.$approval->id;
    $date = $data['document']['date'] ?? now()->format('d/m/Y');
    $currency = strtoupper((string) ($currency ?? $data['totals']['currency'] ?? 'USD'));
    $currencyLabel = in_array($currency, ['NIO', 'C$', 'CORDOBA', 'CORDOBAS'], true) ? 'C$' : '$';
    $supplier = $data['supplier'];
    $consignee = $data['consignee'];
    $shipping = $data['shipping'];
    $comments = $data['comments'];

    $terms = collect([
        $comments['supplier'] ?? null,
        $comments['special_instructions'] ?? null,
        $shipping['comments'] ?? null,
        $comments['footer'] ?? null,
    ])->filter()->implode("\n");
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Orden de Compra {{ $orderCode }}</title>
    <style>
        @page { margin: 10mm 10mm 18mm 10mm; footer: html_pdf_footer; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #222; line-height: 1.28; margin: 0; }
        * { box-sizing: border-box; }
        .bold { font-weight: bold; }
        .right { text-align: right; }
        .center { text-align: center; }
        .header { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .header-left { width: 62%; vertical-align: top; padding-right: 12mm; }
        .header-right { width: 38%; vertical-align: top; text-align: center; }
        .title { font-size: 23px; line-height: 1.05; letter-spacing: .8px; color: #111; font-weight: bold; text-transform: uppercase; margin: 0 0 8px; }
        .meta { width: 100%; border-collapse: collapse; margin-top: 2px; margin-bottom: 0; }
        .meta-number { padding: 5px 0; font-size: 8.5px; height: 24px; white-space: nowrap; overflow: hidden; }
        .meta-date { background: #A00F0F; color: #fff; padding: 5px 8px; font-size: 8.5px; height: 24px; text-align: center; font-weight: bold; white-space: nowrap; }
        .logo { width: 105px; height: auto; display: block; margin: 0 auto 3px; }
        .company-name { font-size: 8.8px; font-weight: bold; margin-bottom: 2px; }
        .company-info { font-size: 7.6px; color: #222; line-height: 1.18; text-align: center; }
        .header-divider { border-top: .6px solid #dadada; margin: 7px 0 9px; }
        .top-grid { width: 100%; border-collapse: collapse; margin-top: 8px; margin-bottom: 14px; }
        .top-grid td { vertical-align: top; }
        .left-box { width: 58%; padding-right: 9mm; }
        .right-box { width: 42%; padding-left: 9mm; border-left: .5px solid #d5d5d5; }
        .block-title { font-size: 11px; font-weight: bold; margin-bottom: 4px; color: #A00F0F; }
        .info-table { width: 100%; border-collapse: collapse; font-size: 8.3px; }
        .info-table td { padding: 1.6px 0; vertical-align: top; border-bottom: .35px solid #ececec; }
        .info-table td:first-child { width: 26%; font-weight: bold; color: #333; }
        .mini-table { width: 100%; border-collapse: collapse; font-size: 8.4px; }
        .mini-table td { padding: 2px 0; vertical-align: top; }
        .mini-table td:first-child { width: 42%; font-weight: bold; color: #333; }
        .section-title { font-size: 10.4px; font-weight: bold; margin: 12px 0 7px; color: #222; }
        .items { width: 100%; border-collapse: collapse; font-size: 8.1px; margin-bottom: 12px; }
        .items thead { display: table-header-group; }
        .items tr { page-break-inside: avoid; }
        .items th { padding: 7px 5px; border: .5px solid #A00F0F; background: #A00F0F; color: #fff; font-weight: bold; text-align: left; }
        .items td { padding: 7px 5px; border: .35px solid #e1e1e1; vertical-align: top; }
        .items tbody tr:nth-child(even) td { background: #fbfbfb; }
        .description-main { font-weight: bold; }
        .description-sub { color: #666; font-size: 7.4px; margin-top: 2px; }
        .bottom { width: 100%; border-collapse: collapse; margin-top: 8px; page-break-inside: avoid; }
        .bottom-spacer { width: 57%; vertical-align: top; padding-right: 9mm; }
        .bottom-right { width: 43%; vertical-align: top; padding-left: 4mm; }
        .totals { width: 100%; border-collapse: collapse; font-size: 9px; }
        .totals td { padding: 5px 6px; border-bottom: .6px solid #cfcfcf; }
        .total-row td { border-bottom: none; font-size: 11px; font-weight: bold; padding-top: 8px; }
        .conditions { width: 100%; border-collapse: collapse; margin-top: 14px; page-break-inside: avoid; }
        .conditions td { padding: 0; font-size: 8px; line-height: 1.35; color: #222; }
        .conditions-title { font-size: 8.5px; font-weight: bold; color: #A00F0F; margin-bottom: 5px; }
        .urgent { color: #8F0000; font-weight: bold; }
        .pdf-footer { border-top: .6px solid #cfcfcf; padding-top: 5px; font-size: 7.2px; text-align: center; color: #555; }
    </style>
</head>
<body>
    <htmlpagefooter name="pdf_footer">
        <div class="pdf-footer">
            {{ $companyAddress }} | {{ $companyPhone }} | {{ $companyEmail }} | Generado por Sistema ERP - {{ $companyName }}
        </div>
    </htmlpagefooter>
    <sethtmlpagefooter name="pdf_footer" value="on" />

    <table class="header">
        <tr>
            <td class="header-left">
                <div class="title">Orden de Compra</div>
                <table class="meta">
                    <tr>
                        <td style="width: 54%; padding-right: 5px;">
                            <div class="meta-number"><span class="bold">Número de orden:</span> {{ $orderCode }}</div>
                        </td>
                        <td style="width: 46%; padding-left: 5px;">
                            <div class="meta-date">{{ $date }}</div>
                        </td>
                    </tr>
                </table>
            </td>
            <td class="header-right">
                @if($logo)
                    <img src="{{ $logo }}" class="logo" alt="Quantum Soluciones">
                @endif
                <div class="company-name">{{ $companyName }}</div>
                <div class="company-info">
                    {{ $companyPhone }}<br>
                    {{ $companyWebsite }}<br>
                    {{ $companyEmail }}<br>
                    {{ $companyAddress }}
                </div>
            </td>
        </tr>
    </table>

    <div class="header-divider"></div>

    <table class="top-grid">
        <tr>
            <td class="left-box">
                <div class="block-title">Facturar a:</div>
                <table class="info-table">
                    <tr><td>Nombre:</td><td class="bold">{{ $data['document']['quote_to_name'] ?: $companyName }}</td></tr>
                    <tr><td>RUC:</td><td>{{ $companyRuc }}</td></tr>
                    <tr><td>Solicitud:</td><td>{{ $quotation?->reference ?: '-' }}</td></tr>
                    @if(!empty($data['document']['tracking_code']))
                        <tr><td>Seguimiento:</td><td>{{ $data['document']['tracking_code'] }}</td></tr>
                    @endif
                </table>

                <div class="block-title" style="margin-top: 13px;">Detalles de envío</div>
                <table class="info-table">
                    <tr><td>Enviar a:</td><td>{{ $consignee['address_1'] }}{{ $consignee['address_2'] ? ', '.$consignee['address_2'] : '' }}</td></tr>
                    <tr><td>Consignatario:</td><td>{{ $consignee['name'] }}</td></tr>
                    <tr><td>País / ciudad:</td><td>{{ $consignee['country'] }} / {{ $consignee['city'] }}</td></tr>
                    <tr><td>Teléfono:</td><td>{{ $consignee['phone'] }}</td></tr>
                    <tr><td>Correos:</td><td>{{ $consignee['emails'] ?: '-' }}</td></tr>
                    @if(!empty($consignee['urgent_comment']))
                        <tr><td class="urgent">Urgente:</td><td>{{ $consignee['urgent_comment'] }}</td></tr>
                    @endif
                </table>
            </td>
            <td class="right-box">
                <div class="block-title">Proveedor:</div>
                <table class="mini-table">
                    <tr><td>Nombre:</td><td class="bold">{{ $supplier['name'] ?: 'Proveedor seleccionado' }}</td></tr>
                    <tr><td>Contacto:</td><td>{{ $supplier['contact'] ?: '-' }}</td></tr>
                    <tr><td>Email:</td><td>{{ $supplier['email'] ?: '-' }}</td></tr>
                    <tr><td>Teléfono:</td><td>{{ $supplier['phone'] ?: '-' }}</td></tr>
                    <tr><td>Pago:</td><td>{{ $shipping['terms'] ?: 'Según acuerdo proveedor' }}</td></tr>
                    <tr><td>Incoterm:</td><td>{{ $shipping['incoterm'] ?: '-' }}</td></tr>
                    <tr><td>Transportista:</td><td>{{ $shipping['carrier'] ?: '-' }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="section-title">Detalles del pedido</div>
    <table class="items">
        <thead>
            <tr>
                <th style="width: 6%;" class="center">Items</th>
                <th style="width: 16%;">Número Parte</th>
                <th style="width: 36%;">Descripción</th>
                <th style="width: 10%;" class="center">Cantidad</th>
                <th style="width: 15%;" class="right">Valor unitario</th>
                <th style="width: 17%;" class="right">Valor Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $index => $item)
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>{{ $item['part_number'] ?: '-' }}</td>
                    <td>
                        <div class="description-main">{{ $item['part_name'] ?: '-' }}</div>
                        @if(($item['brand'] ?? null) || ($item['model'] ?? null) || ($item['description'] ?? null))
                            <div class="description-sub">
                                @if($item['brand']) Marca: {{ $item['brand'] }} @endif
                                @if($item['model']) Modelo: {{ $item['model'] }} @endif
                                @if($item['description']) {{ $item['description'] }} @endif
                            </div>
                        @endif
                    </td>
                    <td class="center">{{ number_format((float) $item['quantity'], 2) }}</td>
                    <td class="right">{{ $currencyLabel }} {{ number_format((float) $item['unit_cost'], 2) }}</td>
                    <td class="right">{{ $currencyLabel }} {{ number_format((float) $item['subtotal'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="bottom">
        <tr>
            <td class="bottom-spacer"></td>
            <td class="bottom-right">
                <table class="totals">
                    <tr>
                        <td class="bold right">Costo subtotal:</td>
                        <td class="right">{{ $currencyLabel }} {{ number_format((float) $data['totals']['subtotal'], 2) }}</td>
                    </tr>
                    @if((float) ($data['totals']['taxes'] ?? 0) > 0)
                        <tr>
                            <td class="right">Impuestos</td>
                            <td class="right">{{ $currencyLabel }} {{ number_format((float) $data['totals']['taxes'], 2) }}</td>
                        </tr>
                    @endif
                    @if((float) ($data['totals']['shipping'] ?? 0) > 0)
                        <tr>
                            <td class="right">Envío</td>
                            <td class="right">{{ $currencyLabel }} {{ number_format((float) $data['totals']['shipping'], 2) }}</td>
                        </tr>
                    @endif
                    <tr class="total-row">
                        <td class="right">TOTAL:</td>
                        <td class="right">{{ $currencyLabel }} {{ number_format((float) $data['totals']['total'], 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    @if($terms !== '')
        <table class="conditions">
            <tr>
                <td>
                    <div class="conditions-title">Términos y condiciones relevantes:</div>
                    {!! nl2br(e($terms)) !!}
                </td>
            </tr>
        </table>
    @endif
</body>
</html>
