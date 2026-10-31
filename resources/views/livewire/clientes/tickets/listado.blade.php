<aside class="cp-ticket-list {{ $view !== 'list' ? 'cp-ticket-list--inactive' : '' }}" aria-label="Mis tickets">
    <div class="cp-panel-heading"><h2>Mis tickets <span class="cp-count">{{ $tickets->count() }}</span></h2><button type="button" wire:click="startCreate" class="cp-icon-button" aria-label="Nuevo ticket"><x-portal.icon name="plus" /></button></div>
    <div class="cp-ticket-scroll">
        @foreach($tickets as $ticket)
            @php($isUnread = $ticket->messages->where('sender_type', 'admin')->whereNull('read_at')->count() > 0)
            <button type="button" wire:key="portal-ticket-{{ $ticket->id }}" wire:click="openTicket({{ $ticket->id }})" class="cp-ticket-preview {{ $selectedTicketId === $ticket->id ? 'is-selected' : '' }}" @if($selectedTicketId === $ticket->id) aria-current="true" @endif>
                <span class="cp-ticket-code">{{ $ticket->code }} @if($isUnread)<span class="cp-unread">Sin leer</span>@endif</span><strong>{{ $ticket->subject }}</strong><span class="cp-ticket-meta"><span>{{ $statusLabels[$ticket->status] ?? $ticket->status }}</span><span>{{ $ticket->last_message_at?->format('d/m/Y') ?? $ticket->created_at->format('d/m/Y') }}</span></span>
            </button>
        @endforeach
    </div>
</aside>
