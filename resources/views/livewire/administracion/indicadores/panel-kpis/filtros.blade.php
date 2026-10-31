<section class="bg-white rounded-lg border border-gray-200 shadow-sm p-4">
    <div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-7 gap-3">
        <div>
            <label class="text-[10px] font-black uppercase text-gray-400">Desde</label>
            <input type="date" wire:model.live="dateFrom" class="mt-1 w-full rounded-lg border-gray-200 text-sm focus:border-slate-700 focus:ring-slate-700">
        </div>
        <div>
            <label class="text-[10px] font-black uppercase text-gray-400">Hasta</label>
            <input type="date" wire:model.live="dateTo" class="mt-1 w-full rounded-lg border-gray-200 text-sm focus:border-slate-700 focus:ring-slate-700">
        </div>
        <div>
            <label class="text-[10px] font-black uppercase text-gray-400">Cliente</label>
            <select wire:model.live="customerId" class="mt-1 w-full rounded-lg border-gray-200 text-sm focus:border-slate-700 focus:ring-slate-700">
                <option value="">Todos</option>
                @foreach($options['customers'] as $customer)
                    <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-[10px] font-black uppercase text-gray-400">Proveedor</label>
            <select wire:model.live="supplierId" class="mt-1 w-full rounded-lg border-gray-200 text-sm focus:border-slate-700 focus:ring-slate-700">
                <option value="">Todos</option>
                @foreach($options['suppliers'] as $supplier)
                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-[10px] font-black uppercase text-gray-400">Marca</label>
            <select wire:model.live="brand" class="mt-1 w-full rounded-lg border-gray-200 text-sm focus:border-slate-700 focus:ring-slate-700">
                <option value="">Todas</option>
                @foreach($options['brands'] as $optionBrand)
                    <option value="{{ $optionBrand }}">{{ $optionBrand }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-[10px] font-black uppercase text-gray-400">Vendedor</label>
            <select wire:model.live="sellerId" class="mt-1 w-full rounded-lg border-gray-200 text-sm focus:border-slate-700 focus:ring-slate-700">
                <option value="">Todos</option>
                @foreach($options['sellers'] as $seller)
                    <option value="{{ $seller->id }}">{{ $seller->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end">
            <button wire:click="resetFilters" class="w-full px-4 py-2 rounded-lg border border-gray-200 bg-white text-xs font-bold text-gray-600 hover:bg-gray-50">Limpiar</button>
        </div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3">
        <select wire:model.live="segment" class="rounded-lg border-gray-200 text-sm focus:border-slate-700 focus:ring-slate-700">
            <option value="">Todos los segmentos</option>
            <option value="agricola">Agricola</option>
            <option value="industrial">Industrial</option>
            <option value="minero">Minero</option>
        </select>
        <select wire:model.live="technicalCategory" class="rounded-lg border-gray-200 text-sm focus:border-slate-700 focus:ring-slate-700">
            <option value="">Todas las categorias tecnicas</option>
            @foreach($options['technicalCategories'] as $category)
                <option value="{{ $category }}">{{ $category }}</option>
            @endforeach
        </select>
    </div>
    @error('dateTo') <p class="mt-3 text-xs font-bold text-rose-600">{{ $message }}</p> @enderror
</section>
