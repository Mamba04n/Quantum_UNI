<section class="xl:col-span-1 bg-white border border-gray-100 shadow-sm rounded-2xl p-5">
    <label class="block text-xs font-extrabold text-gray-500 uppercase tracking-wider mb-2">Modulo</label>
    <select wire:model.live="module"
            class="w-full bg-gray-50 border-transparent rounded-xl py-3 px-3 text-sm focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all">
        @foreach($modules as $exportModule)
            <option value="{{ $exportModule['key'] }}">
                {{ $exportModule['label'] }} ({{ $exportModule['count'] }})
            </option>
        @endforeach
    </select>

    @php($selected = collect($modules)->firstWhere('key', $module))
    <div class="mt-5 rounded-xl bg-slate-50 border border-slate-100 p-4">
        <p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Archivo</p>
        <p class="mt-1 text-sm font-bold text-slate-800 break-all">{{ $selected['filename'] ?? 'exportacion.xlsx' }}</p>
        <p class="mt-3 text-xs text-slate-500">
            Registros disponibles: <span class="font-bold text-slate-700">{{ $selected['count'] ?? 0 }}</span>
        </p>
    </div>
</section>
