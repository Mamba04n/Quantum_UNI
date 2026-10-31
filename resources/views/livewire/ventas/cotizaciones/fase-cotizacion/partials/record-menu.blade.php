@if($selectedQuote->status !== \App\Models\QuotationRequest::STATUS_CANCELLED)
    <details class="relative shrink-0">
        <summary class="cursor-pointer rounded-xl border border-gray-200 px-3 py-2 text-sm font-semibold text-slate-700">Más</summary>
        <div class="absolute right-0 z-40 mt-1 grid w-56 gap-1 rounded-xl border border-gray-100 bg-white p-2 shadow-sm">
            @if($selectedQuote->status !== \App\Models\QuotationRequest::STATUS_CANCELLED)
                <button type="button" wire:click="openCancelQuoteModal" class="rounded-xl px-3 py-2 text-sm font-semibold text-rose-700">Cancelar cotización</button>
            @endif
        </div>
    </details>
@endif
