<div
    class="min-w-0 font-sans"
    x-data
    x-on:commercial-phase-advanced.window="setTimeout(() => document.getElementById('commercial-order-detail')?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 80)"
>
    <div class="space-y-6 p-6 sm:p-8" wire:key="quotation-view-{{ $selectedQuote?->id ?? 'kanban' }}">
        @if(!$selectedQuote)
            @include('livewire.ventas.cotizaciones.fase-cotizacion.partials.list')
        @else
            @include('livewire.ventas.cotizaciones.fase-cotizacion.partials.detail')
        @endif
    </div>

    @include('livewire.ventas.cotizaciones.shared.modal-administrar-items')
    @include('livewire.ventas.cotizaciones.fase-cotizacion.partials.cancel-modal')
</div>
