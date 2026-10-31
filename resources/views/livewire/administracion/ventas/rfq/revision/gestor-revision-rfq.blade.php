<div class="erp-page">
    <div class="erp-shell">
        @include('livewire.administracion.ventas.rfq.revision.encabezado')

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            @include('livewire.administracion.ventas.rfq.revision.listado-pendientes')

            <main class="lg:col-span-8">
                @if($selectedReview)
                    @include('livewire.administracion.ventas.rfq.revision.detalle-revision')
                @else
                    @include('livewire.administracion.ventas.rfq.revision.estado-vacio')
                @endif
            </main>
        </div>
    </div>
</div>
