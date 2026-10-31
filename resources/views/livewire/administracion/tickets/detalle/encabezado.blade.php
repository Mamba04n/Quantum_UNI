<div class="px-5 py-4 border-b border-gray-100">
    <div class="flex items-start justify-between gap-3">
        <div class="flex items-center gap-3">
            <button wire:click="$set('selectedTicketId', null)" class="xl:hidden p-1.5 rounded-lg bg-gray-100 text-gray-500 hover:bg-gray-200 hover:text-gray-900 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </button>
            <div>
                <h3 class="font-black text-gray-900">{{ $selectedTicket->subject }}</h3>
            <p class="text-xs font-mono text-gray-400">{{ $selectedTicket->code }}</p>
        </div>
        <span class="px-2 py-1 rounded-lg text-xs font-bold bg-gray-100 text-gray-700">{{ $statusLabels[$selectedTicket->status] ?? $selectedTicket->status }}</span>
    </div>
</div>
