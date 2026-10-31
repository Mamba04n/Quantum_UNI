@php
    $currentOrder = match ($currentStage) {
        'customer_request' => 1,
        'supplier_bid' => 1,
        'supplier_response' => 2,
        'supplier_selection', 'client_offer', 'order_changes' => 3,
        'customer_approval' => 4,
        'purchase_preparation', 'purchase_order_generated' => 5,
        default => 1,
    };

    $isCancelled = $selectedQuote->status === \App\Models\QuotationRequest::STATUS_CANCELLED;
    $progressSteps = ['Licitación', 'Preliquidación', 'Oferta cliente', 'Aprobación', 'Preparación orden de compra'];
@endphp

<div class="ml-auto min-w-0 {{ ($miniOnly ?? false) ? 'w-56 max-w-full' : (($inlineHeader ?? false) ? 'w-full' : 'w-full max-w-3xl') }}">
    <div class="{{ ($miniOnly ?? false) ? '' : (($inlineHeader ?? false) ? 'md:hidden' : 'lg:hidden') }}" aria-label="Avance de la cotización">
        <div class="mb-1 flex items-center justify-between gap-3 text-sm">
            <span class="font-semibold text-slate-800">{{ $isCancelled ? 'Cancelada' : $progressSteps[$currentOrder - 1] }}</span>
            @unless($isCancelled)<span class="shrink-0 text-gray-500">{{ $currentOrder }} de {{ count($progressSteps) }}</span>@endunless
        </div>
        <div class="flex gap-1" aria-hidden="true">
            @foreach($progressSteps as $step)
                <span class="h-1 flex-1 rounded {{ $isCancelled ? 'bg-gray-200' : ($loop->iteration < $currentOrder ? 'bg-emerald-500' : ($loop->iteration === $currentOrder ? 'bg-slate-800' : 'bg-gray-200')) }}"></span>
            @endforeach
        </div>
    </div>
    @unless($miniOnly ?? false)
        <div class="{{ ($inlineHeader ?? false) ? 'hidden md:block' : 'hidden lg:block' }}">
            <x-process-steps :steps="$progressSteps" :current="$currentOrder" :cancelled="$isCancelled" :compact="true" :single-row="$inlineHeader ?? false" :wrap-labels="true" label="Progreso del proceso comercial" />
        </div>
    @endunless
</div>
