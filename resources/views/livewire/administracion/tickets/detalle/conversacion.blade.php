<div class="space-y-3">
    <h4 class="text-sm font-black text-gray-900">Conversación</h4>
    <div class="p-4 space-y-4 max-h-[55vh] overflow-y-auto bg-slate-50 border border-gray-100 rounded-2xl" id="admin-chat-messages">
        @foreach($selectedTicket->messages as $message)
            @php
                $isAdmin = $message->sender_type === 'admin';
                $url = $message->attachment_path
                    ? route('private.ticket-message-attachment', $message)
                    : null;
            @endphp
            <div class="flex {{ $isAdmin ? 'justify-end' : 'justify-start' }} max-w-full group">
                <div class="max-w-[85%] sm:max-w-[75%] rounded-2xl px-4 py-3 shadow-sm {{ $isAdmin ? 'bg-indigo-600 text-white rounded-br-sm' : 'bg-white text-slate-800 border border-slate-100 rounded-bl-sm' }}">
                    <p class="text-[10px] font-black uppercase tracking-widest mb-1 opacity-60 flex items-center justify-between gap-4">
                        <span>{{ $isAdmin ? 'Tú' : ($message->sender?->name ?? 'Usuario') }}</span>
                    </p>
                    <p class="text-sm whitespace-pre-line leading-relaxed">{!! nl2br(e($message->message)) !!}</p>
                    @if($url)
                        <a href="{{ $url }}" target="_blank" class="mt-3 flex items-center gap-2 text-xs font-bold bg-black/10 hover:bg-black/20 w-fit px-3 py-1.5 rounded-lg transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
                            Ver archivo adjunto
                        </a>
                    @endif
                    <div class="flex items-center justify-end gap-1 mt-2 -mb-1 opacity-80">
                        <span class="text-[9.5px] font-medium">
                            {{ $message->created_at->format('H:i') }}
                        </span>
                        @if($isAdmin)
                            <div class="flex items-center ml-0.5">
                                @if($message->read_at)
                                    <!-- Doble check visto (azul claro sobre fondo oscuro) -->
                                    <svg class="w-4 h-4 text-sky-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="18 6 9 17 4 12"></polyline>
                                        <polyline points="22 6 13 17 11 15"></polyline>
                                    </svg>
                                @else
                                    <!-- Doble check no visto (blanco semitransparente) -->
                                    <svg class="w-4 h-4 text-white/50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="18 6 9 17 4 12"></polyline>
                                        <polyline points="22 6 13 17 11 15"></polyline>
                                    </svg>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
<script>
    document.addEventListener('livewire:initialized', () => {
        const scrollToBottom = () => {
            const container = document.getElementById('admin-chat-messages');
            if (container) container.scrollTop = container.scrollHeight;
        };
        scrollToBottom();
        Livewire.hook('morph.updated', () => {
            scrollToBottom();
        });
    });
</script>
