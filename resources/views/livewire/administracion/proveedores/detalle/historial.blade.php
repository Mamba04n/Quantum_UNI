<dl class="grid grid-cols-3 divide-x divide-slate-200 rounded-lg border border-slate-200 bg-slate-50">
    @foreach([['Cotizaciones solicitadas',$selectedSupplier->supplierBids->count()],['Respuestas recibidas',$selectedSupplier->supplierBidResponses->count()],['Órdenes de compra',$selectedSupplier->purchaseOrders->count()]] as [$label,$value])
        <div class="px-5 py-4"><dt class="text-xs text-slate-500">{{ $label }}</dt><dd class="mt-1 text-xl font-semibold tabular-nums text-slate-950">{{ $value }}</dd></div>
    @endforeach
</dl>
<div class="mt-5 overflow-hidden rounded-lg border border-slate-200">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-slate-500"><tr><th class="px-4 py-3 font-medium">Tipo</th><th class="px-4 py-3 font-medium">Referencia</th><th class="px-4 py-3 font-medium">Fecha</th><th class="px-4 py-3 font-medium">Estado</th></tr></thead>
        <tbody class="divide-y divide-slate-100 bg-white">
            @forelse($selectedSupplier->purchaseOrders->take(8) as $order)
                <tr><td class="px-4 py-3 text-slate-600">Orden de compra</td><td class="px-4 py-3 font-medium text-slate-900">{{ $order->reference_number ?? $order->reference ?? '#'.$order->id }}</td><td class="px-4 py-3 text-slate-600">{{ optional($order->created_at)->format('d/m/Y') }}</td><td class="px-4 py-3 text-slate-600">{{ str($order->status ?? 'Sin estado')->replace('_', ' ')->headline() }}</td></tr>
            @empty
                @if($selectedSupplier->supplierBidResponses->isEmpty())<tr><td colspan="4" class="px-5 py-8 text-center text-slate-500">No hay actividad comercial registrada.</td></tr>@endif
            @endforelse
            @foreach($selectedSupplier->supplierBidResponses->take(8) as $response)
                <tr><td class="px-4 py-3 text-slate-600">Respuesta de proveedor</td><td class="px-4 py-3 font-medium text-slate-900">{{ $response->quotationRequest->reference ?? '#'.$response->id }}</td><td class="px-4 py-3 text-slate-600">{{ optional($response->created_at)->format('d/m/Y') }}</td><td class="px-4 py-3 text-slate-600">{{ str($response->status ?? 'registrada')->replace('_', ' ')->headline() }}</td></tr>
            @endforeach
        </tbody>
    </table>
</div>
