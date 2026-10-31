<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #172033; font-size: 10px; }
        .header { width: 100%; border-bottom: 2px solid #172033; padding-bottom: 14px; }
        .title { font-size: 22px; font-weight: bold; letter-spacing: 1px; }
        .muted { color: #64748b; }
        .meta { width: 100%; margin-top: 18px; border-collapse: collapse; }
        .meta td { width: 50%; padding: 8px 10px; border: 1px solid #dbe3ee; }
        .label { display: block; color: #64748b; font-size: 8px; font-weight: bold; text-transform: uppercase; }
        table.items { width: 100%; margin-top: 20px; border-collapse: collapse; }
        .items th { background: #172033; color: white; padding: 9px 8px; text-align: left; font-size: 8px; text-transform: uppercase; }
        .items td { border-bottom: 1px solid #dbe3ee; padding: 10px 8px; }
        .right { text-align: right; }
        .total { width: 42%; margin-left: auto; margin-top: 18px; border-collapse: collapse; }
        .total td { padding: 8px; border-bottom: 1px solid #dbe3ee; }
        .total .grand td { background: #172033; color: white; font-weight: bold; font-size: 12px; }
        .footer { margin-top: 45px; border-top: 1px solid #dbe3ee; padding-top: 10px; color: #64748b; font-size: 8px; }
    </style>
</head>
<body>
    <table class="header"><tr><td>
        <div class="title">FACTURA</div>
        <div class="muted">QUANTUM SOLUCIONES S.A.</div>
    </td><td class="right">
        <strong>N.º {{ $invoice->invoice_number }}</strong><br>
        <span class="muted">DESPACHO {{ $status->order_code }}</span>
    </td></tr></table>

    <table class="meta">
        <tr><td><span class="label">Cliente</span>{{ $status->saleOrder?->client_name ?: $status->customer?->name ?: 'Cliente general' }}</td><td><span class="label">Fecha de emisión</span>{{ $invoice->date->format('d/m/Y') }}</td></tr>
        <tr><td><span class="label">Condición</span>{{ $invoice->condition }}</td><td><span class="label">Vencimiento</span>{{ optional($invoice->due_date)->format('d/m/Y') ?: '—' }}</td></tr>
    </table>

    <table class="items">
        <thead><tr><th>Código</th><th>Producto</th><th class="right">Cantidad</th><th class="right">Precio unit.</th><th class="right">Total</th></tr></thead>
        <tbody>
        @foreach($lines as $line)
            <tr><td>{{ $line['code'] }}</td><td>{{ $line['description'] }}</td><td class="right">{{ number_format($line['quantity'], 2) }}</td><td class="right">C$ {{ number_format($line['unit_price'], 2) }}</td><td class="right">C$ {{ number_format($line['total'], 2) }}</td></tr>
        @endforeach
        </tbody>
    </table>

    <table class="total"><tr><td>SUBTOTAL</td><td class="right">C$ {{ number_format($invoice->subtotal, 2) }}</td></tr><tr><td>IVA</td><td class="right">C$ 0.00</td></tr><tr class="grand"><td>TOTAL</td><td class="right">C$ {{ number_format($invoice->total, 2) }}</td></tr></table>
    <div class="footer">Documento generado desde el despacho agrupado. Incluye únicamente las partidas y cantidades enviadas en esta salida.</div>
</body>
</html>
