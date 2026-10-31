<div class="p-6 sm:p-8 font-sans" x-data="{ imageModal: null }" wire:poll.30s>
    @include('livewire.administracion.auditoria.actividad.encabezado')
    <p class="mb-4 text-sm text-gray-500">Actualización cada 30 segundos. Un evento HTTP no confirma un guardado.</p>
    @include('livewire.administracion.auditoria.actividad.metricas')
    @include('livewire.administracion.auditoria.actividad.filtros')

    @include('livewire.administracion.auditoria.actividad.timeline')
</div>
