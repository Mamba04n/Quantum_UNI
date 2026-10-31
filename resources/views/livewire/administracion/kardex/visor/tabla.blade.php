<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm whitespace-nowrap">
            <thead class="bg-gray-50 text-gray-500">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold">Fecha</th>
                    <th class="px-4 py-3 text-left font-semibold">Producto</th>
                    <th class="px-4 py-3 text-left font-semibold">SKU</th>
                    <th class="px-4 py-3 text-left font-semibold">Tipo</th>
                    <th class="px-4 py-3 text-right font-semibold">Cantidad</th>
                    <th class="px-4 py-3 text-right font-semibold">Costo</th>
                    <th class="px-4 py-3 text-right font-semibold">Saldo</th>
                    <th class="px-4 py-3 text-left font-semibold">Usuario</th>
                    <th class="px-4 py-3 text-center font-semibold">Imagen</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($moves as $move)
                    @php
                        $thumb = $move->attachment_path
                            ? route('private.stock-move-attachment', $move)
                            : null;
                        $type = $move->movement_type?->value ?? ($move->quantity > 0 ? 'in' : 'out');
                    @endphp
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-4 py-3 text-gray-600 font-mono">{{ optional($move->movement_date)->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 font-semibold text-gray-900">{{ $move->product?->name ?? 'N/A' }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $move->product?->sku ?? '-' }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex px-2 py-1 rounded-full text-xs font-bold {{ $type === 'in' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $type === 'in' ? 'Entrada' : 'Salida' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right font-mono">{{ $move->quantity }}</td>
                        <td class="px-4 py-3 text-right font-mono">${{ number_format((float) $move->unit_cost, 2) }}</td>
                        <td class="px-4 py-3 text-right font-mono font-bold text-gray-900">{{ $move->computed_balance }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $move->user?->name ?? 'Sistema' }}</td>
                        <td class="px-4 py-3 text-center">
                            @if($thumb)
                                <button type="button" @click="imageModal = '{{ $thumb }}'" class="inline-flex items-center gap-2 text-indigo-600 font-bold text-xs">
                                    <img src="{{ $thumb }}" alt="Adjunto" loading="lazy" class="w-10 h-10 rounded object-cover border border-gray-200">
                                    Ver
                                </button>
                            @else
                                <span class="text-gray-300">-</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-6 py-14 text-center text-gray-500">Sin movimientos para los filtros seleccionados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($moves->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">
            {{ $moves->links() }}
        </div>
    @endif
</div>
