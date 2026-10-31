<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>RFQ {{ $rfqId }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; color: #1f2937; }
        .header { width: 100%; border-bottom: 3px solid #0f172a; padding-bottom: 12px; margin-bottom: 18px; }
        .logo { width: 210px; }
        .title { font-size: 22px; font-weight: bold; color: #0f172a; text-align: right; }
        .muted { color: #64748b; }
        .box { border: 1px solid #e5e7eb; padding: 10px; margin-bottom: 12px; }
        .section-title { font-weight: bold; color: #0f172a; text-transform: uppercase; font-size: 9px; letter-spacing: .6px; margin-bottom: 6px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #0f172a; color: #fff; padding: 7px; font-size: 9px; text-align: left; }
        td { border: 1px solid #e5e7eb; padding: 7px; vertical-align: top; }
        .terms td { border: none; padding: 4px 0; }
        .footer { position: fixed; bottom: 0; left: 0; right: 0; border-top: 1px solid #e5e7eb; padding-top: 8px; font-size: 8px; color: #64748b; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td width="55%">
                @php($logoBase64 = $logoBase64 ?? app(\App\Services\Pdf\PdfDocumentService::class)->logoToBase64())
                @if($logoBase64)
                    <img src="{{ $logoBase64 }}" class="logo">
                @else
                    <strong>Quantum Soluciones</strong>
                @endif
                <div class="muted" style="margin-top: 8px;">
                    Quantum Soluciones S.A.<br>
                    RUC J0310000475024<br>
                    ventas@quantumsolucionesnic.com
                </div>
            </td>
            <td width="45%" class="title">
                Solicitud de Cotizacion (RFQ)
                <div class="muted" style="font-size: 11px; margin-top: 8px;">{{ $rfqId }}</div>
                <div class="muted" style="font-size: 10px;">Fecha: {{ optional($request->date)->format('d/m/Y') ?: now()->format('d/m/Y') }}</div>
            </td>
        </tr>
    </table>

    <table style="margin-bottom: 14px;">
        <tr>
            <td width="50%" class="box">
                <div class="section-title">Datos de Quantum</div>
                Colonia Centroamericana, Managua<br>
                Tel: +505 7669 8164 | +505 5744 5963<br>
                Responsable: {{ $request->user->name ?? 'Ventas' }}
            </td>
            <td width="50%" class="box">
                <div class="section-title">Datos del proveedor</div>
                Proveedor seleccionado: Pendiente<br>
                Contacto: Pendiente<br>
                Correo: Pendiente
            </td>
        </tr>
    </table>

    <div class="section-title">Partidas solicitadas / respuesta del proveedor</div>
    <table>
        <thead>
            <tr>
                <th width="7%">Cant.</th>
                <th width="8%">Unidad</th>
                <th width="28%">Descripcion</th>
                <th width="14%">No. parte</th>
                <th width="10%">Marca</th>
                <th width="11%">Precio unit.</th>
                <th width="10%">Entrega</th>
                <th width="12%">Observacion</th>
            </tr>
        </thead>
        <tbody>
            @foreach($request->items as $item)
                <tr>
                    <td>{{ number_format((float) $item->quantity, 2) }}</td>
                    <td>Und</td>
                    <td>{{ $item->description }}</td>
                    <td>{{ $item->part_number ?: 'N/D' }}</td>
                    <td>{{ $item->brand ?: 'N/D' }}</td>
                    <td></td>
                    <td></td>
                    <td>{{ $item->internal_request_ref ?: '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table style="margin-top: 14px;">
        <tr>
            <td width="50%" class="box">
                <div class="section-title">Terminos solicitados</div>
                <table class="terms">
                    <tr><td width="36%"><strong>Entrega:</strong></td><td>Indicar disponibilidad y lead time.</td></tr>
                    <tr><td><strong>Garantia:</strong></td><td>Indicar cobertura y vigencia.</td></tr>
                    <tr><td><strong>Pago:</strong></td><td>{{ $request->payment_terms ?: 'Indicar condiciones.' }}</td></tr>
                    <tr><td><strong>Moneda:</strong></td><td>Indicar moneda ofertada.</td></tr>
                    <tr><td><strong>Validez:</strong></td><td>Minimo 15 dias calendario.</td></tr>
                </table>
            </td>
            <td width="50%" class="box">
                <div class="section-title">Respuesta del proveedor</div>
                <table class="terms">
                    <tr><td width="36%"><strong>Proveedor:</strong></td><td>________________________________</td></tr>
                    <tr><td><strong>Contacto:</strong></td><td>________________________________</td></tr>
                    <tr><td><strong>Correo:</strong></td><td>________________________________</td></tr>
                    <tr><td><strong>Telefono:</strong></td><td>________________________________</td></tr>
                    <tr><td><strong>Total ofertado:</strong></td><td>________________________________</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="box">
        <div class="section-title">Condiciones de respuesta</div>
        Esta RFQ no constituye orden de compra. Quantum Soluciones podra aceptar total o parcialmente la oferta recibida.
        Adjuntar proforma, ficha tecnica o evidencia de disponibilidad cuando aplique.
    </div>

    <div class="box">
        <div class="section-title">Notas especiales</div>
        {!! nl2br(e($request->notes ?: 'Favor adjuntar proforma o ficha tecnica cuando aplique.')) !!}
    </div>

    <div style="margin-top: 34px;">
        Responsable: ________________________________
    </div>

    <div class="footer">
        RFQ para proveedores. Este documento no constituye oferta comercial al cliente.
    </div>
</body>
</html>
