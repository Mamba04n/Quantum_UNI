<div class="flex flex-wrap items-center gap-2">
    <button type="button" wire:click="openManageItemsModal" wire:loading.attr="disabled" wire:target="openManageItemsModal" class="inline-flex items-center justify-center rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-50 disabled:opacity-50">Modificar orden</button>
    @include('livewire.ventas.cotizaciones.fase-cotizacion.partials.record-menu')
</div>