<x-mail::message>
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 0 0 24px;">
<tr>
<td style="vertical-align: top;">
<img src="{{ $logoUrl }}" alt="{{ config('app.name', 'Quantum Soluciones') }}" width="178" style="display: block; width: 178px; max-width: 72%; height: auto;">
</td>
<td style="vertical-align: top; text-align: right;">
<div style="font-size: 11px; color: #6b7280; text-transform: uppercase;">Packing list</div>
<div style="font-size: 18px; font-weight: 700; color: #111827;">{{ $orderStatus->order_code }}</div>
</td>
</tr>
</table>

Hola {{ $order?->client_name ?: 'cliente' }},

Tu pedido ya salio de bodega y se genero el packing list oficial. Adjuntamos el PDF con el resumen de productos despachados, cantidades y fotografias de referencia.

@if(! empty($items))
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="border-collapse: collapse; border: 1px solid #d9dde3; margin: 18px 0 24px;">
<tr>
<td colspan="4" style="background: #f5f6f8; border-bottom: 1px solid #d9dde3; padding: 12px 14px; font-size: 13px; font-weight: 700; color: #111827; text-transform: uppercase;">Productos despachados</td>
</tr>
@foreach($items as $item)
<tr>
<td width="72" style="width: 72px; padding: 12px 10px 12px 14px; border-bottom: 1px solid #e5e7eb; vertical-align: top;">
@if($item['image_src'])
<img src="{{ $item['image_src'] }}" alt="{{ $item['description'] }}" width="56" height="56" style="display: block; width: 56px; height: 56px; object-fit: cover; border: 1px solid #d9dde3;">
@else
<div style="width: 56px; height: 56px; border: 1px solid #d9dde3; background: #f5f6f8; color: #9ca3af; font-size: 10px; font-weight: 700; line-height: 56px; text-align: center;">IMG</div>
@endif
</td>
<td style="padding: 12px 10px; border-bottom: 1px solid #e5e7eb; vertical-align: top;">
<div style="font-size: 14px; color: #111827; font-weight: 700;">{{ $item['description'] }}</div>
<div style="font-size: 12px; color: #6b7280; margin-top: 4px;">Parte: {{ $item['part_number'] ?: '-' }}</div>
</td>
<td width="90" style="width: 90px; padding: 12px 10px; border-bottom: 1px solid #e5e7eb; text-align: center;">
<div style="font-size: 11px; color: #6b7280; text-transform: uppercase;">Despachado</div>
<div style="font-size: 14px; color: #111827; font-weight: 700;">{{ number_format($item['quantity_dispatched'], 0) }}</div>
</td>
<td width="90" style="width: 90px; padding: 12px 14px 12px 10px; border-bottom: 1px solid #e5e7eb; text-align: center;">
<div style="font-size: 11px; color: #6b7280; text-transform: uppercase;">Kardex out</div>
<div style="font-size: 14px; color: #111827; font-weight: 700;">{{ number_format($item['quantity_kardex_output'], 0) }}</div>
</td>
</tr>
@endforeach
</table>
@endif

<x-mail::button :url="$portalUrl">
Consultar pedido
</x-mail::button>

Equipo de Quantum Soluciones
</x-mail::message>
