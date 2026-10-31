<div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
    @include('livewire.ventas.cotizaciones.correcciones-orden.partials.encabezado')

    @if(!$selectedQuote)
        @include('livewire.ventas.cotizaciones.correcciones-orden.partials.buscador')
        @include('livewire.ventas.cotizaciones.correcciones-orden.partials.listado')
    @else
        @include('livewire.ventas.cotizaciones.correcciones-orden.partials.detalle.detalle-orden')
    @endif
</div>
