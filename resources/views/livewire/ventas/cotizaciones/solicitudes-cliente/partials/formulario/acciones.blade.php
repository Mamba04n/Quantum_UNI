<div class="sticky bottom-0 z-10 flex flex-wrap items-center justify-end gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm">
    <a href="{{ route('sales.quotations.phase') }}" class="px-5 py-2.5 rounded-xl border border-gray-200 text-sm font-bold text-gray-600 hover:bg-gray-50">Cancelar</a>
    <button type="submit" wire:loading.attr="disabled" wire:target="save,evidenceAttachment" class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-slate-900 disabled:cursor-not-allowed disabled:opacity-50">
        <span wire:loading.remove wire:target="save">Guardar solicitud</span>
        <span wire:loading wire:target="save">Guardando solicitud...</span>
    </button>
</div>
