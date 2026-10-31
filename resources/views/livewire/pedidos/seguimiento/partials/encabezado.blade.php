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
</header>
