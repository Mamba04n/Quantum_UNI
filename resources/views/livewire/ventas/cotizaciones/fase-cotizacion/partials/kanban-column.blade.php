@php
    $stageDot = match ($key) {
        'supplier_bid' => 'bg-blue-600',
        'supplier_response' => 'bg-amber-500',
        'client_offer' => 'bg-violet-600',
        'customer_approval' => 'bg-[#b50e0e]',
        'purchase_preparation' => 'bg-teal-600',
        default => 'bg-slate-600',
    };
    $stagePill = match ($key) {
        'supplier_bid' => 'bg-blue-50 text-blue-700',
        'supplier_response' => 'bg-amber-50 text-amber-700',
        'client_offer' => 'bg-violet-50 text-violet-700',
        'customer_approval' => 'bg-rose-50 text-[#b50e0e]',
        'purchase_preparation' => 'bg-teal-50 text-teal-700',
        default => 'bg-gray-100 text-gray-700',
    };
    $columnGroups = $quotes->map(function ($quote) {
        if ($quote->sale_order_id || in_array($quote->status, [\App\Models\QuotationRequest::STATUS_CLOSED, \App\Models\QuotationRequest::STATUS_CANCELLED])) {
            return 'finished';
        }
        return in_array($this->stageKeyForQuote($quote), ['supplier_response', 'customer_approval', 'purchase_order_generated', 'order_changes']) ? 'pending' : 'active';
    })->values()->all();
@endphp

<section
    aria-labelledby="stage-{{ $key }}"
    class="flex w-[290px] shrink-0 flex-col rounded-2xl border border-gray-200/80 bg-gray-50/70 p-3 shadow-2xs"
    data-testid="kanban-column-{{ $key }}"
>
    {{-- Header de la Columna --}}
    <header class="flex items-center justify-between gap-2 rounded-xl bg-white p-3 border border-gray-100 shadow-2xs mb-3">
        <div class="flex items-center gap-2 min-w-0">
            <span class="h-2.5 w-2.5 shrink-0 rounded-full {{ $stageDot }}" aria-hidden="true"></span>
            <h2 id="stage-{{ $key }}" class="text-sm font-bold text-gray-900 truncate">
                {{ $stage['label'] }}
            </h2>
        </div>

        <span
            x-text="@js($columnGroups).filter(value => filter === 'all' || value === filter).length"
            class="inline-flex min-w-6 items-center justify-center rounded-full px-2 py-0.5 text-xs font-bold tabular-nums {{ $stagePill }}"
        >
            {{ $count }}
        </span>
    </header>

    {{-- Lista de Tarjetas --}}
    <div class="space-y-3 px-0.5 pb-2 md:max-h-[calc(100vh-22rem)] md:overflow-y-auto">
        @foreach($quotes as $quote)
            @include('livewire.ventas.cotizaciones.fase-cotizacion.partials.quotation-card', ['quote' => $quote])
        @endforeach

        <div x-cloak x-show="!@js($columnGroups).some(value => filter === 'all' || value === filter)">
            <div class="flex flex-col items-center justify-center p-6 text-center rounded-xl border border-dashed border-gray-200 bg-white/60 my-2">
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 text-gray-400 mb-1.5">
                    <x-portal.icon name="document" class="h-4 w-4" />
                </span>
                <p class="text-xs font-semibold text-gray-500">Sin solicitudes</p>
                <p class="text-[11px] text-gray-400 mt-0.5">No hay órdenes en esta fase</p>
            </div>
        </div>
    </div>
</section>
