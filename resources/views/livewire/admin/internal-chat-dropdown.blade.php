<div class="relative" wire:poll.10s="checkNewMessages">
    <x-dropdown align="right" width="80">
        <x-slot name="trigger">
            <button type="button" class="relative inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200/80 bg-white text-slate-500 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <!-- Messenger Icon -->
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                </svg>
                @if($this->unreadTotal > 0)
                    <span class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 rounded-full bg-rose-600 text-white text-[10px] font-black flex items-center justify-center">{{ $this->unreadTotal > 9 ? '9+' : $this->unreadTotal }}</span>
                @endif
            </button>
        </x-slot>

        <x-slot name="content">
            <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                <p class="text-sm font-bold text-gray-900">Chats Internos</p>
                <a href="{{ route('admin.chat') }}" class="text-[10px] text-indigo-600 hover:text-indigo-800 font-bold flex items-center gap-1">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" /></svg>
                </a>
            </div>

            <div class="max-h-80 overflow-y-auto">
                @forelse($this->conversations as $conv)
                    @php 
                        $otherUser = $conv['user'];
                        $latestMsg = $conv['latest_message'];
                        $isUnread = $latestMsg->receiver_id === auth()->id() && is_null($latestMsg->read_at);
                    @endphp
                    <a href="{{ route('admin.chat') }}?user={{ $otherUser->id }}" class="block px-4 py-3 hover:bg-gray-50 border-b border-gray-50 transition-colors">
                        <div class="flex items-center gap-3">
                            <div class="relative w-10 h-10 rounded-full bg-gradient-to-tr from-indigo-500 to-purple-500 flex-shrink-0 flex items-center justify-center text-white text-sm font-bold uppercase shadow-sm">
                                {{ mb_strtoupper(mb_substr(trim($otherUser->name), 0, 2, 'UTF-8'), 'UTF-8') }}
                                @if($isUnread)
                                    <div class="absolute -right-0.5 -bottom-0.5 w-3 h-3 bg-rose-500 border-2 border-white rounded-full"></div>
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-bold text-gray-800 truncate {{ $isUnread ? 'text-gray-900' : '' }}">{{ $otherUser->name }}</p>
                                <div class="flex items-center gap-1 text-xs {{ $isUnread ? 'text-gray-900 font-semibold' : 'text-gray-500' }}">
                                    @if($latestMsg->user_id === auth()->id())
                                        <span class="text-[10px] text-gray-400">Tú:</span>
                                    @endif
                                    <p class="truncate">{{ $latestMsg->message }}</p>
                                    <span class="text-[10px] text-gray-400 shrink-0 ml-auto">&middot; {{ $latestMsg->created_at->diffForHumans(null, true, true) }}</span>
                                </div>
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="px-4 py-8 text-center text-sm text-gray-400 flex flex-col items-center justify-center gap-2">
                        <svg class="w-8 h-8 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                        </svg>
                        No hay conversaciones recientes.
                    </div>
                @endforelse
            </div>

            <div class="px-4 py-3 border-t border-gray-100 bg-gray-50 text-center rounded-b-md">
                <a href="{{ route('admin.chat') }}" class="text-sm font-bold text-indigo-600 hover:text-indigo-800 transition-colors">Ver todo en Messenger</a>
            </div>
        </x-slot>
    </x-dropdown>
</div>
