<div class="erp-page">
    <div class="erp-shell">
        @include('livewire.administracion.ventas.ofertas.revision.encabezado')

        @if(!$selectedReview)
            @include('livewire.administracion.ventas.ofertas.revision.listado-pendientes')
        @else
            @include('livewire.administracion.ventas.ofertas.revision.detalle-revision')
        @endif
    </div>
</div>
