<section class="cp-ticket-detail {{ $view === 'list' ? 'cp-ticket-detail--inactive' : '' }}" aria-label="Detalle del ticket">
    @if($view === 'create')
        @include('livewire.clientes.tickets.formulario')
    @elseif($view === 'detail' && $selectedTicket)
        @include('livewire.clientes.tickets.conversacion')
    @else
        <x-portal.empty icon="chat" title="Tus conversaciones, a mano" description="Selecciona un ticket para consultar sus mensajes o inicia una nueva solicitud."><button type="button" wire:click="startCreate" class="cp-button"><x-portal.icon name="plus" />Nuevo ticket</button></x-portal.empty>
    @endif
</section>
