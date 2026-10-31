<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cotización {{ $ref }}</title>
    <style>
        @page {
            margin: 1cm;
        }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 10pt;
            color: #333;
            line-height: 1.3;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #ddd;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .logo {
            width: 250px;
        }
        .company-info {
            font-size: 8.5pt;
            color: #444;
        }
        .title-area {
            text-align: right;
        }
        .title-main {
            font-size: 18pt;
            font-weight: bold;
            color: #00235a;
            margin-bottom: 5px;
        }
        .title-sub {
            font-size: 14pt;
            color: #00235a;
            font-weight: normal;
        }
        .quote-meta {
            text-align: right;
            font-size: 9pt;
        }
        .quote-meta td {
            padding: 1px 0;
        }

        .customer-section {
            width: 100%;
            margin-bottom: 20px;
        }
        .customer-title {
            font-weight: bold;
            font-size: 11pt;
            border-bottom: 1px solid #ccc;
            margin-bottom: 8px;
            padding-bottom: 2px;
        }
        .customer-info-table {
            width: 100%;
            font-size: 9.5pt;
        }
        .label {
            font-weight: bold;
            width: 80px;
            color: #555;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table th {
            background-color: #a30000;
            color: white;
            font-weight: bold;
            text-align: center;
            padding: 6px;
            font-size: 9pt;
            border: 1px solid #a30000;
        }
        .items-table td {
            border: 1px solid #ddd;
            padding: 6px;
            font-size: 9pt;
            vertical-align: middle;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }

        .footer-section {
            width: 100%;
        }
        .observations-box {
            width: 60%;
            border: 1px solid #ccc;
            padding: 10px;
            font-size: 9pt;
            min-height: 80px;
        }
        .totals-table {
            width: 35%;
            float: right;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 4px;
            font-size: 10pt;
            border: 1px solid #ddd;
        }
        .total-row {
            background-color: #a30000;
            color: white;
            font-weight: bold;
        }
        .currency-info {
            font-size: 8.5pt;
            margin-top: 10px;
            color: #555;
        }
    </style>
</head>
<body>

    {{-- Encabezado --}}
    <table class="header-table">
        <tr>
            <td width="50%" valign="top">
                @php($logoBase64 = $logoBase64 ?? app(\App\Services\Pdf\PdfDocumentService::class)->logoToBase64())
                @if($logoBase64)
                    <img src="{{ $logoBase64 }}" class="logo">
                @else
                    <strong>Quantum Soluciones</strong>
                @endif
                <div class="company-info" style="margin-top: 10px;">
                    <div style="font-weight: bold; font-size: 10pt;">Quantum Soluciones S.A.</div>
                    <div>Dir.: Colonia Centroamericana, Distribuidora "El Tope", 1c E. 1c.S, 20 vrs E. Contiguo a Bomba de Enacal</div>
                    <div>Email: ventas@quantumsolucionesnic.com</div>
                    <div>Telf: +505 7669 8164 | +505 5744 5963</div>
                    <div>Ruc: J0310000475024</div>
                    <div>www.quantumsolucionesnic.com</div>
                </div>
            </td>
            <td width="50%" valign="top" class="title-area">
                <div class="title-main">Repuestos • Servicio • Maquinaria</div>
                <div class="title-sub">COTIZACIÓN</div>
                <table align="right" class="quote-meta" style="margin-top: 10px;">
                    <tr>
                        <td class="text-right" style="padding-right: 10px;">Número de cotización:</td>
                        <td class="text-right" style="font-weight: bold;">{{ $ref }}</td>
                    </tr>
                    <tr>
                        <td class="text-right">Fecha:</td>
                        <td class="text-right">{{ $fecha }}</td>
                    </tr>
                    <tr>
                        <td class="text-right">Validez de la oferta:</td>
                        <td class="text-right">15 días</td>
                    </tr>
                    <tr>
                        <td class="text-right">Condición de Pago:</td>
                        <td class="text-right">{{ $payTerms ?: 'Contado' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Información del Cliente --}}
    <div class="customer-section">
        <div class="customer-title">Información del cliente</div>
        <table class="customer-info-table">
            <tr>
                <td class="label">Nombre:</td>
                <td colspan="3" style="font-weight: bold; text-transform: uppercase;">{{ $quoteTo }}</td>
            </tr>
            <tr>
                <td class="label">Dirección:</td>
                <td colspan="3">{{ $consigneeAddr ?: 'N/D' }}</td>
            </tr>
            <tr>
                <td class="label">Contacto:</td>
                <td>{{ $requestedBy ?: 'N/D' }}</td>
                <td class="label" style="text-align: right; padding-right: 10px;">Email:</td>
                <td>{{ $email1 ?: 'N/D' }}</td>
            </tr>
            <tr>
                <td class="label">Teléfono:</td>
                <td colspan="3">{{ $phone ?: 'N/D' }}</td>
            </tr>
        </table>
    </div>

    <div style="margin-bottom: 10px; font-style: italic;">Es un placer extenderle nuestra cotización.</div>

    {{-- Tabla de Ítems --}}
    <table class="items-table">
        <thead>
            <tr>
                <th width="5%">N°</th>
                <th width="15%">Referencia</th>
                <th width="40%">Descripción</th>
                <th width="10%">Cantidad</th>
                <th width="15%">Precio Unitario</th>
                <th width="15%">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $index => $r)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td class="text-center">{{ $r['pn'] }}</td>
                <td>{{ $r['desc'] }}</td>
                <td class="text-center">{{ $r['qty'] }}</td>
                <td class="text-right">C${{ number_format((float) $r['unit'], 2) }}</td>
                <td class="text-right">C${{ number_format((float) $r['total'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Pie de página y Totales --}}
    <div class="footer-section">
        <div style="float: left; width: 60%;">
            <div style="font-weight: bold; margin-bottom: 5px;">Observaciones:</div>
            <div class="observations-box">
                {!! nl2br(e($notes)) !!}
                <div style="margin-top: 10px; font-size: 8pt; color: #666;">
                    - Garantía según fabricante.<br>
                    - Disponibilidad sujeta a venta previa.
                </div>
            </div>
            <div class="currency-info">
                Moneda: Córdoba <span style="margin-left: 15px;">Tipo de cambio: Oficial,BCN</span><br>
                Elaborar Cheque a nombre de: QUANTUM SOLUCIONES S.A.
            </div>
        </div>

        <table class="totals-table">
            <tr>
                <td class="text-right" style="font-weight: bold;">Sub-Total</td>
                <td class="text-right">C$ {{ number_format((float) $subtotal, 2) }}</td>
            </tr>
            <tr>
                <td class="text-right" style="font-weight: bold;">Monto IVA (15%)</td>
                <td class="text-right">C$ {{ number_format((float) $subtotal * 0.15, 2) }}</td>
            </tr>
            <tr class="total-row">
                <td class="text-right" style="font-size: 11pt;">Total</td>
                <td class="text-right" style="font-size: 11pt;">C$ {{ number_format((float) $subtotal * 1.15, 2) }}</td>
            </tr>
        </table>
    </div>

</body>
</html>
