<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>@yield('title', 'Documento comercial')</title>
    @include('partials.favicons')
    <style>
        @page { margin: 22px 28px 48px 28px; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #1f2937;
            font-size: 9px;
            line-height: 1.28;
            margin: 0;
        }
        table { width: 100%; border-collapse: collapse; page-break-inside: auto; }
        thead { display: table-header-group; }
        tfoot { display: table-footer-group; }
        tr { page-break-inside: avoid; }
        th, td { vertical-align: top; }
        .header-table { margin-bottom: 10px; }
        .header-table td { border: none; padding: 0; }
        .header-left { width: 45%; }
        .header-right { width: 55%; text-align: right; }
        .logo { width: 115px; height: auto; display: block; margin-bottom: 6px; }
        .document-logo-fixed {
            position: fixed;
            top: 52px;
            right: 58px;
            width: 172px;
            height: auto;
            z-index: -1;
        }
        .company-name { color: #b91c1c; font-size: 12px; font-weight: bold; margin-bottom: 3px; }
        .company-info { color: #4b5563; font-size: 8px; line-height: 1.35; }
        .slogan { color: #6b7280; font-size: 8px; margin-bottom: 4px; }
        .document-title {
            color: #b91c1c;
            font-size: 20px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            line-height: 1.1;
            margin: 0 0 8px 0;
        }
        .meta-table { width: 245px; margin-left: auto; font-size: 8.5px; }
        .meta-table td { border: none; padding: 2px 0 2px 12px; }
        .meta-label { color: #4b5563; font-weight: bold; text-transform: uppercase; text-align: left; }
        .meta-value { color: #111827; font-weight: bold; text-align: right; }
        .content-line { border-top: 1px solid #e5e7eb; margin: 0 0 8px 0; }
        .section-title {
            color: #b91c1c;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: .8px;
            border-top: 1px solid #b91c1c;
            border-bottom: 1px solid #fecaca;
            padding: 4px 0;
            margin: 8px 0 7px 0;
        }
        .info-block { margin-bottom: 7px; font-size: 8.1px; }
        .info-block td { border: 1px solid #e5e7eb; padding: 3px 4px; }
        .info-label { width: 78px; color: #64748b; font-weight: bold; text-transform: uppercase; font-size: 7.2px; }
        .items-table { margin-top: 6px; margin-bottom: 8px; }
        .items-table th {
            background: #991b1b;
            color: #ffffff;
            border: 1px solid #991b1b;
            padding: 3.5px 3px;
            font-size: 7.1px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .items-table td {
            border: 1px solid #d7dce4;
            padding: 3.2px 3px;
            font-size: 7.7px;
        }
        .items-table tbody tr:nth-child(even) td { background: #f8fafc; }
        .notes-box {
            margin-top: 7px;
            border: 1px solid #d7dce4;
            border-left: 4px solid #991b1b;
            padding: 6px 7px;
            background: #fafafa;
            font-size: 8px;
        }
        .notes-box ul { margin: 4px 0 0 0; padding-left: 14px; }
        .notes-box li { margin-bottom: 1px; }
        .preserve-lines { margin-top: 4px; white-space: pre-line; line-height: 1.45; }
        .two-col td { width: 50%; padding: 0 4px 0 0; }
        .box { border: 1px solid #d7dce4; padding: 5px; margin-top: 6px; }
        .box-title { color: #991b1b; font-weight: bold; text-transform: uppercase; font-size: 7.4px; margin-bottom: 3px; }
        .label { color: #64748b; font-weight: bold; text-transform: uppercase; font-size: 6.9px; }
        .small { font-size: 6.9px; color: #64748b; }
        .urgent { border: 1px solid #fca5a5; background: #fff1f2; color: #991b1b; padding: 4px; font-weight: bold; margin-top: 4px; }
        .totals-table { width: 38%; margin-left: auto; margin-top: 5px; font-size: 8px; }
        .totals-table td { border-bottom: 1px solid #e5e7eb; padding: 3px 4px; }
        .total-row td { border-top: 2px solid #991b1b; color: #991b1b; font-weight: bold; font-size: 9px; }
        .footer {
            position: fixed;
            left: 0;
            right: 0;
            bottom: -25px;
            height: 34px;
            border-top: 1px solid #e5e7eb;
            padding-top: 5px;
            color: #6b7280;
            font-size: 7px;
            text-align: center;
            line-height: 1.35;
        }
        .footer-line { margin-bottom: 2px; }
        .pagenum:before { content: counter(page); }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
    </style>
    @stack('styles')
</head>
<body>
    @if(!empty($logoBase64))
        <img src="{{ $logoBase64 }}" class="document-logo-fixed">
    @endif

    <table class="header-table">
        <tr>
            <td class="header-left">
                @hasSection('header_left')
                    @yield('header_left')
                @else
                    <div class="company-name">{{ $company['name'] ?? 'Quantum Soluciones S.A.' }}</div>
                    <div class="company-info">
                        @hasSection('company_info')
                            @yield('company_info')
                        @else
                            {{ $company['address'] ?? 'Rotonda Jean Paul Genie, Managua, Nicaragua' }}<br>
                            {{ $company['email'] ?? 'ventas@quantum.com.ni' }}@if(!empty($company['phone'])) | {{ $company['phone'] }}@endif<br>
                            {{ $company['website'] ?? 'www.quantum.com.ni' }}
                        @endif
                    </div>
                @endif
            </td>
            <td class="header-right">
                @if($__env->yieldContent('header_right') !== '')
                    @yield('header_right')
                @else
                    <div class="slogan">{{ $company['slogan'] ?? 'Repuestos - Servicio - Maquinaria' }}</div>
                    <div class="document-title">@yield('doc_type', 'DOCUMENTO')</div>
                    <table class="meta-table">@yield('header_meta')</table>
                @endif
            </td>
        </tr>
    </table>

    <div class="content-line"></div>

    <main>@yield('content')</main>

    @php
        $footerDefaults = [
            'address' => 'Rotonda Jean Paul Genie',
            'contact' => ($company['phone'] ?? '+505 22XX-XXXX') . ' | ' . ($company['email'] ?? 'ventas@quantum.com.ni'),
            'schedule' => $company['schedule'] ?? 'Lunes a Viernes 8:00 AM - 5:00 PM',
            'system' => 'Generado por Sistema ERP - ' . ($company['name'] ?? 'Quantum Soluciones S.A.'),
        ];
        $footer = array_merge($footerDefaults, $footerData ?? []);
    @endphp
    <div class="footer">
        <div class="footer-line">
            <strong>Direccion:</strong> {{ $footer['address'] }} |
            <strong>Contacto:</strong> {{ $footer['contact'] }} |
            <strong>Horario:</strong> {{ $footer['schedule'] }}
        </div>
        {{ $footer['system'] }} |
        Pagina <span class="pagenum"></span>
    </div>
</body>
</html>
