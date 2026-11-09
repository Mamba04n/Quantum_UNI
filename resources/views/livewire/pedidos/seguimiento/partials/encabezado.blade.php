<header data-testid="order-status-header" class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
    <div>
        <p class="text-xs font-black uppercase tracking-[0.22em] text-slate-400">Modulo Order Status</p>
        <h1 class="mt-2 text-3xl font-black text-slate-950">Seguimiento {{ $orderStatus->order_code ?: ($orderStatus->saleOrder?->reference ?: 'Sin código') }}</h1>
        <p class="mt-2 max-w-4xl text-sm font-bold text-slate-500">
            <span class="font-black text-slate-700">Orden {{ $orderStatus->saleOrder?->reference ?: ('#'.$orderStatus->sale_order_id) }}</span>
            &middot;
            {{ $orderStatus->saleOrder?->client_name ?: $orderStatus->customer?->name ?: 'Sin cliente' }}
            &middot; Tracking {{ $orderStatus->tracking_code ?: 'N/D' }}
            &middot; {{ $statusService->humanStatus($orderStatus->current_status) }}
        </p>
    </div>

    <div class="flex flex-wrap items-center gap-3">
        @if($orderStatus->administrative_status !== 'cancelado_total' && $orderStatus->saleOrder?->status !== 'cancelado')
            @canany(['order_status.manage', 'supplier_cancellation.register'])
                <button
                    type="button"
                    wire:click="requestFullOrderCancellation"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-xs font-bold text-rose-700 shadow-sm transition hover:bg-rose-100 hover:border-rose-300 focus:outline-none focus:ring-2 focus:ring-rose-400"
                    title="Cancelar toda la orden y sus partidas pendientes"
                >
                    <svg class="h-4 w-4 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                    </svg>
                    <span>Cancelar orden completa</span>
                </button>
            @endcanany
        @endif

        <div class="grid grid-cols-3 gap-3 text-center">
            <div class="rounded-lg bg-white px-4 py-3 shadow-sm">
                <p class="text-[10px] font-black uppercase text-slate-400">Progreso</p>
                <p class="mt-1 text-xl font-black text-slate-950">{{ $orderStatus->progress_percentage }}%</p>
            </div>
            <div class="rounded-lg bg-white px-4 py-3 shadow-sm">
                <p class="text-[10px] font-black uppercase text-slate-400">Recibidos</p>
                <p class="mt-1 text-xl font-black text-slate-950">{{ $receivedCount }}/{{ $orderStatus->items->count() }}</p>
            </div>
            <div class="rounded-lg bg-white px-4 py-3 shadow-sm">
                <p class="text-[10px] font-black uppercase text-slate-400">Listos</p>
                <p class="mt-1 text-xl font-black text-slate-950">{{ $readyCount }}</p>
            </div>
        </div>
    </div>
</header>
