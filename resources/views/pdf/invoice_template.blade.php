<style>
    body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 10pt; color: #222; margin: 0; padding: 0; }
    .accent { color: #7c0a02; }
    .header-table { width: 100%; margin-bottom: 25px; }
    .title { font-size: 18pt; font-weight: bold; color: #222; letter-spacing: 1px; margin-bottom: 8px; }
    .badge { display: inline-block; background: #7c0a02; color: #fff; font-size: 8pt; padding: 4px 12px; border-radius: 3px; letter-spacing: 1px; font-weight: bold; }
    .meta-table { width: 100%; border-collapse: collapse; margin-top: 12px; font-size: 8pt; }
    .meta-table td { padding: 4px 8px; border: 1px solid #e0e0e0; }
    .meta-label { font-weight: bold; background: #f5f5f5; width: 40%; }
    .section-title { font-weight: bold; font-size: 9pt; color: #7c0a02; margin: 18px 0 6px 0; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid #7c0a02; padding-bottom: 3px; }
    .info-text { font-size: 8.5pt; color: #333; line-height: 1.5; }
    .items-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
    .items-table th { font-size: 8pt; font-weight: bold; padding: 8px 6px; border-bottom: 2px solid #7c0a02; background: #f9f9f9; }
    .items-table tbody td { padding: 9px 6px; font-size: 8pt; color: #333; border-bottom: 1px solid #f0f0f0; }
    .items-table tbody tr:nth-child(even) td { background: #fafafa; }
    .totals-wrapper { width: 100%; margin-top: 20px; }
    .totals-table { width: 45%; float: right; font-size: 9pt; border-collapse: collapse; }
    .totals-table td { padding: 7px 6px; }
    .totals-table .total-row td { font-size: 11pt; font-weight: bold; border-top: 2px solid #7c0a02; padding-top: 10px; }
    .footer-box { background: #7c0a02; color: #fff; padding: 12px 15px; margin-top: 40px; font-size: 8pt; border-radius: 3px; }
    .status-badge { font-size: 8pt; font-weight: bold; padding: 3px 8px; border-radius: 3px; }
    .status-pagado { background: #d1fae5; color: #065f46; }
    .status-pendiente { background: #fee2e2; color: #991b1b; }
    .status-parcial { background: #fef3c7; color: #92400e; }
</style>

<table class="header-table" cellpadding="0" cellspacing="0">
    <tr>
        <td width="55%" valign="top">
            <div class="title">FACTURA</div>
            <div>
                <span class="badge">N° {{ $invoice->invoice_number ?: ('INV-' . str_pad($invoice->id, 4, '0', STR_PAD_LEFT)) }}</span>
            </div>

            <table class="meta-table" style="margin-top: 18px; width: 90%;" cellspacing="0">
                <tr>
                    <td class="meta-label">Fecha de Emisión</td>
                    <td>{{ $invoice->date ? $invoice->date->format('d/m/Y') : '—' }}</td>
                </tr>
                <tr>
                    <td class="meta-label">Fecha de Vencimiento</td>
                    <td>{{ $invoice->due_date ? $invoice->due_date->format('d/m/Y') : '—' }}</td>
                </tr>
                <tr>
                    <td class="meta-label">N° Cotización</td>
                    <td>{{ $invoice->cotizacion_ref ?: '—' }}</td>
                </tr>
                <tr>
                    <td class="meta-label">Condición</td>
                    <td>{{ $invoice->condition ?: 'Contado' }}</td>
                </tr>
                <tr>
                    <td class="meta-label">Estado</td>
                    <td>
                        <span class="status-badge status-{{ strtolower($invoice->status) }}">{{ $invoice->status }}</span>
                    </td>
                </tr>
            </table>

            <div class="section-title" style="margin-top: 22px;">Facturar a</div>
            <div class="info-text">
                <strong>{{ optional($invoice->entity)->name ?: 'Cliente General' }}</strong>
            </div>
        </td>
        <td width="45%" valign="top">
            <div style="padding-left: 30px;">
                <div style="padding-left: 10px;">
                    @php($logoBase64 = app(\App\Services\Pdf\PdfDocumentService::class)->logoToBase64())
                    @if($logoBase64)
                        <img src="{{ $logoBase64 }}" width="190">
                    @else
                        <strong>Quantum Soluciones</strong>
                    @endif
                </div>
                <div style="font-size: 8pt; color: #333; line-height: 1.5; margin-top: 10px; padding-left: 10px;">
                    <strong>QUANTUM SOLUCIONES S.A</strong><br>
                    (+505) 7669 8164<br>
                    Quantumsolucionesnic.com<br>
                    Jovanbetanco@quantumsolucionesnic.com<br>
                    Managua, Nicaragua
                </div>
            </div>
        </td>
    </tr>
</table>

<div class="section-title">Descripción del Servicio / Producto</div>

<table class="items-table">
    <thead>
        <tr>
            <th width="46%" style="text-align: left;">Descripción</th>
            <th width="14%" style="text-align: right;">Cantidad</th>
            <th width="20%" style="text-align: right;">Precio unit.</th>
            <th width="20%" style="text-align: right;">Importe</th>
        </tr>
    </thead>
    <tbody>
        @forelse($invoice->saleOrder?->items ?? [] as $item)
            <tr><td>{{ $item->description }}<br><small>{{ $item->part_number }}</small></td><td style="text-align: right;">{{ number_format($item->quantity, 0) }}</td><td style="text-align: right;">{{ $invoice->saleOrder->currency }} {{ number_format($item->unit_price, 2) }}</td><td style="text-align: right;">{{ $invoice->saleOrder->currency }} {{ number_format($item->total_price, 2) }}</td></tr>
        @empty
            <tr><td>{{ $invoice->description }}</td><td style="text-align: right;">1</td><td style="text-align: right;">C$ {{ number_format($invoice->amount_nio, 2) }}</td><td style="text-align: right;">C$ {{ number_format($invoice->amount_nio, 2) }}</td></tr>
        @endforelse
    </tbody>
</table>

<div class="totals-wrapper">
    <table class="totals-table">
        <tr>
            <td width="55%"><b>Monto Bruto:</b></td>
            <td width="45%" style="text-align: right;">C$ {{ number_format($invoice->amount_nio, 2) }}</td>
        </tr>
        <tr>
            <td>Retención:</td>
            <td style="text-align: right; color: #7c0a02;">- C$ {{ number_format($invoice->retention_amount, 2) }}</td>
        </tr>
        @if($invoice->amount_usd)
        <tr>
            <td>Equivalente USD:</td>
            <td style="text-align: right; color: #555;">$ {{ number_format($invoice->amount_usd, 2) }}</td>
        </tr>
        @endif
        <tr class="total-row">
            <td>Neto a Pagar:</td>
            <td style="text-align: right; color: #7c0a02;">C$ {{ number_format($invoice->amount_nio - $invoice->retention_amount, 2) }}</td>
        </tr>
    </table>
    <div style="clear: both;"></div>
</div>

<div class="footer-box">
    <div style="margin-bottom: 4px; font-weight: bold;">Quantum Soluciones S.A — Términos de Pago</div>
    <div>{{ $invoice->condition }} &bull; Gracias por su preferencia. Para cualquier consulta comuníquese al (+505) 7669 8164.</div>
</div>
<div style="border-bottom: 4px solid #1a3b5c; margin-top: 5px;"></div>
