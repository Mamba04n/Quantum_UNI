<div class="p-6 sm:p-8 font-sans">
    @include('livewire.administracion.reportes.exportaciones.encabezado')

    <div class="grid grid-cols-1 items-start gap-4 xl:grid-cols-3">
        @include('livewire.administracion.reportes.exportaciones.selector')
        @include('livewire.administracion.reportes.exportaciones.modulos')
    </div>
</div>
