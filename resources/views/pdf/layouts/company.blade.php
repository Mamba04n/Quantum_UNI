<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Documento Quantum')</title>
    @include('partials.favicons')
    <style>
        @page { margin: 1cm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; color: #1f2937; line-height: 1.35; }
        table { width: 100%; border-collapse: collapse; }
        .brand-header { border-bottom: 2px solid #d1d5db; padding-bottom: 10px; margin-bottom: 16px; }
        .logo { width: 230px; }
        .company-info { font-size: 8.5px; color: #4b5563; margin-top: 8px; }
        .document-title { text-align: right; color: #0f172a; }
        .document-title h1 { margin: 0; font-size: 20px; letter-spacing: .4px; }
        .document-title .tagline { font-size: 14px; font-weight: bold; color: #00235a; margin-bottom: 4px; }
        .muted { color: #64748b; }
        .box { border: 1px solid #e5e7eb; padding: 10px; margin-bottom: 12px; }
        .section-title { font-weight: bold; color: #0f172a; text-transform: uppercase; font-size: 9px; letter-spacing: .7px; margin-bottom: 6px; }
        .data-table th { background: #0f172a; color: #fff; padding: 7px; font-size: 9px; text-align: left; }
        .data-table td { border: 1px solid #e5e7eb; padding: 7px; vertical-align: top; }
        .offer-table th { background: #a30000; color: #fff; padding: 6px; font-size: 9px; text-align: center; border: 1px solid #a30000; }
        .offer-table td { border: 1px solid #ddd; padding: 6px; font-size: 9px; vertical-align: middle; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .totals-table { width: 35%; float: right; border-collapse: collapse; }
        .totals-table td { padding: 5px; font-size: 10px; border: 1px solid #ddd; }
        .total-row { background-color: #a30000; color: white; font-weight: bold; }
        .footer { clear: both; position: fixed; bottom: 0; left: 0; right: 0; border-top: 1px solid #ddd; padding-top: 8px; font-size: 8px; color: #555; text-align: center; }
        @yield('styles')
    </style>
</head>
<body>
    <table class="brand-header">
        <tr>
            <td width="52%" valign="top">
                @php($logoBase64 = $logoBase64 ?? app(\App\Services\Pdf\PdfDocumentService::class)->logoToBase64())
                @if($logoBase64)
                    <img src="{{ $logoBase64 }}" class="logo">
                @else
                    <strong>Quantum Soluciones</strong>
                @endif
                <div class="company-info">
                    <strong>Quantum Soluciones S.A.</strong><br>
                    Colonia Centroamericana, Managua<br>
                    RUC J0310000475024<br>
                    ventas@quantumsolucionesnic.com<br>
                    +505 7669 8164 | +505 5744 5963
                </div>
            </td>
            <td width="48%" valign="top" class="document-title">
                @yield('document-header')
            </td>
        </tr>
    </table>

    @yield('content')

    <div class="footer">
        Colonia Centroamericana, Managua | ventas@quantumsolucionesnic.com | Lun-Vie 8:00 AM - 5:00 PM
    </div>
</body>
</html>
