<main class="mx-auto w-full max-w-6xl">
    @php
        $isFromAdmin = $selectedQuote->status === \App\Models\QuotationRequest::STATUS_ADMIN_CHANGES_REQUESTED;
        $canEditOfferData = true;
    @endphp

    @include('livewire.ventas.cotizaciones.correcciones-orden.partials.detalle.barra-retorno')
    <x-order-context :quotation="$selectedQuote" class="mb-6" />

    <div class="bg-white shadow-md border border-slate-200 rounded-2xl overflow-hidden">
        @include('livewire.ventas.cotizaciones.correcciones-orden.partials.detalle.encabezado-orden')

        <div class="p-8 space-y-8">
            @include('livewire.ventas.cotizaciones.correcciones-orden.partials.detalle.motivo-cambio')
            
            <div class="mt-8 border-t border-slate-200 pt-8">
                @include('livewire.ventas.cotizaciones.fase-cotizacion.partials.client-offer-panel')
            </div>
            
            <div class="mt-8 pt-8 flex justify-end">
                @include('livewire.ventas.cotizaciones.correcciones-orden.partials.detalle.acciones-reenvio')
            </div>
        </div>
    </div>
</main>
