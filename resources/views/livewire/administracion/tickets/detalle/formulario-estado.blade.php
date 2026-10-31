<form wire:submit="saveState" class="rounded-xl border border-gray-100 p-4 space-y-3">
    <div class="flex items-center gap-3">
        <div class="flex-1">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Estado del Ticket</label>
            <select wire:model="status" class="mt-1 w-full rounded-xl border-gray-200 text-sm font-bold text-slate-700">
                @foreach($statusLabels as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="pt-5">
            <button type="submit" class="px-4 py-2 rounded-xl bg-slate-900 text-white text-sm font-bold shadow-sm hover:bg-black transition-colors">Guardar</button>
        </div>
    </div>
</form>
