<div class="w-full">
    @php($previousStage = $this->previousStageInfo($selectedQuote))
    @php($pendingStageChange = $this->pendingStageChangeRequest($selectedQuote))
    @php($advanceLabel = match ($currentStage) {
        'customer_request' => 'Enviar a licitación',
        'supplier_bid' => 'Continuar a respuestas',
        'supplier_response' => 'Continuar a Oferta Cliente',
        'client_offer' => 'Continuar a respuesta del cliente',
        'purchase_preparation' => 'Finalizar preparación',
        default => 'Avanzar etapa',
    })
    @php($isClientOfferStage = $currentStage === 'client_offer')
    @php($hasOfferPdf = filled($selectedQuote->client_offer_pdf_path) || $selectedQuote->offerVersions->isNotEmpty())
    @php($cannotAdvanceOffer = $isClientOfferStage && ! $hasOfferPdf)
    @if($this->advanceStageError)
        <div class="mb-4 rounded-md bg-red-50 p-4 border border-red-200 shadow-sm">
            <div class="flex items-start">
                <div class="flex-shrink-0 mt-0.5">
                    <svg class="h-5 w-5 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3 w-full flex-1">
                    <h3 class="text-sm font-bold text-red-800">No se puede avanzar la etapa</h3>
                    <div class="mt-1 text-sm text-red-700">
                        <p>{{ $this->advanceStageError }}</p>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div
        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
    >
        <div @if($responseWorkspace ?? false) class="hidden" @endif>
            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Etapa actual</p>
            <p class="mt-1 text-sm font-black text-slate-900">{{ $flowStages[$currentStage]['label'] ?? $this->humanStatus($selectedQuote->status) }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
        @if($previousStage)
            <button
                type="button"
                wire:click="revertStage({{ $selectedQuote->id }})"
                wire:confirm="¿Estás seguro de que deseas {{ strtolower($previousStage['label']) }}? {{ $previousStage['warning'] ?? '' }}"
                wire:loading.attr="disabled"
                wire:target="revertStage"
                class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 hover:text-slate-900 transition shadow-xs disabled:opacity-50"
            >
                <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <span wire:loading.remove wire:target="revertStage">{{ $previousStage['label'] }}</span>
                <span wire:loading wire:target="revertStage">Regresando...</span>
            </button>
        @endif
        @if($preliquidationActions ?? false)
            @include('livewire.ventas.cotizaciones.licitacion-proveedor.partials.preliquidacion-save')
        @endif
        <button
            type="button"
            wire:click="advanceStage({{ $selectedQuote->id }})"
            wire:loading.attr="disabled"
            wire:target="advanceStage"
            @disabled($cannotAdvanceOffer)
            @if($cannotAdvanceOffer) title="Es obligatorio generar el PDF de la Oferta Cliente antes de continuar." @endif
            @if($responseWorkspace ?? false) x-bind:disabled="dirtyItems.length > 0 || detailsDirty || saving || uploading || $wire.supplierProformas.length > 0" @endif
            class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm disabled:cursor-not-allowed disabled:opacity-50"
        >
            <span wire:loading.remove wire:target="advanceStage">{{ $advanceLabel }}</span>
            <span wire:loading wire:target="advanceStage" class="inline-flex items-center gap-2">
                <span class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                Avanzando...
            </span>
        </button>
        </div>
    </div>
</div>
