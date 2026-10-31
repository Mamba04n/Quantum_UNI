<section class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-sm font-black text-gray-900 uppercase tracking-widest">Inventario</h3>
        <span class="text-xs text-gray-400">{{ $movementLogs->count() }} registros</span>
    </div>
    <div class="divide-y divide-gray-100 max-h-[420px] overflow-y-auto">
        @forelse($movementLogs as $move)
            @php
                $thumb = $move->attachment_path
                    ? route('private.stock-move-attachment', $move)
                    : null;
                $type = $move->movement_type?->value ?? ($move->quantity > 0 ? 'in' : 'out');
            @endphp
            <div class="px-5 py-3 text-xs">
                <div class="flex items-center justify-between gap-3">
                    <span class="font-bold text-gray-800 truncate">{{ $move->product?->name ?? 'N/A' }}</span>
                    <span class="{{ $type === 'in' ? 'text-emerald-700' : 'text-rose-700' }} font-black">{{ $type === 'in' ? '+' : '-' }}{{ abs((float) $move->quantity) }}</span>
                </div>
                <div class="mt-1 flex items-center justify-between text-gray-400">
                    <span>{{ optional($move->movement_date)->format('d/m/Y') }}</span>
                    <span>{{ $move->user?->name ?? 'Sistema' }}</span>
                </div>
                @if($thumb)
                    <button type="button" @click="imageModal = '{{ $thumb }}'" class="mt-2 text-[11px] font-bold text-slate-600">Ver adjunto</button>
                @endif
            </div>
        @empty
            <div class="px-5 py-8 text-center text-xs text-gray-400">Sin movimientos.</div>
        @endforelse
    </div>
</section>
