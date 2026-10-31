<div class="cp-ticket-center" wire:poll.visible.5s>
    @if(session()->has('message'))<div class="cp-success" role="status"><x-portal.icon name="check" />{{ session('message') }}</div>@endif
    @if($tickets->isEmpty() && $view === 'list')
        <section class="cp-panel"><x-portal.empty icon="support" title="Conversemos sobre lo que necesitas" description="Crea un ticket para consultas técnicas, ayuda con un repuesto o seguimiento. Tu conversación quedará guardada aquí."><button type="button" wire:click="startCreate" class="cp-button cp-button--primary"><x-portal.icon name="plus" />Nuevo ticket</button></x-portal.empty></section>
    @else
        <div class="cp-ticket-workspace {{ $tickets->isEmpty() ? 'cp-ticket-workspace--single' : '' }}">
            @if($tickets->isNotEmpty())@include('livewire.clientes.tickets.listado')@endif
            @include('livewire.clientes.tickets.detalle')
        </div>
    @endif
</div>
