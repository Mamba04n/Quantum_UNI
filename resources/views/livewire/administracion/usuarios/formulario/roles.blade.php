<section class="rounded-2xl border border-gray-100 p-5">
    <h4 class="text-[11px] font-black uppercase tracking-widest text-gray-400 mb-4">Roles y permisos</h4>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
        @foreach($roles as $key => $label)
            @if($key !== 'admin' || auth()->user()->id === 1)
                <label class="flex items-center gap-2 bg-gray-50 rounded-xl px-3 py-2 text-sm font-semibold text-gray-700">
                    <input type="checkbox" wire:model.live="selectedRoles" value="{{ $key }}" class="rounded border-gray-300 text-slate-800 focus:ring-slate-500">
                    <span>{{ $label }}</span>
                </label>
            @endif
        @endforeach
    </div>
    @error('selectedRoles') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
    @error('selectedRoles.*') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
</section>
