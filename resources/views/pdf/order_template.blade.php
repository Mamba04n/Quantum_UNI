<style>
    body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 10pt; color: #222; margin: 0; padding: 0; }
    .title { font-size: 20pt; color: #222; margin-bottom: 20px; letter-spacing: 1px; }

    .header-table { width: 100%; margin-bottom: 30px; }

    .logo-container { text-align: center; width: 100%; }
    .logo-text { font-size: 20pt; font-weight: bold; letter-spacing: -1px; margin-top: 5px; color:#000; font-family: sans-serif;}
    .logo-sub { font-size: 8pt; letter-spacing: 3px; color:#000; font-family: sans-serif;}

    .company-details { font-size: 7.5pt; color: #333; line-height: 1.3; margin-top: 15px; }

    .section-title { font-weight: bold; font-size: 9pt; color: #000; margin-bottom: 5px; margin-top: 15px; }
    .info-text { font-size: 8pt; color: #333; line-height: 1.4; }

    .items-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    .items-table th {
        font-size: 8pt;
        font-weight: bold;
        text-align: center;
        padding-bottom: 10px;
        border-bottom: 1px solid #7c0a02;
    }
    .items-table tbody td {
        padding: 10px 5px;
        font-size: 8pt;
        text-align: center;
        color: #333;
    }

    .totals-wrapper { width: 100%; margin-top: 20px; }
    .totals-table { width: 45%; float: right; font-size: 9pt; border-collapse: collapse; margin-right: 15px; }
    .totals-table td { padding: 8px 0; }
    .line-row { border-bottom: 1px solid #c4c4c4; }

    .footer-box {
        background-color: #7c0a02;
        color: #fff;
        padding: 15px;
        margin-top: 50px;
        font-size: 8pt;
    }
</style>

<table class="header-table" cellpadding="0" cellspacing="0">
    <tr>
        <td width="55%" valign="top">
            <div class="title">ORDEN DE COMPRA</div>
            <table cellspacing="0" cellpadding="0">
                <tr>
                    <td style="border: 1px solid #7c0a02; padding: 5px 10px; font-size: 8pt; width: 160px;">Numero de orden: {{ $orderId }}</td>
                    <td style="width: 10px;"></td>
                    <td style="background-color: #7c0a02; color: #fff; padding: 6px 10px; font-size: 8pt; width: 160px;">{{ $fecha }}</td>
                </tr>
            </table>

            <div class="section-title" style="margin-top: 30px;">Facturar a:</div>
            <div class="info-text">
                {{ $cliente }}
            </div>

            <div class="section-title" style="margin-top: 15px;">Detalles de envío</div>
            <div class="info-text">
                {!! nl2br(e($shipping ?? '')) !!}
            </div>
        </td>
        <td width="45%" valign="top">
            <div style="padding-left: 40px;">
                <div style="padding-left: 15px;">
                    @php($logoBase64 = app(\App\Services\Pdf\PdfDocumentService::class)->logoToBase64())
                    @if($logoBase64)
                        <img src="{{ $logoBase64 }}" width="190">
                    @else
                        <strong>Quantum Soluciones</strong>
                    @endif
                </div>

                <div style="font-size: 8pt; color: #333; line-height: 1.3; margin-top: 10px; padding-left: 10px; text-align: left;">
                    <strong>QUANTUM SOLUCIONES S.A</strong><br>
                    (+505) 7669 8164<br>
                    Quantumsolucionesnic.com<br>
                    Jovanbetanco@quantumsolucionesnic.com<br>
                    Managua, Nicaragua
                </div>

                <div style="margin-top: 30px; padding-left: 10px; text-align: left;">
                    <div style="font-weight: bold; font-size: 9pt; color: #000; margin-bottom: 5px;">Proveedor:</div>
                    <div style="font-size: 8pt; color: #333; line-height: 1.4;">
                        {{ $provider ?? 'SRP' }}
                    </div>

                    <table style="margin-top: 15px; width: 100%; text-align: left;" cellspacing="0" cellpadding="0">
                        <tr>
                            <td style="font-weight: bold; font-size: 8pt; width: 60px;">Contado</td>
                            <td style="border: 1px solid #7c0a02; width: 20px; height: 12px; text-align:center;">{{ $payment === 'Contado' ? 'X' : '' }}</td>
                            <td style="font-weight: bold; font-size: 8pt; width: 60px; text-align: right; padding-right:10px;">Crédito</td>
                            <td style="border: 1px solid #7c0a02; width: 20px; height: 12px; text-align:center;">{{ $payment === 'Crédito' ? 'X' : '' }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </td>
    </tr>
</table>

<div class="section-title">Detalles del pedido</div>

<table class="items-table">
    <thead>
        <tr>
            <th width="8%">Items</th>
            <th width="15%">Numero Parte</th>
            <th width="35%" style="text-align: left;">Descripción</th>
            <th width="12%">Cantidad</th>
            <th width="15%" style="text-align: right;">Valor unitario</th>
            <th width="15%" style="text-align: right;">Valor Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($piezas as $index => $item)
        <tr>
            <td>{{ $index + 1 }}</td>
            <td>{{ $item['codigo'] }}</td>
            <td style="text-align: left;">{{ $item['descripcion'] }}</td>
            <td>{{ $item['cantidad'] }}</td>
            <td style="text-align: right;">${{ number_format($item['p_unitario'], 2) }}</td>
            <td style="text-align: right;">${{ number_format($item['cantidad'] * $item['p_unitario'], 2) }}</td>
        </tr>
        @endforeach

        <!-- Rellenamos filas vacias para recrear la estructura pero completamente en blanco -->
        @for($i = count($piezas) + 1; $i <= 12; $i++)
        <tr>
            <td style="padding: 15px 5px;"></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td style="text-align: right;"></td>
        </tr>
        @endfor
    </tbody>
</table>

<div class="totals-wrapper">
    <table class="totals-table">
        <tr>
            <td width="55%"><b>Costo subtotal:</b></td>
            <td width="45%" style="text-align: right;">${{ number_format($subtotal, 2) }}</td>
        </tr>
        <tr><td colspan="2" class="line-row"></td></tr>
        <tr>
            <td>Impuestos 15%</td>
            <td style="text-align: right;"></td>
        </tr>
        <tr><td colspan="2" class="line-row"></td></tr>
        <tr>
            <td style="font-size: 11pt; padding-top: 5px;">Importe total: </td>
            <td style="text-align: right; font-size: 11pt; font-weight: bold; padding-top: 5px; letter-spacing: 2px;">${{ number_format($total, 2) }}</td>
        </tr>
        <tr><td colspan="2" class="line-row"></td></tr>
    </table>
    <div style="clear: both;"></div>
</div>

<div class="footer-box">
    <div style="font-size: 8pt; margin-bottom: 5px;">Términos y Condiciones:</div>
    <div style="font-size: 8pt;">Envio Aereo Via Fedex</div>
</div>
<div style="border-bottom: 4px solid #1a3b5c; margin-top: 5px;"></div>
