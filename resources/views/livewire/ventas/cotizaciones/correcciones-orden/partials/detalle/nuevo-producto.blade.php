<section class="border border-dashed border-slate-300 rounded-xl p-6 bg-slate-50">
    <div class="flex items-center justify-between mb-4">
        <h4 class="text-xs font-black uppercase tracking-widest text-slate-500">Añadir Nuevo Producto</h4>
        <a href="{{ route('admin.suppliers') }}" target="_blank" class="text-[10px] font-bold text-indigo-600 hover:text-indigo-800 flex items-center gap-1 bg-indigo-50 px-2 py-1 rounded-md transition">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Crear Nuevo Proveedor
        </a>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-6 gap-4 items-end">
        <div class="md:col-span-2">
            <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Buscar Producto Existente</label>
            <select wire:model.live="newItem.product_id" class="w-full rounded-xl border-slate-200 text-xs shadow-sm">
                <option value="">Selecciona (Opcional)</option>
                @foreach($products as $p)
                    <option value="{{ $p->id }}">{{ $p->part_number }} - {{ $p->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="md:col-span-2">
            <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Proveedor <span class="text-rose-500">*</span></label>
            <select wire:model="newItem.selected_supplier_id" class="w-full rounded-xl border-slate-200 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Selecciona un proveedor</option>
                @foreach($suppliers as $supplier)
                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                @endforeach
            </select>
            @error('newItem.selected_supplier_id') <span class="text-rose-500 text-[10px] mt-1">{{ $message }}</span> @enderror
        </div>
        <div class="md:col-span-2">
            <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Nombre</label>
            <input type="text" wire:model="newItem.part_name" class="w-full rounded-xl border-slate-200 text-xs shadow-sm" placeholder="Ej. Filtro...">
            @error('newItem.part_name') <span class="text-rose-500 text-[10px] mt-1">{{ $message }}</span> @enderror
        </div>
        <div class="md:col-span-2">
            <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Codigo</label>
            <input type="text" wire:model="newItem.part_code" class="w-full rounded-xl border-slate-200 text-xs shadow-sm" placeholder="Ej. FL-123">
            @error('newItem.part_code') <span class="text-rose-500 text-[10px] mt-1">{{ $message }}</span> @enderror
        </div>
        <div class="md:col-span-1">
            <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Cantidad</label>
            <input type="number" wire:model="newItem.quantity" class="w-full rounded-xl border-slate-200 text-xs shadow-sm" min="1">
        </div>
        <div class="md:col-span-1">
            <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Costo U. ($)</label>
            <input type="number" step="0.01" wire:model="newItem.unit_cost" class="w-full rounded-xl border-slate-200 text-xs shadow-sm">
        </div>
        <div class="md:col-span-1">
            <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Precio U. (C$)</label>
            <input type="number" step="0.01" wire:model="newItem.unit_price" class="w-full rounded-xl border-slate-200 text-xs shadow-sm">
        </div>
        <div class="md:col-span-1 text-right">
            <button type="button" wire:click="addNewItem" class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2 text-xs font-black text-white hover:bg-slate-800">
                Añadir
            </button>
        </div>
    </div>
</section>
