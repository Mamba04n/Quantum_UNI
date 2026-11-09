<div class="min-h-screen bg-slate-50 px-4 py-6 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-[2200px] space-y-6">
        @if(auth()->user()->can('public_tracking.view') || auth()->user()->can('payment_links.view'))
            <livewire:ventas.public-order-commerce :order-id="$orderStatus->sale_order_id" :key="'public-commerce-'.$orderStatus->sale_order_id" />
        @endif
        @php
            $statusService = app(\App\Services\OrderStatusService::class);
            $phaseKeys = array_keys($phaseDefinitions);
            $selectedGroup = $selectedGroupKey ? ($itemGroups[$selectedGroupKey] ?? null) : null;
            $isDetailMode = (bool) $selectedItem || $selectedGroupItems->isNotEmpty();
            $selectedEvents = $selectedItem
                ? $selectedItem->events
                : ($selectedGroupItems->isNotEmpty() ? $selectedGroupItems->flatMap->events : collect());
            $timelineEvents = $orderStatus->events->merge($selectedEvents)->sortByDesc('created_at')->take(8)->values();
            $kanbanColumns = collect($phaseDefinitions)->mapWithKeys(fn ($phaseData, $phase) => [$phase => collect()]);
            $visibleItemIds = $visibleItems->pluck('id')->all();
            $kanbanItems = $selectedItem
                ? collect([$selectedItem])
                : ($isDetailMode ? collect() : $visibleItems);

            foreach ($kanbanItems as $item) {
                if (in_array($item->id, $groupedItemIds, true)) {
                    continue;
                }

                $phaseKey = $this->phaseForBoardItem($item);
                if (! $phaseKey || ! array_key_exists($phaseKey, $phaseDefinitions)) {
                    $phaseKey = array_key_exists($orderStatus->current_phase, $phaseDefinitions)
                        ? $orderStatus->current_phase
                        : \App\Services\OrderStatusService::PHASE_PURCHASE;
                }
                $kanbanColumns[$phaseKey]->push($item);
            }
            $receivedCount = $orderStatus->items->filter(fn ($item) => (float) $item->quantity_received > 0)->count();
            $readyCount = $orderStatus->items->filter(fn ($item) => (float) $item->quantity_kardex_entry > (float) $item->quantity_kardex_output)->count();
        @endphp

        <x-order-context :sale-order="$orderStatus->saleOrder" :order-status="$orderStatus" />
        @include('livewire.pedidos.seguimiento.partials.status-pipeline')
        @include('livewire.pedidos.seguimiento.partials.alertas')
        @include('livewire.pedidos.seguimiento.partials.encabezado')
        @include('livewire.pedidos.seguimiento.partials.ordenes-compra-proveedor')
        @unless($isDetailMode)
            @include('livewire.pedidos.seguimiento.partials.tablero')
        @endunless
        @include('livewire.pedidos.seguimiento.partials.detalle-grupo')
        @include('livewire.pedidos.seguimiento.partials.detalle-partida')

        @if($showFullOrderCancelConfirmation)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/45 p-4" role="dialog" aria-modal="true" aria-labelledby="cancel-full-order-title">
                <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-rose-100 text-rose-700">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div>
                            <h3 id="cancel-full-order-title" class="text-lg font-black text-slate-950">Cancelar orden completa</h3>
                            <p class="text-xs text-slate-500">Esta acción cancelará todas las partidas activas y marcará la orden de venta como cancelada.</p>
                        </div>
                    </div>

                    {{-- Resumen de la orden --}}
                    <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3.5 text-xs space-y-1.5">
                        <div class="flex justify-between text-slate-700">
                            <span class="font-semibold">Orden:</span>
                            <span class="font-bold text-slate-900">{{ $orderStatus->order_code ?: ($orderStatus->saleOrder?->reference ?: '#'.$orderStatus->sale_order_id) }}</span>
                        </div>
                        <div class="flex justify-between text-slate-700">
                            <span class="font-semibold">Cliente:</span>
                            <span class="font-medium text-slate-800">{{ $orderStatus->saleOrder?->client_name ?: $orderStatus->customer?->name ?: 'N/D' }}</span>
                        </div>
                        <div class="flex justify-between text-slate-700">
                            <span class="font-semibold">Partidas a cancelar:</span>
                            <span class="font-bold text-rose-700">{{ $orderStatus->items->whereNotIn('status', [\App\Services\OrderStatusService::STATUS_DELIVERED, \App\Services\OrderStatusService::STATUS_CLOSED, \App\Services\OrderStatusService::STATUS_CANCELLED_SUPPLIER])->count() }} partida(s) pendiente(s)</span>
                        </div>
                    </div>

                    <div class="mt-4 space-y-3">
                        {{-- Motivo --}}
                        <div>
                            <label class="block text-[11px] font-black uppercase tracking-wide text-slate-600">
                                Motivo de cancelación de la orden <span class="text-rose-600">*</span>
                            </label>
                            <textarea wire:model="fullOrderCancelReason" rows="3" class="mt-1 w-full rounded-xl border border-slate-200 bg-white p-3 text-sm text-slate-800 shadow-sm placeholder:text-slate-400 focus:border-rose-400 focus:outline-none focus:ring-2 focus:ring-rose-200" placeholder="Ej.: El cliente solicitó anulación total por retrasos del fabricante..."></textarea>
                            @error('fullOrderCancelReason') <p class="mt-1 text-xs font-bold text-rose-700">{{ $message }}</p> @enderror
                        </div>

                        {{-- Doble verificación --}}
                        <div class="rounded-xl border border-rose-200 bg-rose-50/60 p-3">
                            <label class="flex items-start gap-2.5 cursor-pointer">
                                <input type="checkbox" wire:model.live="fullOrderCancelConfirmation" class="mt-0.5 h-4 w-4 rounded border-rose-300 text-rose-600 focus:ring-rose-400">
                                <span class="text-xs font-medium text-slate-800">
                                    Confirmo que deseo cancelar la orden completa y todas sus partidas pendientes. Esta acción es definitiva y quedará registrada en el historial.
                                </span>
                            </label>
                            @error('fullOrderCancelConfirmation') <p class="mt-1 text-xs font-bold text-rose-700">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" wire:click="closeFullOrderCancellation" class="rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition">
                            Volver sin cancelar
                        </button>
                        <button
                            type="button"
                            wire:click="cancelFullOrder"
                            @if(! $fullOrderCancelConfirmation) disabled @endif
                            class="rounded-xl bg-rose-600 px-4 py-2.5 text-xs font-black text-white shadow-sm transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-50"
                            wire:loading.attr="disabled"
                            wire:target="cancelFullOrder"
                        >
                            <span wire:loading.remove wire:target="cancelFullOrder">Sí, cancelar orden completa</span>
                            <span wire:loading wire:target="cancelFullOrder">Cancelando orden...</span>
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
