<div class="w-full min-w-0 p-6 font-sans sm:p-8">
    @include('livewire.ventas.cotizaciones.solicitudes-cliente.partials.encabezado')
    @include('livewire.ventas.cotizaciones.solicitudes-cliente.partials.mensaje')

    <form wire:submit="save" class="min-w-0 space-y-4">
        <div class="grid items-start gap-4 lg:grid-cols-2 lg:gap-5 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm sm:p-5">
            @include('livewire.ventas.cotizaciones.solicitudes-cliente.partials.formulario.cliente-origen')
            @include('livewire.ventas.cotizaciones.solicitudes-cliente.partials.formulario.datos-solicitud')
        </div>
        <div class="grid items-start gap-4 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm sm:p-5 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            @include('livewire.ventas.cotizaciones.solicitudes-cliente.partials.formulario.notas')
            @include('livewire.ventas.cotizaciones.solicitudes-cliente.partials.formulario.evidencia')
        </div>
        @include('livewire.ventas.cotizaciones.solicitudes-cliente.partials.formulario.productos-solicitados')
        @include('livewire.ventas.cotizaciones.solicitudes-cliente.partials.formulario.acciones')
    </form>
</div>
