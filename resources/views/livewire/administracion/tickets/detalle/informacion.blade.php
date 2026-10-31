<div class="grid grid-cols-2 gap-3 text-sm">
    <div><p class="text-xs text-gray-400 font-bold">Cliente</p><p class="font-semibold">{{ $selectedTicket->customer?->name }}</p></div>
    <div><p class="text-xs text-gray-400 font-bold">Tipo</p><p>{{ $typeLabels[$selectedTicket->type] ?? $selectedTicket->type }}</p></div>
    <div><p class="text-xs text-gray-400 font-bold">Producto</p><p>{{ $selectedTicket->product?->name ?? 'No aplica' }}</p></div>
    <div><p class="text-xs text-gray-400 font-bold">SKU</p><p class="font-mono">{{ $selectedTicket->related_sku ?: ($selectedTicket->product?->sku ?? '-') }}</p></div>
    <div><p class="text-xs text-gray-400 font-bold">Maquinaria cliente</p><p>{{ $selectedTicket->clientMachine?->name ?? 'No aplica' }}</p></div>
    @if($selectedTicket->stockMove)
        <div><p class="text-xs text-gray-400 font-bold">Kardex</p><p class="font-mono">{{ $selectedTicket->stockMove->reference }}</p></div>
    @endif
    @if($selectedTicket->quotationRequest)
        <div><p class="text-xs text-gray-400 font-bold">Ventas</p><p class="font-mono">{{ $selectedTicket->quotationRequest->reference }}</p></div>
    @endif
</div>
