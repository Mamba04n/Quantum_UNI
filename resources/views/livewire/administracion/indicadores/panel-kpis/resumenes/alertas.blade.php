<div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5">
    <h2 class="text-sm font-black text-gray-900 uppercase tracking-widest">Alertas</h2>
    <div class="mt-3 space-y-3">
        <div class="rounded-lg border border-amber-100 bg-amber-50 p-3 text-xs text-amber-800">{{ $dashboard['notes']['delivery_time'] }}</div>
        <div class="rounded-lg border border-amber-100 bg-amber-50 p-3 text-xs text-amber-800">{{ $dashboard['notes']['low_stock'] }}</div>
        <div class="divide-y divide-gray-100">
            @foreach(data_get($dashboard, 'inventoryCost.lowStockProducts', []) as $row)
                <div class="py-2 flex items-center justify-between gap-3 text-xs">
                    <span class="font-semibold text-gray-600 truncate">{{ $row->name }}</span>
                    <span class="font-black text-rose-700">{{ $row->stock_on_hand }} und.</span>
                </div>
            @endforeach
        </div>
    </div>
</div>
