<div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5">
    <h2 class="text-sm font-black text-gray-900 uppercase tracking-widest">Top clientes</h2>
    <div class="mt-3 divide-y divide-gray-100">
        @forelse(data_get($dashboard, 'salesVolume.topCustomers', []) as $row)
            <div class="py-3 flex items-center justify-between gap-3">
                <span class="text-sm font-semibold text-gray-600 truncate">{{ $row->label }}</span>
                <span class="text-sm font-black text-gray-900">C$ {{ number_format((float) $row->amount, 2) }}</span>
            </div>
        @empty
            <p class="text-xs text-gray-400 py-4">Sin datos.</p>
        @endforelse
    </div>
</div>
