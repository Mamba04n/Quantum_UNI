<div class="min-h-screen bg-slate-50 p-6">
    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-xs font-black uppercase tracking-[0.22em] text-slate-400">Módulo de aprobación</p>
            <h1 class="mt-1 text-3xl font-black text-slate-950">Autorizar salidas parciales</h1>
            <p class="mt-2 max-w-3xl text-sm font-bold text-slate-500">Solicitudes de Bodega pendientes de autorización antes de registrar la salida de Kardex.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.approvals') }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition-colors hover:bg-gray-50 hover:text-gray-900">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 19-7-7 7-7"/></svg>
                Volver a todas las aprobaciones
            </a>
            <span class="w-fit rounded-full bg-amber-100 px-4 py-2 text-xs font-black text-amber-800">{{ $pendingItems->count() }} pendientes</span>
        </div>
    </div>

    @if(session('message'))
        <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-800">{{ session('message') }}</div>
    @endif

    <div class="space-y-6">
        @forelse($pendingOrders as $orderStatusId => $items)
            @php
                $first = $items->first();
                $saleOrder = $first->orderStatus?->saleOrder;
                $customer = $saleOrder?->customer?->name ?: $saleOrder?->client_name ?: 'Cliente no definido';
            @endphp
            <section class="overflow-hidden rounded-lg border border-amber-200 bg-white shadow-sm" wire:key="dispatch-order-{{ $orderStatusId }}">
                <div class="flex flex-col gap-3 border-b border-amber-100 bg-amber-50 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-amber-600 px-2.5 py-1 text-[10px] font-black uppercase text-white">Autorización pendiente</span>
                            <span class="font-mono text-xs font-black text-slate-700">{{ $first->orderStatus?->tracker?->tracking_code ?: 'Pedido #'.$saleOrder?->id }}</span>
                        </div>
                        <h2 class="mt-2 text-lg font-black text-slate-950">{{ $customer }}</h2>
                        <p class="mt-1 text-xs font-bold text-slate-500">{{ $items->count() }} {{ $items->count() === 1 ? 'partida solicita' : 'partidas solicitan' }} salida parcial</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @if($saleOrder)
                            <a href="{{ route('orders.status.show', $saleOrder) }}" class="rounded-lg border border-amber-200 bg-white px-4 py-2.5 text-xs font-black text-amber-900 hover:bg-amber-100">Ver seguimiento</a>
                        @endif
                        @if($canApprove && $items->count() > 1)
                            <button type="button" wire:click="authorizeOrder({{ $orderStatusId }})" wire:confirm="¿Autorizar todas las salidas parciales pendientes de este pedido?" wire:loading.attr="disabled" class="rounded-lg bg-emerald-700 px-4 py-2.5 text-xs font-black text-white hover:bg-emerald-800 disabled:opacity-60">Autorizar todo el pedido</button>
                        @endif
                    </div>
                </div>

                <div class="divide-y divide-slate-100">
                    @foreach($items as $item)
                        <div class="grid gap-4 px-5 py-4 md:grid-cols-[minmax(0,1fr)_auto_auto] md:items-center" wire:key="dispatch-item-{{ $item->id }}">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-black text-slate-950">{{ $item->part_name ?: $item->product?->name ?: 'Producto' }}</p>
                                <p class="mt-1 text-xs font-bold text-slate-500">{{ $item->part_number ?: 'Sin referencia' }} · Proveedor {{ $item->supplier?->name ?: 'Pendiente' }}</p>
                                <p class="mt-1 text-[10px] font-bold uppercase text-slate-400">Solicitada {{ $item->updated_at?->format('d/m/Y H:i') }} por {{ $item->events->firstWhere('event_type', 'dispatch.authorization_requested')?->user?->name ?: 'Sistema' }}</p>
                            </div>
                            <div class="grid grid-cols-3 gap-2 text-center text-xs">
                                <div class="rounded-md bg-slate-50 px-3 py-2"><span class="block text-[9px] font-black uppercase text-slate-400">Entrada</span><strong>{{ number_format((float) $item->quantity_kardex_entry, 0) }}</strong></div>
                                <div class="rounded-md bg-slate-50 px-3 py-2"><span class="block text-[9px] font-black uppercase text-slate-400">Salida</span><strong>{{ number_format((float) $item->quantity_kardex_output, 0) }}</strong></div>
                                <div class="rounded-md bg-emerald-50 px-3 py-2 text-emerald-800"><span class="block text-[9px] font-black uppercase">Disponible</span><strong>{{ number_format(max(0, (float) $item->quantity_kardex_entry - (float) $item->quantity_kardex_output), 0) }}</strong></div>
                            </div>
                            @if($canApprove)
                                <button type="button" wire:click="authorizeItem({{ $item->id }})" wire:confirm="¿Autorizar la salida parcial de esta partida?" wire:loading.attr="disabled" class="rounded-lg bg-emerald-700 px-4 py-2.5 text-xs font-black text-white hover:bg-emerald-800 disabled:opacity-60">Autorizar salida</button>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @empty
            <section class="rounded-lg border border-emerald-200 bg-emerald-50 p-10 text-center">
                <p class="text-lg font-black text-emerald-900">No hay salidas parciales pendientes</p>
                <p class="mt-2 text-sm font-bold text-emerald-700">Las nuevas solicitudes de Bodega aparecerán aquí automáticamente.</p>
            </section>
        @endforelse

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4"><h2 class="text-xs font-black uppercase tracking-widest text-slate-600">Autorizadas pendientes de registrar en Kardex</h2></div>
            <div class="divide-y divide-slate-100">
                @forelse($recentlyApproved as $item)
                    <div class="flex flex-col gap-2 px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div><p class="text-sm font-black text-slate-900">{{ $item->part_number ?: $item->part_name ?: 'Partida #'.$item->id }}</p><p class="text-xs font-bold text-slate-500">{{ $item->orderStatus?->tracker?->tracking_code ?: 'Pedido #'.$item->orderStatus?->sale_order_id }}</p></div>
                        <span class="rounded-full bg-emerald-50 px-3 py-1 text-[10px] font-black uppercase text-emerald-700">Autorizada {{ $item->updated_at?->format('d/m/Y H:i') }}</span>
                    </div>
                @empty
                    <p class="px-5 py-8 text-center text-sm font-bold text-slate-400">Sin autorizaciones recientes.</p>
                @endforelse
            </div>
        </section>
    </div>
</div>
