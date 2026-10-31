<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>@yield('title', 'Documento Comercial')</title>
    @include('partials.favicons')
    <style>
        @page {
            margin: 22px 28px 45px 28px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            color: #1f2937;
            margin: 0;
            padding: 0;
            line-height: 1.28;
        }

        * {
            box-sizing: border-box;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .header-table td {
            border: none;
            padding: 0;
            vertical-align: top;
        }

        .header-left {
            width: 45%;
        }

        .header-right {
            width: 55%;
            text-align: right;
        }

        .logo {
            width: 115px;
            height: auto;
            display: block;
            margin-bottom: 6px;
        }

        .company-name {
            color: #b91c1c;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 3px;
        }

        .company-info {
            color: #4b5563;
            font-size: 8px;
            line-height: 1.35;
        }

        .slogan {
            color: #6b7280;
            font-size: 8px;
            margin-bottom: 4px;
        }

        .document-title {
            color: #b91c1c;
            font-size: 20px;
            font-weight: bold;
            letter-spacing: 1.5px;
            margin: 0 0 8px 0;
            text-transform: uppercase;
            line-height: 1.1;
        }

        .meta-table {
            width: 230px;
            margin-left: auto;
            border-collapse: collapse;
            font-size: 8.5px;
        }

        .meta-table td {
            border: none;
            padding: 2px 0 2px 12px;
            vertical-align: top;
        }

        .meta-label {
            width: 45%;
            color: #4b5563;
            font-weight: bold;
            text-align: left;
        }

        .meta-value {
            width: 55%;
            color: #222222;
            font-weight: bold;
            text-align: right;
        }

        .content-spacer {
            height: 0;
            border-top: 1px solid #eeeeee;
            margin-bottom: 8px;
        }

        .section-title {
            color: #b91c1c;
            font-size: 9px;
            text-transform: uppercase;
            font-weight: bold;
            letter-spacing: .8px;
            border-top: 1px solid #b91c1c;
            border-bottom: 1px solid #fecaca;
            padding: 4px 0;
            margin: 8px 0 7px 0;
        }

        .info-block {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
            font-size: 10px;
        }

        .info-block td {
            border: none;
            padding: 4px 6px;
            vertical-align: top;
        }

        .info-label {
            width: 85px;
            color: #666666;
            font-weight: bold;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            page-break-inside: auto;
            margin-top: 12px;
            margin-bottom: 20px;
        }

        .items-table thead {
            display: table-header-group;
        }

        .items-table tfoot {
            display: table-footer-group;
        }

        .items-table tr {
            page-break-inside: avoid;
        }

        .items-table th {
            background: #b91c1c;
            color: #ffffff;
            padding: 8px 6px;
            border: 1px solid #b91c1c;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            text-align: left;
            vertical-align: middle;
        }

        .items-table td {
            border: 1px solid #dddddd;
            padding: 7px 6px;
            font-size: 10px;
            vertical-align: top;
        }

        .items-table tr:nth-child(even) td {
            background: #fafafa;
        }

        .notes-box {
            margin-top: 20px;
            border: 1px solid #dddddd;
            border-left: 4px solid #b91c1c;
            padding: 12px;
            background: #fafafa;
            font-size: 9px;
        }

        .notes-box ul {
            margin: 6px 0 0 0;
            padding-left: 18px;
        }

        .notes-box li {
            margin-bottom: 3px;
        }

        .totals-table {
            width: 42%;
            float: right;
            border-collapse: collapse;
            font-size: 10.5px;
            margin: 5px 0 20px 0;
        }

        .totals-table td {
            border-bottom: 1px solid #eeeeee;
            padding: 6px 8px;
        }

        .totals-label {
            color: #555555;
            font-weight: bold;
            text-align: right;
        }

        .totals-value {
            width: 110px;
            text-align: right;
        }

        .total-row td {
            color: #b91c1c;
            font-size: 12px;
            font-weight: bold;
            border-top: 2px solid #b91c1c;
            border-bottom: 2px solid #b91c1c;
            background: #fff7f7;
        }

        .footer {
            position: fixed;
            bottom: -28px;
            left: 0;
            right: 0;
            height: 32px;
            padding-top: 5px;
            border-top: 1px solid #eeeeee;
            color: #6b7280;
            font-size: 7px;
            text-align: center;
            line-height: 1.35;
        }

        .footer-line { margin-bottom: 2px; }
        .pagenum:before { content: counter(page); }
        .pagecount:before { content: counter(pages); }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .font-bold { font-weight: bold; }
        .clearfix::after { content: ""; clear: both; display: table; }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td class="header-left">
                @if(!empty($logoBase64))
                    <img src="{{ $logoBase64 }}" class="logo">
                @endif

                <div class="company-name">{{ $company['name'] ?? 'Quantum Soluciones S.A.' }}</div>
                <div class="company-info">
                    {!! nl2br(e($company['address'] ?? '')) !!}<br>
                    {{ $company['email'] ?? '' }}@if(!empty($company['phone'])) | {{ $company['phone'] }}@endif<br>
                    {{ $company['website'] ?? '' }}
                </div>
            </td>
            <td class="header-right">
                <div class="slogan">{{ $company['slogan'] ?? 'Repuestos - Servicio - Maquinaria' }}</div>
                <div class="document-title">@yield('doc_type', 'DOCUMENTO')</div>

                <table class="meta-table">
                    @yield('header_meta')
                </table>
            </td>
        </tr>
    </table>

    <div class="content-spacer"></div>

    <main>
        @yield('content')
    </main>

    @php
        $footer = $footerData ?? [
            'address' => 'Rotonda Jean Paul Genie',
            'contact' => ($company['phone'] ?? '+505 22XX-XXXX') . ' | ' . ($company['email'] ?? 'ventas@quantum.com.ni'),
            'schedule' => $company['schedule'] ?? 'Lunes a Viernes 8:00 AM - 5:00 PM',
            'system' => 'Generado por Sistema ERP - ' . ($company['name'] ?? 'Quantum Soluciones S.A.'),
        ];
    @endphp

    <div class="footer">
        <div class="footer-line">
            <strong>Direccion:</strong> {{ $footer['address'] }} |
            <strong>Contacto:</strong> {{ $footer['contact'] }} |
            <strong>Horario:</strong> {{ $footer['schedule'] }}
        </div>
        {{ $footer['system'] }} |
        Pagina <span class="pagenum"></span> de <span class="pagecount"></span>
    </div>
</body>
</html>
