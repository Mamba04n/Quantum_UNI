<section class="xl:col-span-3 space-y-4 {{ $selectedTicketId ? 'hidden xl:block' : '' }}">
    @include('livewire.administracion.tickets.listado.filtros')
    @include('livewire.administracion.tickets.listado.tabla')
</section>
