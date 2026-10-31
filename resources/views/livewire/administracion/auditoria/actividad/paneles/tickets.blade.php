<section class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-sm font-black text-gray-900 uppercase tracking-widest">Tickets</h3>
        <span class="text-xs text-gray-400">{{ $todayTickets }} hoy</span>
    </div>
    <div class="divide-y divide-gray-100 max-h-[360px] overflow-y-auto">
        @forelse($ticketLogs as $ticket)
            <div class="px-5 py-3 text-xs">
                <p class="font-black text-gray-800">{{ $ticket->subject }}</p>
                <p class="mt-1 text-gray-500">{{ $ticket->customer?->name ?? 'Cliente' }} &middot; {{ \App\Models\Ticket::$statusLabels[$ticket->status] ?? $ticket->status }}</p>
            </div>
        @empty
            <div class="px-5 py-8 text-center text-xs text-gray-400">Sin tickets recientes.</div>
        @endforelse
    </div>
</section>
