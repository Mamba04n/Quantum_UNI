<div class="w-full flex-1 flex overflow-hidden bg-white relative">
    
    <!-- Sidebar: Lista de Contactos -->
    <div class="relative w-80 flex-shrink-0 border-r border-gray-200 flex flex-col bg-gray-50/50" wire:poll.10s>
        <div class="p-4 border-b border-gray-200 bg-white flex items-center justify-between">
            <div>
                <h3 class="font-bold text-gray-900 text-lg">Chats</h3>
                <p class="text-xs text-gray-500 mt-1">Tus conversaciones recientes.</p>
            </div>
            <button wire:click="$set('showNewChatModal', true)" class="w-8 h-8 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center hover:bg-indigo-100 transition-colors shadow-sm" title="Nuevo chat">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
            </button>
        </div>

        <!-- Nuevo Chat Modal / Panel Flotante -->
        @if($showNewChatModal)
            <div class="absolute inset-0 z-20 flex" x-data="{ searchFocus() { $refs.searchInput.focus() } }" x-init="setTimeout(() => searchFocus(), 100)">
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-gray-900/20 backdrop-blur-sm" wire:click="$set('showNewChatModal', false)"></div>
                
                <!-- Panel Lateral que sale de la izquierda -->
                <div class="relative w-80 h-full bg-white shadow-xl flex flex-col animate-[slideRight_0.2s_ease-out]">
                    <div class="p-4 bg-indigo-600 text-white flex items-center gap-3">
                        <button wire:click="$set('showNewChatModal', false)" class="text-indigo-100 hover:text-white transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                        </button>
                        <h3 class="font-bold text-lg">Nuevo Chat</h3>
                    </div>
                    
                    <div class="p-3 border-b border-gray-100">
                        <div class="relative">
                            <svg class="w-4 h-4 absolute left-3 top-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                            <input wire:model.live.debounce.300ms="searchUser" x-ref="searchInput" type="text" placeholder="Buscar compañero..." class="w-full bg-gray-50 border-transparent focus:border-indigo-500 focus:bg-white focus:ring-0 text-sm rounded-xl pl-9 py-2 transition-all">
                        </div>
                    </div>
                    
                    <div class="flex-1 overflow-y-auto">
                        @forelse($this->availableUsers as $user)
                            <button wire:click="selectUser({{ $user->id }})" class="w-full text-left p-3 hover:bg-gray-50 flex items-center gap-3 transition-colors border-b border-gray-50">
                                <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-indigo-500 to-purple-500 flex items-center justify-center text-white text-sm font-bold uppercase">
                                    {{ mb_strtoupper(mb_substr(trim($user->name), 0, 2, 'UTF-8'), 'UTF-8') }}
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-gray-900">{{ $user->name }}</p>
                                    <p class="text-xs text-gray-500">{{ $user->role_label ?? 'Personal' }}</p>
                                </div>
                            </button>
                        @empty
                            <div class="p-8 text-center text-gray-400 text-sm">
                                No se encontraron usuarios.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
            
            <style>
                @keyframes slideRight {
                    from { transform: translateX(-100%); }
                    to { transform: translateX(0); }
                }
            </style>
        @endif
        
        <div class="flex-1 overflow-y-auto">
            @foreach($this->contacts as $contact)
                @php 
                    $user = $contact['user'];
                    $latestMsg = $contact['latest_message'];
                    $unread = $contact['unread_count'];
                    $isActive = $this->selectedUserId === $user->id;
                @endphp
                <button wire:click="selectUser({{ $user->id }})" class="w-full text-left p-4 border-b border-gray-100 hover:bg-white transition-colors {{ $isActive ? 'bg-white border-l-4 border-l-indigo-600' : 'border-l-4 border-l-transparent' }}">
                    <div class="flex items-center gap-3">
                        <div class="relative w-12 h-12 rounded-full bg-gradient-to-tr from-indigo-500 to-purple-500 flex-shrink-0 flex items-center justify-center text-white text-lg font-bold uppercase shadow-sm">
                            {{ mb_strtoupper(mb_substr(trim($user->name), 0, 2, 'UTF-8'), 'UTF-8') }}
                            @if($unread > 0)
                                <div class="absolute -right-1 -top-1 w-5 h-5 bg-rose-500 border-2 border-white rounded-full flex items-center justify-center text-[9px] font-bold text-white">{{ $unread > 9 ? '9+' : $unread }}</div>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-bold text-gray-900 truncate">{{ $user->name }}</p>
                                @if($latestMsg)
                                    <span class="text-[10px] text-gray-400 shrink-0">{{ $latestMsg->created_at->format('H:i') }}</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-1 text-sm {{ $unread > 0 ? 'text-gray-900 font-semibold' : 'text-gray-500' }}">
                                @if($latestMsg)
                                    @if($latestMsg->user_id === auth()->id())
                                        <span class="text-xs text-gray-400">Tú:</span>
                                    @endif
                                    <p class="truncate text-xs">{{ $latestMsg->message }}</p>
                                @else
                                    <p class="truncate text-xs text-gray-400 italic">Haz clic para iniciar chat</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </button>
            @endforeach
        </div>
    </div>

    <!-- Main Content: Área de Chat -->
    <div class="flex-1 flex flex-col bg-white">
        @if($this->selectedUserId)
            @php
                $activeUser = \App\Models\User::find($this->selectedUserId);
            @endphp
            <!-- Header del Chat Activo -->
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between bg-white shadow-sm z-10">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-indigo-500 to-purple-500 flex items-center justify-center text-white text-sm font-bold uppercase shadow-sm">
                        {{ mb_strtoupper(mb_substr(trim($activeUser->name), 0, 2, 'UTF-8'), 'UTF-8') }}
                    </div>
                    <div>
                        <h2 class="font-bold text-gray-900 text-lg leading-tight">{{ $activeUser->name }}</h2>
                        <p class="text-xs text-indigo-600 font-medium">{{ $activeUser->role_label ?? 'Personal' }}</p>
                    </div>
                </div>
            </div>

            <!-- Lista de Mensajes -->
            <div x-data="{ scrollToBottom() { this.$refs.chatScroll.scrollTop = this.$refs.chatScroll.scrollHeight; } }" 
                 x-init="scrollToBottom(); Livewire.on('messageSent', () => { setTimeout(() => scrollToBottom(), 50) })"
                 wire:poll.2s
                 class="flex-1 overflow-y-auto p-6 bg-gray-50 flex flex-col gap-4" x-ref="chatScroll">
                
                @forelse($this->activeConversation as $msg)
                    @php $isMe = $msg->user_id === auth()->id(); @endphp
                    <div class="flex flex-col {{ $isMe ? 'items-end' : 'items-start' }}">
                        <div class="flex items-end gap-2 max-w-[70%] {{ $isMe ? 'flex-row-reverse' : '' }}">
                            <div class="relative group">
                                <div class="px-5 py-3 rounded-2xl text-[15px] shadow-sm {{ $isMe ? 'bg-indigo-600 text-white rounded-br-none' : 'bg-white border border-gray-100 text-gray-800 rounded-bl-none' }}">
                                    {!! nl2br(e($msg->message)) !!}
                                </div>
                                <div class="text-[10px] text-gray-400 mt-1 flex items-center gap-1 {{ $isMe ? 'justify-end mr-1' : 'ml-1' }}">
                                    {{ $msg->created_at->format('d M Y, H:i') }}
                                    @if($isMe)
                                        @if($msg->read_at)
                                            <svg class="w-3 h-3 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7M5 13l4 4L19 7" /></svg>
                                        @else
                                            <svg class="w-3 h-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="flex-1 flex flex-col items-center justify-center text-gray-400 gap-4 h-full">
                        <div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center">
                            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4-.8L3 20l1.3-3.4A7.4 7.4 0 013 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                            </svg>
                        </div>
                        <p class="text-sm font-medium">Inicia la conversación con {{ $activeUser->name }}</p>
                    </div>
                @endforelse
            </div>

            <!-- Área de Input -->
            <div class="p-4 bg-white border-t border-gray-200">
                <form wire:submit.prevent="sendMessage" class="flex items-end gap-3" x-data="{ submitOnEnter(e) { if(e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); $wire.sendMessage().then(() => { $dispatch('messageSent') }); } } }">
                    <div class="flex-1 relative">
                        <textarea wire:model="newMessage" required
                                  x-on:keydown="submitOnEnter"
                                  rows="1"
                                  class="w-full bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-2xl focus:ring-indigo-500 focus:border-indigo-500 block px-4 py-3 resize-none transition-shadow min-h-[44px] max-h-[120px]" 
                                  placeholder="Escribe tu mensaje..."></textarea>
                    </div>
                    <button type="submit" class="flex-shrink-0 flex items-center justify-center w-12 h-12 bg-indigo-600 hover:bg-indigo-700 text-white rounded-full transition-colors shadow-sm focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <svg class="w-5 h-5 transform rotate-90 translate-x-px" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z" />
                        </svg>
                    </button>
                </form>
            </div>
        @else
            <!-- Estado Vacío -->
            <div class="flex-1 flex flex-col items-center justify-center text-gray-500 gap-4 h-full bg-gray-50/50">
                <div class="w-24 h-24 rounded-full bg-indigo-50 flex items-center justify-center">
                    <svg class="w-12 h-12 text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900">Tus Mensajes</h3>
                <p class="text-sm">Selecciona un chat de la lista de contactos para empezar a enviar mensajes.</p>
            </div>
        @endif
    </div>
</div>
