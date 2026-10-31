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
    </div>
</div>
