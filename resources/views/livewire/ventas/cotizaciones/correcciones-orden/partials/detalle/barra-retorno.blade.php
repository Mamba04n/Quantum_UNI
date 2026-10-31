<div class="mb-6 flex items-center justify-between rounded-xl bg-white p-4 shadow-sm border border-slate-200">
    <button type="button" wire:click="backToList" class="flex items-center gap-2 text-sm font-black text-slate-500 transition hover:text-slate-900">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
        Volver a la lista
    </button>
    <div class="flex items-center gap-4">
        <div class="text-right">
            <p class="text-[10px] font-black uppercase text-slate-400">Total Orden</p>
            <p class="font-black text-slate-900">C$ {{ number_format($selectedQuote->total_amount, 2) }}</p>
        </div>
    </div>
</div>
