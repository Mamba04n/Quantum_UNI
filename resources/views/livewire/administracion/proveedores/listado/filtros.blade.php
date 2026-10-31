<div class="mb-4" x-data="{ filtersOpen: false }">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <label class="relative block w-full max-w-sm">
            <span class="sr-only">Buscar proveedor</span>
            <svg class="pointer-events-none absolute left-3 top-3 h-4 w-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input wire:model.live.debounce.400ms="search" type="search" placeholder="Buscar proveedor, contacto, marca o producto..." class="w-full rounded-xl border-transparent bg-gray-50 py-2 pl-10 pr-3 text-sm transition-all focus:bg-white focus:ring-2 focus:ring-slate-200">
        </label>
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs font-medium text-gray-400">{{ $suppliers->count() }} proveedores</span>
            <button type="button" x-on:click="filtersOpen = !filtersOpen" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-600 transition-colors hover:bg-gray-50">
                Filtros
                <svg class="h-3.5 w-3.5 transition-transform" x-bind:class="filtersOpen && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
            </button>
        </div>
    </div>
    <div x-cloak x-show="filtersOpen" x-collapse class="mt-3 rounded-xl border border-gray-100 bg-white p-3 shadow-sm">
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <select wire:model.live="countryFilter" class="h-10 rounded-lg border-gray-200 text-sm"><option value="">Todos los países</option>@foreach($countries as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach</select>
            <select wire:model.live="statusFilter" class="h-10 rounded-lg border-gray-200 text-sm"><option value="">Todos los estados</option><option value="activo">Activos</option><option value="inactivo">Inactivos</option></select>
            <select wire:model.live="brandFilter" class="h-10 rounded-lg border-gray-200 text-sm"><option value="">Todas las marcas</option>@foreach($brandOptions as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach</select>
            <select wire:model.live="productTypeFilter" class="h-10 rounded-lg border-gray-200 text-sm"><option value="">Todos los productos</option>@foreach($productTypeOptions as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach</select>
            <select wire:model.live="machineTypeFilter" class="h-10 rounded-lg border-gray-200 text-sm"><option value="">Toda maquinaria</option>@foreach($machineTypeOptions as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach</select>
            <select wire:model.live="paymentMethodFilter" class="h-10 rounded-lg border-gray-200 text-sm"><option value="">Todos los métodos de pago</option>@foreach($paymentOptions as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach</select>
            <button type="button" wire:click="clearFilters" class="h-10 rounded-lg border border-gray-200 bg-white px-4 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-50">Limpiar filtros</button>
        </div>
    </div>
</div>
