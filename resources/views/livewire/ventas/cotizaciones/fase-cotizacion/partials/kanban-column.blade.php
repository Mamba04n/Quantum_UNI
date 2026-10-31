@php
    $stageTone = match ($key) {
        'supplier_bid' => 'border-sky-200 bg-sky-50/50',
        'supplier_response' => 'border-amber-200 bg-amber-50/50',
        'client_offer' => 'border-violet-200 bg-violet-50/50',
        'customer_approval' => 'border-rose-200 bg-rose-50/50',
        'purchase_preparation' => 'border-teal-200 bg-teal-50/50',
        default => 'border-slate-200 bg-slate-100/70',
    };
    $stageDot = match ($key) {
        'supplier_bid' => 'bg-sky-600', 'supplier_response' => 'bg-amber-500',
        'client_offer' => 'bg-violet-600', 'customer_approval' => 'bg-rose-600',
        'purchase_preparation' => 'bg-teal-600', default => 'bg-slate-600',
    };
    $columnGroups = $quotes->map(function ($quote) {
        if ($quote->sale_order_id || in_array($quote->status, [\App\Models\QuotationRequest::STATUS_CLOSED, \App\Models\QuotationRequest::STATUS_CANCELLED])) {
            return 'finished';
        }
        return in_array($this->stageKeyForQuote($quote), ['supplier_response', 'customer_approval', 'purchase_order_generated', 'order_changes']) ? 'pending' : 'active';
    })->values()->all();
@endphp
<section aria-labelledby="stage-{{ $key }}" class="flex w-[280px] shrink-0 flex-col rounded-xl border {{ $stageTone }}" data-testid="kanban-column-{{ $key }}">
    <div class="px-3 py-3">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 id="stage-{{ $key }}" class="flex items-center gap-2 text-sm font-semibold text-gray-900"><span class="h-2 w-2 shrink-0 rounded-full {{ $stageDot }}" aria-hidden="true"></span>{{ $stage['label'] }}</h2>
            </div>
            <span x-text="@js($columnGroups).filter(value => filter === 'all' || value === filter).length" class="inline-flex min-w-6 items-center justify-center rounded-md bg-white/80 px-1.5 py-0.5 text-sm font-semibold text-slate-600">{{ $count }}</span>
        </div>
    </div>

    <div class="space-y-3 px-2 pb-2">
        @foreach($quotes as $quote)
            @include('livewire.ventas.cotizaciones.fase-cotizacion.partials.quotation-card', ['quote' => $quote])
        @endforeach
        <div x-cloak x-show="!@js($columnGroups).some(value => filter === 'all' || value === filter)">
            <p class="px-2 py-4 text-sm text-slate-500">Sin solicitudes en esta etapa.</p>
        </div>
    </div>
</section>
