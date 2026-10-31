<div class="cp-conversation-heading">
    <button type="button" wire:click="backToList" class="cp-icon-button cp-conversation-back" aria-label="Volver a mis tickets"><x-portal.icon name="back" /></button>
    <span class="cp-record-icon"><x-portal.icon name="support" /></span>
    <div><h2>{{ $selectedTicket->subject }}</h2><p>{{ $selectedTicket->code }} · {{ $selectedTicket->assignee?->name ?? 'Atención Quantum' }}</p><span class="cp-caption">Prioridad {{ mb_strtolower($priorityLabels[$selectedTicket->priority] ?? $selectedTicket->priority) }}</span></div>
    <span class="cp-status">{{ $statusLabels[$selectedTicket->status] ?? $selectedTicket->status }}</span>
</div>
<div class="cp-messages" id="chat-messages-container" wire:key="portal-messages-{{ $selectedTicket->id }}-{{ $selectedTicket->messages->count() }}" x-data x-init="$nextTick(() => { $el.scrollTop = $el.scrollHeight })" role="log" aria-label="Conversación del ticket" aria-live="polite">
    @forelse($selectedTicket->messages as $message)
        @php($isCustomer = $message->sender_type === 'customer')
        <article class="cp-message {{ $isCustomer ? 'cp-message--customer' : '' }}" wire:key="portal-message-{{ $message->id }}">
            <div class="cp-message-bubble"><p class="cp-message-author">{{ $isCustomer ? 'Tú' : ($message->sender?->name ?? 'Soporte Quantum') }}</p><div class="cp-message-text">{{ $message->message }}</div>
                @if($message->attachment_path)<a href="{{ route('private.ticket-message-attachment', $message) }}" target="_blank" rel="noopener" class="cp-message-attachment"><x-portal.icon name="download" />Ver adjunto</a>@endif
            </div>
            <p class="cp-message-time"><time datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->format('d/m/Y · H:i') }}</time>@if($isCustomer)<span>· {{ $message->read_at ? 'Leído' : 'Enviado' }}</span>@endif</p>
        </article>
    @empty
        <div class="cp-message-intro"><p>{{ $selectedTicket->description }}</p><span>Este es el inicio de tu conversación con Quantum.</span></div>
    @endforelse
</div>
@if(in_array($selectedTicket->status, [\App\Models\Ticket::STATUS_OPEN, \App\Models\Ticket::STATUS_IN_PROGRESS, \App\Models\Ticket::STATUS_WAITING_CUSTOMER]))
    <div class="cp-reply-area"><form wire:submit="sendReply" class="cp-reply-form">
        <div class="cp-field"><label class="cp-sr-only" for="ticket-reply">Mensaje para soporte</label><textarea id="ticket-reply" wire:model="replyMessage" rows="2" maxlength="5000" placeholder="Escribe tu mensaje…"></textarea></div>
        <div class="cp-reply-actions"><div class="cp-field"><label class="cp-reply-upload" for="ticket-reply-file"><x-portal.icon name="plus" />Adjuntar archivo</label><input id="ticket-reply-file" type="file" wire:model="replyAttachment" aria-label="Adjunto del mensaje"><span class="cp-caption" wire:loading wire:target="replyAttachment">Subiendo archivo…</span></div><button type="submit" class="cp-button cp-button--primary" wire:loading.attr="disabled" wire:target="sendReply,replyAttachment"><span wire:loading.remove wire:target="sendReply">Enviar</span><span wire:loading wire:target="sendReply">Enviando…</span><x-portal.icon name="arrow" /></button></div>
    </form>@error('replyMessage')<p class="cp-field-error" role="alert">{{ $message }}</p>@enderror @error('replyAttachment')<p class="cp-field-error" role="alert">{{ $message }}</p>@enderror</div>
@else
    <p class="cp-closed-ticket"><x-portal.icon name="check" />Este ticket está cerrado y no admite nuevos mensajes.</p>
@endif
