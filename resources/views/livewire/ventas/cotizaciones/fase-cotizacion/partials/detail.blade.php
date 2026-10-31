@php
    $tracker = app(\App\Services\OrderTrackerService::class)
        ->trackerForQuotation($selectedQuote, false)
        ->load(['comments.user', 'comments.activity.attachments']);
    $currentStage = $this->stageKeyForQuote($selectedQuote);
    $selectedCount = $selectedQuote->supplierBidSelections->count();
    $subtotal = (float) $selectedQuote->items->where('is_approved', true)->sum('line_total');
    if ($subtotal <= 0) { $subtotal = (float) $selectedQuote->items->sum('line_total'); }
    $taxPercent = (int) round((float) ($selectedQuote->tax_percent ?? 15));
    if ($taxPercent <= 0) { $taxPercent = 15; }
    $iva = round($subtotal * ($taxPercent / 100), 2);
    $grandTotal = round($subtotal + $iva, 2);
    $approvedItems = $selectedQuote->items->where('is_approved', true);
    $detailItems = in_array($activeTab, ['offer', 'approval'], true) && $approvedItems->isNotEmpty()
        ? $approvedItems
        : $selectedQuote->items;
    if ($detailItems->isEmpty()) {
        $detailItems = $selectedQuote->items;
    }
    $canEditOfferData = in_array($selectedQuote->status, [
        \App\Models\QuotationRequest::STATUS_CLIENT_OFFER_GENERATED,
        \App\Models\QuotationRequest::STATUS_OFFER_DRAFT,
        \App\Models\QuotationRequest::STATUS_OFFER_PREVIEW_READY,
        \App\Models\QuotationRequest::STATUS_FINAL_PDF_GENERATED,
        \App\Models\QuotationRequest::STATUS_ADMIN_CHANGES_REQUESTED,
        \App\Models\QuotationRequest::STATUS_CUSTOMER_CHANGES_REQUESTED,
        \App\Models\QuotationRequest::STATUS_REVISED_AFTER_CUSTOMER_CHANGE,
    ], true);
    $clientApproved = in_array($selectedQuote->status, [
        \App\Models\QuotationRequest::STATUS_CLIENT_APPROVED_TOTAL,
        \App\Models\QuotationRequest::STATUS_CLIENT_APPROVED_PARTIAL,
        \App\Models\QuotationRequest::STATUS_APPROVED_TOTAL,
        \App\Models\QuotationRequest::STATUS_APPROVED_PARTIAL,
        \App\Services\OrderTrackerService::QUOTE_PENDING_APPROVAL,
    ], true);
@endphp

<section id="commercial-order-detail" wire:key="commercial-detail-{{ $selectedQuote->id }}-{{ $currentStage }}" class="quotation-workspace scroll-mt-4 min-w-0 space-y-3 {{ $currentStage === 'supplier_response' ? 'preliq-page' : '' }}" x-data="{
    condensed: false, updateProgress: null, progressTop: 8, progressRight: 16,
    init() {
        this.$nextTick(() => {
            const main = this.$el.closest('main');
            const root = main && /auto|scroll/.test(getComputedStyle(main).overflowY) ? main : null;
            this.updateProgress = () => {
                const top = root ? Math.max(0, root.getBoundingClientRect().top) : 0;
                const bounds = this.$el.getBoundingClientRect();
                this.progressTop = top + 8;
                this.progressRight = Math.max(16, window.innerWidth - bounds.right);
                this.condensed = this.$refs.orderHeader.getBoundingClientRect().bottom <= top && bounds.bottom > top + 72;
            };
            window.addEventListener('scroll', this.updateProgress, true);
            window.addEventListener('resize', this.updateProgress);
            this.updateProgress();
        });
    },
    destroy() {
        window.removeEventListener('scroll', this.updateProgress, true);
        window.removeEventListener('resize', this.updateProgress);
    }
}">
    <div x-ref="orderHeader" class="rounded-2xl border border-gray-100 bg-white shadow-sm">
        @include('livewire.ventas.cotizaciones.fase-cotizacion.partials.detail-header')
    </div>
    <div class="pointer-events-none fixed z-40" x-bind:style="{ top: progressTop + 'px', right: progressRight + 'px', marginTop: '0' }">
        <div x-cloak x-show="condensed" class="w-fit max-w-full rounded-xl border border-gray-100 bg-white px-3 py-2 shadow-sm">
            @include('livewire.ventas.cotizaciones.fase-cotizacion.partials.status-pipeline', ['miniOnly' => true])
        </div>
    </div>
    <div id="quotation-phase-content" class="scroll-mt-20 min-w-0 space-y-3">
        @if(in_array($currentStage, ['customer_request', 'customer_approval', 'draft', 'admin_review', 'customer_review']))
            @include('livewire.ventas.cotizaciones.fase-cotizacion.partials.items-table')
        @endif
        @include('livewire.ventas.cotizaciones.fase-cotizacion.partials.phase-content')
    </div>
    <div class="min-w-0">
        <div class="min-w-0 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
            <details class="quotation-disclosure group" @if($errors->has('newComment') || $errors->has('commentFiles*')) open @endif>
                <summary class="inline-flex cursor-pointer list-none items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 shadow-xs transition hover:bg-slate-50 focus:outline-none">
                    <svg class="h-3.5 w-3.5 text-slate-500 transition-transform duration-200 group-open:rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    <span>Comentarios de la fase ({{ $tracker->comments->count() }})</span>
                </summary>
                <div class="mt-3">
                    @include('livewire.ventas.cotizaciones.fase-cotizacion.partials.comments', ['tracker' => $tracker, 'hideHeading' => true])
                </div>
            </details>
        </div>
    </div>
    @if($currentStage !== 'supplier_response')
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            @include('livewire.ventas.cotizaciones.fase-cotizacion.partials.process-actions')
        </div>
    @endif
</section>
