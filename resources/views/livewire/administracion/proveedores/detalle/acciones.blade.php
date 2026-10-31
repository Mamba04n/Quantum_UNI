<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <button type="button" wire:click="backToList" class="inline-flex items-center gap-2 text-sm font-medium text-slate-500 hover:text-slate-900"><span aria-hidden="true">‹</span> Volver a proveedores</button>
    <div class="flex flex-wrap gap-2">
        <button wire:click="edit({{ $selectedSupplier->id }})" class="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Modificar datos</button>
        @if(($selectedSupplier->status ?? 'activo') === 'activo')
            <button wire:click="deactivateSupplier({{ $selectedSupplier->id }})" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Desactivar</button>
        @else
            <button wire:click="activateSupplier({{ $selectedSupplier->id }})" class="rounded-lg border border-emerald-200 bg-white px-4 py-2.5 text-sm font-medium text-emerald-700 hover:bg-emerald-50">Reactivar</button>
        @endif
        @if(auth()->user()?->can('supplier.delete'))
            <button wire:click="deleteSupplier({{ $selectedSupplier->id }})" wire:confirm="Si tiene historial se desactivará. ¿Deseas continuar?" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-500 hover:border-rose-200 hover:bg-rose-50 hover:text-rose-700">Eliminar</button>
        @endif
    </div>
</div>
