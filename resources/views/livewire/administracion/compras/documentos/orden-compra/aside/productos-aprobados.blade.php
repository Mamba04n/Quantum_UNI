<aside class="space-y-6">
    <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-black text-slate-950">Productos aprobados</h2>
        <p class="mt-1 text-sm text-slate-500">{{ $items->count() }} partida(s) seleccionadas para compra.</p>
        <div class="mt-4 max-h-[680px] space-y-3 overflow-y-auto pr-1">
            @forelse($items as $index => $item)
                <div class="rounded-xl border border-slate-200 p-3">
                    <div class="flex justify-between gap-3">
                        <p class="text-sm font-black text-slate-900">{{ $index + 1 }}. {{ $item['part_name'] ?: 'Producto' }}</p>
                        <span class="text-xs font-black text-slate-700">{{ $item['currency'] }} {{ number_format((float) $item['subtotal'], 2) }}</span>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">PN: {{ $item['part_number'] ?: '-' }} / Marca: {{ $item['brand'] ?: '-' }}</p>
                    <p class="text-xs text-slate-500">Proveedor: {{ $item['supplier_name'] ?: 'Pendiente' }}</p>
                    <p class="text-xs text-slate-500">Cant: {{ number_format((float) $item['quantity'], 0) }} / Unit: {{ $item['currency'] }} {{ number_format((float) $item['unit_cost'], 2) }}</p>
                </div>
            @empty
                <p class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm font-bold text-amber-800">No hay partidas listas para el documento.</p>
            @endforelse
        </div>
    </section>
</aside>
