<div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-extrabold tracking-tight text-gray-900">Centro de Exportaciones</h2>
        <p class="text-sm text-gray-500 mt-0.5">Descarga informacion operativa del sistema en Excel desde un solo lugar.</p>
    </div>
    <button wire:click="exportExcel"
            wire:loading.attr="disabled"
            wire:target="exportExcel"
            class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-slate-900 disabled:opacity-60">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg>
        <span wire:loading.remove wire:target="exportExcel">Descargar Excel</span>
        <span wire:loading wire:target="exportExcel">Preparando...</span>
    </button>
</div>
