<section>
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-gray-900">Directorio de proveedores</h1>
            <p class="mt-0.5 text-sm text-gray-500">Consulta cobertura comercial, capacidades e historial de compras.</p>
        </div>
        <button type="button" wire:click="create" wire:loading.attr="disabled" wire:target="create" class="inline-flex w-fit items-center justify-center gap-2 rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-slate-900 disabled:opacity-60">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
            Nuevo proveedor
        </button>
    </div>

    @include('livewire.administracion.proveedores.listado.filtros')
    @include('livewire.administracion.proveedores.listado.tarjetas')
</section>
