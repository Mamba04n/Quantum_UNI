<section id="quotation-client-decision" class="scroll-mt-48 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
    <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
        <h3 class="text-sm font-semibold text-slate-900">Aprobación del cliente</h3>
        <p class="mt-1 text-xs font-bold text-slate-500">Registre si el cliente aprueba, solicita cambios o rechaza la oferta.</p>
    </div>
    @php
        $hasOfferGenerated = filled($selectedQuote->client_offer_pdf_path) || $selectedQuote->offerVersions->isNotEmpty();
    @endphp

    <div class="space-y-3 p-4">
        @if(! $hasOfferGenerated)
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                <div class="flex items-start gap-3">
                    <svg class="h-5 w-5 flex-shrink-0 text-amber-600 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <div class="flex-1 text-xs text-amber-900">
                        <p class="font-bold">No se ha generado el PDF de la Oferta Cliente para esta solicitud.</p>
                        <p class="mt-1 text-amber-700">Para registrar la aprobación, cambios o rechazo del cliente es obligatorio contar con la oferta formal generada.</p>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <button type="button" wire:click="revertStage({{ $selectedQuote->id }})" class="inline-flex items-center gap-1.5 rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white shadow-xs hover:bg-amber-700 transition">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                                Regresar a Oferta Cliente
                            </button>
                            <button type="button" wire:click="generateClientOfferPdf" class="inline-flex items-center gap-1.5 rounded-lg border border-amber-300 bg-white px-3 py-1.5 text-xs font-semibold text-amber-800 shadow-xs hover:bg-amber-100 transition">
                                <svg class="h-3.5 w-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l3.414 3.414A1 1 0 0117 7.414V19a2 2 0 01-2 2z"/></svg>
                                Generar PDF de Oferta ahora
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <label for="approval-reason" class="block text-sm font-medium text-slate-700">Comentario o motivo de la decisión</label>
        <textarea id="approval-reason" wire:model="approvalReason" rows="2" class="w-full rounded-lg border-slate-200 text-sm" placeholder="Indica los cambios solicitados o el motivo de la decisión..."></textarea>
        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:justify-end">
            <button type="button" wire:click="registerClientApproval('total')" wire:loading.attr="disabled" wire:target="registerClientApproval,registerCustomerChangeRequest" @disabled(! $hasOfferGenerated) class="rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-900 disabled:opacity-50 disabled:cursor-not-allowed">Registrar aprobación</button>
            <button type="button" wire:click="registerCustomerChangeRequest" wire:loading.attr="disabled" wire:target="registerClientApproval,registerCustomerChangeRequest" @disabled(! $hasOfferGenerated) class="rounded-xl bg-amber-50 px-4 py-2.5 text-sm font-semibold text-amber-700 hover:bg-amber-100 disabled:opacity-50 disabled:cursor-not-allowed">Solicitó cambios</button>
            <button type="button" wire:click="registerClientApproval('rejected')" wire:loading.attr="disabled" wire:target="registerClientApproval,registerCustomerChangeRequest" @disabled(! $hasOfferGenerated) class="rounded-xl bg-rose-50 px-4 py-2.5 text-sm font-semibold text-rose-700 hover:bg-rose-100 disabled:opacity-50 disabled:cursor-not-allowed">Registrar rechazo</button>
        </div>
        <p wire:loading wire:target="registerClientApproval,registerCustomerChangeRequest" role="status" class="text-sm text-slate-500">Guardando decisión...</p>
        @error('customerChangeReason')
            <p class="text-xs font-bold text-rose-600">{{ $message }}</p>
        @enderror
    </div>
</section>
