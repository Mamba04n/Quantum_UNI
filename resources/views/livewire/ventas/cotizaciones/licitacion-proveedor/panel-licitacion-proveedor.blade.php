<div class="min-w-0 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm sm:p-5">
    @php
        $bidPanel = match ($currentStage ?? $this->stageKeyForStatus($selectedQuote->status)) {
            'supplier_response', 'supplier_selection' => 2,
            default => 1,
        };
    @endphp

    @if($bidPanel === 1)
        @include('livewire.ventas.cotizaciones.licitacion-proveedor.partials.preparacion-rfq')
    @else
        @include('livewire.ventas.cotizaciones.licitacion-proveedor.partials.respuesta-proveedor')
    @endif
</div>
