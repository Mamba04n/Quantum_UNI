<div class="quotation-order-header">
    <div class="min-w-0">
        <div class="flex flex-wrap items-center gap-3">
        <nav aria-label="Ruta de navegación" class="flex flex-wrap items-baseline gap-x-2 gap-y-1 text-sm text-gray-500">
            <a href="{{ route('workspace') }}" class="font-medium text-slate-600 hover:text-slate-900">Inicio</a>
            <span aria-hidden="true">/</span>
            <a href="{{ route('sales.quotations.phase') }}" class="font-medium text-slate-600 hover:text-slate-900">Cotizaciones</a>
            <span aria-hidden="true">/</span>
            <h1 aria-current="page" class="text-2xl font-extrabold tracking-tight text-gray-900"><span class="sr-only">Cotización </span>{{ $selectedQuote->reference }}</h1>
        </nav>
        @include('livewire.ventas.cotizaciones.fase-cotizacion.partials.action-buttons')
        </div>
        <div aria-label="Resumen de la orden" class="mt-0.5 flex flex-wrap items-baseline gap-x-3 gap-y-1 text-sm text-gray-500">
            <span class="font-medium text-gray-700">{{ $selectedQuote->quote_to_name ?: 'Sin cliente' }}</span>
            @if($selectedQuote->machinery)
                <span>Equipo: {{ $selectedQuote->machinery }}</span>
            @endif
            <span>{{ $selectedQuote->items->count() }} partidas</span>
            <span>Responsable: {{ $selectedQuote->user->name ?? 'Sin asignar' }}</span>
            @if($selectedQuote->status === \App\Models\QuotationRequest::STATUS_CANCELLED)
                <span class="font-semibold text-red-700">Cancelada</span>
            @endif
        </div>
    </div>

    <div class="quotation-order-progress">
        @include('livewire.ventas.cotizaciones.fase-cotizacion.partials.status-pipeline', ['inlineHeader' => true])
    </div>
</div>
