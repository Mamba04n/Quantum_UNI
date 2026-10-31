<div class="overflow-x-auto">
    <table class="w-full whitespace-nowrap text-left text-sm">
        <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase tracking-wider text-gray-500"><tr>
            <th class="px-5 py-3 font-semibold">Parte / descripción</th>
            @unless($inDetail)<th class="px-5 py-3 font-semibold">Proveedor / fecha</th>@endunless
            <th class="px-5 py-3 text-right font-semibold">Cantidad</th><th class="px-5 py-3 text-right font-semibold">Unitario USD</th><th class="px-5 py-3 text-right font-semibold">Total USD</th><th class="px-5 py-3 font-semibold">Disponibilidad</th><th class="px-5 py-3 font-semibold">Acción</th>
        </tr></thead>
        <tbody class="divide-y divide-gray-50">
        @forelse($rows as $item)
            <tr wire:key="price-{{ $inDetail ? 'detail' : 'list' }}-{{ $item->id }}" class="transition-colors hover:bg-gray-50/50">
                <td class="px-5 py-3"><div class="font-semibold text-gray-900">{{ $item->part_number }}</div><div class="mt-0.5 max-w-sm whitespace-normal text-gray-500">{{ $item->description }}</div></td>
                @unless($inDetail)<td class="px-5 py-3"><div class="font-medium text-gray-900">{{ $item->quotation->supplier->name }}</div><div class="mt-0.5 text-gray-500">{{ $item->quotation->quoted_at->format('d/m/Y') }}@if($item->quotation->valid_until?->lt(today())) · Vigencia vencida @endif</div></td>@endunless
                <td class="px-5 py-3 text-right tabular-nums">{{ rtrim(rtrim(number_format((float)$item->quantity, 3, '.', ','), '0'), '.') }} {{ $item->unit }}</td>
                <td class="px-5 py-3 text-right font-semibold tabular-nums">{{ number_format((float)$item->unit_price, 4) }}</td><td class="px-5 py-3 text-right tabular-nums">{{ number_format((float)$item->total_price, 2) }}</td>
                <td class="px-5 py-3"><span class="{{ $item->availability === 'available' ? 'text-green-700' : ($item->availability === 'unavailable' ? 'text-red-700' : 'text-gray-500') }}">{{ ['pending' => 'Por confirmar', 'available' => 'Disponible al consultar', 'unavailable' => 'No disponible al consultar'][$item->availability] ?? 'Por confirmar' }}</span>@if($item->availability_checked_at)<div class="mt-0.5 text-xs text-gray-500">Consulta: {{ $item->availability_checked_at->format('d/m/Y H:i') }}</div>@endif @if($inDetail && $item->availability_notes)<p class="mt-1 max-w-xs whitespace-normal text-gray-500">{{ $item->availability_notes }}</p>@endif</td>
                <td class="px-5 py-3">@if($inDetail) @can('confirmAvailability', \App\Models\SupplierPriceQuotation::class)<button type="button" wire:click="check({{ $item->id }})" class="font-semibold text-slate-700 underline">Consultar</button>@endcan @else<button type="button" wire:click="detail({{ $item->supplier_price_quotation_id }})" class="font-semibold text-slate-700 underline">Ver oferta</button>@endif</td>
            </tr>
        @empty
            <tr><td colspan="{{ $inDetail ? 6 : 7 }}" class="px-5 py-8 text-center text-sm text-gray-500">No se encontraron precios registrados.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
