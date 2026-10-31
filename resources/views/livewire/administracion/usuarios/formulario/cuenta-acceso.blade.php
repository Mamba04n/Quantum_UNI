<section class="rounded-2xl border border-gray-100 p-5">
    <h4 class="text-[11px] font-black uppercase tracking-widest text-gray-400 mb-4">Cuenta de acceso</h4>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-xs font-bold text-gray-600 mb-1">Correo electronico <span class="text-rose-500">*</span></label>
            <input wire:model="email" type="email" class="w-full bg-gray-50 border-transparent rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all">
            @error('email') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-600 mb-1">Contrasena {{ $editingId ? '(dejar vacio)' : '*' }}</label>
            <input wire:model="password" type="password" class="w-full bg-gray-50 border-transparent rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all">
            @error('password') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
        </div>
    </div>
</section>
