<section class="xl:col-span-2">
    @if($selectedTicket)
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden sticky top-6">
            @include('livewire.administracion.tickets.detalle.encabezado')

            <div class="p-5 space-y-5 max-h-[calc(100vh-190px)] overflow-y-auto">
                @include('livewire.administracion.tickets.detalle.informacion')

                @if($selectedTicket->type === \App\Models\Ticket::TYPE_PURCHASE)
                    @include('livewire.administracion.tickets.detalle.solicitud-compra')
                @endif

                @include('livewire.administracion.tickets.detalle.formulario-estado')
                @include('livewire.administracion.tickets.detalle.conversacion')
                @include('livewire.administracion.tickets.detalle.formulario-respuesta')
                @include('livewire.administracion.tickets.detalle.notas-internas')
            </div>
        </div>
    @else
        @include('livewire.administracion.tickets.detalle.estado-vacio')
    @endif
</section>
