<section class="rounded-2xl border border-gray-100 p-5">
    <h4 class="text-[11px] font-black uppercase tracking-widest text-gray-400 mb-4">Datos personales</h4>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-xs font-bold text-gray-600 mb-1">Nombre visible <span class="text-rose-500">*</span></label>
            <input wire:model="name" type="text" class="w-full bg-gray-50 border-transparent rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all">
            @error('name') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-600 mb-1">Telefono</label>
            <input wire:model="phone" type="text" class="w-full bg-gray-50 border-transparent rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all">
            @error('phone') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-600 mb-1">Nombre</label>
            <input wire:model="first_name" type="text" class="w-full bg-gray-50 border-transparent rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all">
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-600 mb-1">Apellido</label>
            <input wire:model="last_name" type="text" class="w-full bg-gray-50 border-transparent rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all">
        </div>
        <div class="md:col-span-2">
            <label class="block text-xs font-bold text-gray-600 mb-1">Direccion</label>
            <input wire:model="address" type="text" class="w-full bg-gray-50 border-transparent rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all">
        </div>
    </div>
</section>
