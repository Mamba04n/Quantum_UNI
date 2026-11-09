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

<article
    x-show="filter === 'all' || filter === '{{ $cardGroup }}'"
    wire:key="kanban-card-{{ $quote->id }}"
    class="group overflow-hidden rounded-xl border border-gray-200/90 bg-white shadow-2xs transition-all hover:border-[#b50e0e]/50 hover:shadow-sm"
    data-testid="quotation-card-{{ $quote->id }}"
>
    <a
        href="{{ $this->getQuoteUrl($quote) }}"
        aria-label="Abrir cotización {{ $quote->reference }}: {{ $title }} — {{ $flowStages[$cardStage]['label'] ?? 'Expediente' }}"
        class="block focus:outline-none focus-visible:ring-2 focus-visible:ring-[#b50e0e]"
    >
        @if($cover)
            <div class="overflow-hidden border-b border-gray-100 bg-gray-50/50" x-data="{ failed: false }" x-show="!failed">
                <img
                    src="{{ route('private.quotation-cover', $quote) }}"
                    alt="Imagen adjunta de {{ $title }}"
                    loading="lazy"
                    decoding="async"
                    width="528"
                    height="320"
                    class="h-36 w-full object-contain p-2"
                    x-on:error="failed = true"
                >
            </div>
        @endif

        <div class="p-3.5">
            {{-- Encabezado: Código de Orden + Badges --}}
            <div class="flex items-center justify-between gap-1.5 flex-wrap">
                <span class="rounded-md bg-gray-100 px-1.5 py-0.5 font-mono text-[11px] font-bold text-gray-700">
                    {{ $quote->reference }}
                </span>

                <div class="flex items-center gap-1">
                    @if($urgent)
                        <span class="rounded-md bg-rose-50 border border-rose-200 px-1.5 py-0.5 text-[10px] font-bold text-rose-700">
                            Urgente
                        </span>
                    @endif
                    @if($hasAlert)
                        <span class="rounded-md bg-amber-50 border border-amber-200 px-1.5 py-0.5 text-[10px] font-bold text-amber-800">
                            Atención
                        </span>
                    @endif
                </div>
            </div>

            {{-- Título y Cliente --}}
            <h3 class="mt-2 line-clamp-2 text-sm font-bold text-gray-900 group-hover:text-[#b50e0e] transition-colors leading-snug break-words">
                {{ $title }}
            </h3>

            <p class="mt-0.5 line-clamp-1 text-xs text-gray-600 font-medium">
                {{ $quote->quote_to_name ?: 'Sin cliente' }}
            </p>

            @if($firstItem?->part_number)
                <p class="mt-1 truncate text-[11px] text-gray-400 font-mono" title="{{ $firstItem->part_number }}">
                    P/N {{ $firstItem->part_number }}
                </p>
            @endif

            {{-- Partidas y Archivos Adjuntos --}}
            <div class="mt-2.5 flex items-center gap-2 text-[11px] text-gray-500">
                <span class="rounded-md bg-gray-50 px-2 py-0.5 border border-gray-100 font-medium">
                    {{ $quote->items->count() }} {{ $quote->items->count() === 1 ? 'partida' : 'partidas' }}
                </span>

                @if($files)
                    <span class="inline-flex items-center gap-1 rounded-md bg-gray-50 px-2 py-0.5 border border-gray-100 font-medium">
                        <x-portal.icon name="document" class="w-3 h-3 text-gray-400" />
                        <span>{{ $files }} {{ $files === 1 ? 'adjunto' : 'adjuntos' }}</span>
                    </span>
                @endif
            </div>

            {{-- Footer: Responsable y Fecha --}}
            <div class="mt-3 flex items-center justify-between gap-2 border-t border-gray-100 pt-2.5">
                <div class="flex min-w-0 items-center gap-1.5" title="Responsable: {{ $owner }}">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-slate-100 text-[10px] font-bold text-slate-700" aria-hidden="true">
                        {{ mb_strtoupper(mb_substr($owner, 0, 2)) }}
                    </span>
                    <span class="truncate text-xs text-gray-600 font-medium">{{ $owner }}</span>
                </div>

                <time class="shrink-0 text-[11px] text-gray-400" datetime="{{ $quote->created_at?->toDateString() }}" title="Fecha de creación">
                    {{ $quote->created_at?->format('d/m/Y') }}
                </time>
            </div>

            {{-- Acción Siguiente --}}
            <div class="mt-2 flex items-center justify-between gap-1 text-xs font-semibold text-gray-600 group-hover:text-[#b50e0e] transition-colors">
                <span>{{ $cardFinished ? 'Ver expediente' : ($flowStages[$cardStage]['action'] ?? 'Abrir proceso') }}</span>
                <span aria-hidden="true">&rarr;</span>
            </div>
        </div>
    </a>
</article>
