@php
    $hasAlert = $this->quoteHasBlockingAlert($quote);
    $cardStage = $this->stageKeyForQuote($quote);
    $cardFinished = $quote->sale_order_id || in_array($quote->status, [\App\Models\QuotationRequest::STATUS_CLOSED, \App\Models\QuotationRequest::STATUS_CANCELLED]);
    $cardGroup = $cardFinished ? 'finished' : (in_array($cardStage, ['supplier_response', 'customer_approval', 'purchase_order_generated', 'order_changes']) ? 'pending' : 'active');
    $cover = auth()->user()->can('view', $quote) ? app(\App\Support\QuotationCover::class)->resolve($quote, auth()->user()->can('supplier_bid.view')) : null;
    $firstItem = $quote->items->first();
    $title = $firstItem?->part_name ?: ($firstItem?->description ?: ($quote->machinery ?: 'Solicitud de cotización'));
    $owner = $quote->user?->name ?: 'Sin asignar';
    $urgent = in_array(mb_strtolower($quote->request_urgency ?? ''), ['alta', 'urgente', 'critica', 'crítica']);
    $files = collect([$quote->evidence_attachment_path])
        ->merge($quote->items->pluck('image_path'))
        ->merge($quote->supplierBidResponses->pluck('attachment_path'))
        ->merge($quote->supplierBidResponses->pluck('supplier_document_path'))
        ->merge($quote->supplierBidResponses->flatMap(fn ($response) => $response->supplierDocumentAttachments->pluck('file_path')))
        ->merge($quote->supplierRfqDocuments->pluck('pdf_path'))->filter()->unique()->count();
@endphp
<article x-show="filter === 'all' || filter === '{{ $cardGroup }}'" wire:key="kanban-card-{{ $quote->id }}" class="group overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition-colors hover:border-slate-400" data-testid="quotation-card-{{ $quote->id }}">
    <a href="{{ $this->getQuoteUrl($quote) }}" aria-label="Abrir cotización {{ $quote->reference }}: {{ $title }} — {{ $flowStages[$cardStage]['label'] ?? 'Expediente' }}" class="block focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-slate-700">
        @if($cover)
            <div class="overflow-hidden border-b border-slate-100 bg-slate-50" x-data="{ failed: false }" x-show="!failed">
                <img src="{{ route('private.quotation-cover', $quote) }}" alt="Imagen adjunta de {{ $title }}" loading="lazy" decoding="async" width="528" height="320" class="h-40 w-full object-contain" x-on:error="failed = true">
            </div>
        @endif
        <div class="p-3">
            <div class="flex flex-wrap items-center gap-2">
                <span class="mr-auto whitespace-nowrap text-xs font-semibold text-slate-500">{{ $quote->reference }}</span>
                @if($urgent)<span class="rounded bg-rose-50 px-2 py-0.5 text-xs font-semibold text-rose-700">Urgente</span>@endif
                @if($hasAlert)<span class="rounded bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-800">Requiere atención</span>@endif
            </div>
            <h3 class="mt-2 line-clamp-2 break-words text-sm font-semibold leading-5 text-slate-900">{{ $title }}</h3>
            <p class="mt-1 line-clamp-2 text-sm text-slate-600">{{ $quote->quote_to_name ?: 'Sin cliente' }}</p>
            @if($firstItem?->part_number)<p class="mt-1 truncate text-xs text-slate-500" title="{{ $firstItem->part_number }}">P/N {{ $firstItem->part_number }}</p>@endif
            <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
                <span>{{ $quote->items->count() }} {{ $quote->items->count() === 1 ? 'partida' : 'partidas' }}</span>
                @if($files)<span class="inline-flex items-center gap-1"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m21 11-9 9a6 6 0 0 1-8.5-8.5l10-10a4 4 0 0 1 5.7 5.7l-10 10a2 2 0 0 1-2.8-2.8l9-9"/></svg>{{ $files }} {{ $files === 1 ? 'archivo' : 'archivos' }}</span>@endif
            </div>
            <div class="mt-3 flex items-center justify-between gap-2 border-t border-slate-100 pt-3">
                <span class="flex min-w-0 items-center gap-2" title="Responsable: {{ $owner }}">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600" aria-hidden="true">{{ mb_strtoupper(mb_substr($owner, 0, 2)) }}</span>
                    <span class="truncate text-xs text-slate-600">{{ $owner }}</span>
                </span>
                <time class="shrink-0 text-xs text-slate-500" datetime="{{ $quote->created_at?->toDateString() }}" title="Fecha de creación">{{ $quote->created_at?->format('d/m/Y') }}</time>
            </div>
            <p class="mt-3 flex items-center justify-between gap-2 text-xs font-semibold text-slate-700"><span>{{ $cardFinished ? 'Ver expediente' : ($flowStages[$cardStage]['action'] ?? 'Abrir proceso') }}</span><span aria-hidden="true">→</span></p>
        </div>
    </a>
</article>
