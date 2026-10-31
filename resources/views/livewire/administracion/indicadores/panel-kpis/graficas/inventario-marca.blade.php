<div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5">
    <h2 class="text-sm font-black text-gray-900 uppercase tracking-widest">Inventario por marca</h2>
    <div class="mt-5 space-y-3">
        @php $inventoryBrands = collect(data_get($dashboard, 'inventoryCost.topBrands', [])); $maxInventory = max(1, (float) $inventoryBrands->max('amount')); @endphp
        @forelse($inventoryBrands as $row)
            <div class="grid grid-cols-[120px_1fr_110px] items-center gap-3 text-xs">
                <span class="font-bold text-gray-500 truncate">{{ $row->label }}</span>
                <div class="h-2.5 bg-gray-100 rounded-full overflow-hidden">
                    <div class="h-full bg-rose-700 rounded-full" style="width: {{ max(2, ((float) $row->amount / $maxInventory) * 100) }}%"></div>
                </div>
                <span class="text-right font-black text-gray-900">C$ {{ number_format((float) $row->amount, 2) }}</span>
            </div>
        @empty
            <p class="text-sm text-gray-400 py-5 text-center">Sin inventario valorizado.</p>
        @endforelse
    </div>
</div>
