<div>
    <div class="mb-4 flex gap-2">
        <input wire:model="{{ $input }}" type="text" placeholder="{{ $placeholder }}" class="h-11 flex-1 rounded-lg border-slate-300 text-sm">
        <button wire:click="{{ $action }}" class="rounded-lg bg-slate-900 px-5 py-2 text-sm font-semibold text-white hover:bg-slate-800">Agregar</button>
    </div>

    <div class="flex flex-wrap gap-2">
        @forelse($items as $item)
            <span class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                {{ $item->{$field} }}
                <button wire:click="deleteChild('{{ addslashes($model) }}', {{ $item->id }})" class="text-slate-400 hover:text-rose-700" aria-label="Eliminar">×</button>
            </span>
        @empty
            <p class="py-6 text-sm text-slate-500">Sin datos registrados.</p>
        @endforelse
    </div>
</div>
