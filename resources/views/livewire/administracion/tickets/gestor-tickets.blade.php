<div class="p-6 sm:p-8 font-sans" wire:poll.5s>
    @include('livewire.administracion.tickets.encabezado')

    <div class="grid grid-cols-1 xl:grid-cols-5 gap-5">
        @include('livewire.administracion.tickets.listado')
        @include('livewire.administracion.tickets.detalle')
    </div>
</div>
