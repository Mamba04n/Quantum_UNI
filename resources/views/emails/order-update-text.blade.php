Quantum Soluciones
{{ $heading }}
Pedido: {{ $order?->reference }}
@if(!empty($previewNotice))PRUEBA: {{ $previewNotice }}. No corresponde a un pedido real.@endif

Hola, {{ $order?->client_name ?: 'cliente' }}.
@if($phaseLabel)Etapa: {{ $phaseLabel }}@endif
Estado: {{ $statusLabel }}
{{ $statusMessage }}
@if($isItemUpdate)Este aviso corresponde únicamente al producto indicado.@endif

@foreach($orderItems as $item)
{{ $item['description'] }}
{{ $item['short_description'] ?? '' }}
Parte: {{ $item['part_number'] ?: 'Por confirmar' }}
Cantidad: {{ $item['quantity'] }}
@if($showTotals)Subtotal: {{ $currencyLabel }}{{ number_format($item['total_price'], 2) }}@endif

@endforeach
@if($showTotals)Total del pedido: {{ $currencyLabel }}{{ number_format((float)$order->total_amount, 2) }}@endif
@if($isPackingList)Adjuntamos el packing list de este despacho.@endif

Ver mi pedido: {{ $portalUrl }}
Si tienes dudas, responde a este correo.
Quantum Soluciones
