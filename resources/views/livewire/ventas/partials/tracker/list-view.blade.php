<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-8">
    <div class="p-4 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
        <div class="relative w-72">
            <input type="text" wire:model.live.debounce.500ms="search" data-testid="tracker-search-input" placeholder="Buscar por cliente o referencia..."
                   class="w-full pl-9 pr-4 py-2 border border-gray-200 rounded-xl text-xs focus:ring-slate-500 focus:border-slate-500 bg-white shadow-sm">
            <svg class="w-4 h-4 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
        </div>
        <div class="text-[10px] font-bold text-gray-500 uppercase tracking-widest">
            {{ count($listItems) }} Registros
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-[10px] font-black text-gray-500 uppercase tracking-wider border-b border-gray-100">
                <tr>
                    <th class="px-6 py-4">Seguimiento</th>
                    <th class="px-6 py-4">Referencia</th>
                    <th class="px-6 py-4">Cliente</th>
                    <th class="px-6 py-4">Fase actual</th>
                    <th class="px-6 py-4">Estado</th>
                    <th class="px-6 py-4">Fecha</th>
                    <th class="px-6 py-4 text-right">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($listItems as $item)
                    <tr wire:click="$dispatch('open-tracker-details', { id: {{ $item['id'] }}, type: '{{ $item['type'] }}', orderStatusItemId: {{ $item['order_status_item_id'] ?? 'null' }} })" data-testid="tracker-row-{{ $item['tracking_code'] ?? $item['reference'] }}"
                        class="hover:bg-slate-50/50 cursor-pointer transition-colors group">
                        <td class="px-6 py-4 font-black text-slate-800 text-xs group-hover:text-indigo-600 transition-colors">
                            {{ $item['tracking_code'] ?? $item['reference'] }}
                        </td>
                        <td class="px-6 py-4 font-bold text-slate-600 text-xs">{{ $item['reference'] }}</td>
                        <td class="px-6 py-4 font-bold text-slate-700 text-xs">
                            {{ $item['client'] }}
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-[10px] font-bold text-slate-700 uppercase tracking-wider border px-2 py-1 rounded bg-slate-50 border-slate-200">
                                {{ str_replace('_', ' ', $item['phase'] ?? '-') }}
                            </span>
                            @if(!empty($item['product']))
                                <p class="mt-1 text-[10px] font-bold text-slate-400">{{ $item['product'] }} · {{ $item['progress'] ?? 0 }}%</p>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-[10px] font-bold text-gray-600 uppercase tracking-wider border px-2 py-1 rounded bg-gray-50 border-gray-200">
                                {{ str_replace('_', ' ', $item['status']) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-xs font-medium text-gray-500">
                            {{ \Carbon\Carbon::parse($item['date'])->format('d M, Y') }}
                        </td>
                        <td class="px-6 py-4 text-right font-black text-slate-900 text-sm">
                            ${{ number_format($item['total'], 2) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-gray-400 font-medium text-sm">
                            No se encontraron registros.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
