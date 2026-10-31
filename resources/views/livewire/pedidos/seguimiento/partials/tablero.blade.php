<section class="space-y-4">
    <div class="grid gap-3 lg:grid-cols-[1fr_220px_auto]">
        <input wire:model.live.debounce.350ms="itemSearch" type="search" placeholder="Buscar producto, referencia, marca, modelo, proveedor o estado" class="w-full rounded-lg border-slate-200 bg-white px-4 py-3 text-sm shadow-sm">
        <select wire:model.live="itemFilter" class="rounded-lg border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-700 shadow-sm">
            <option value="all">Todos</option>
            <option value="pending">Pendientes</option>
            <option value="transit">En transito</option>
            <option value="warehouse">En bodega</option>
            <option value="received">Recibidos</option>
            <option value="ready">Listos para salida</option>
            <option value="delivered">Entregados</option>
            <option value="cancelled">Cancelados</option>
            <option value="incident">Con incidencia</option>
        </select>
                @if($isDetailMode)
            <button type="button" wire:click="backToKanban" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-5 py-3 text-xs font-black text-slate-700 shadow-sm hover:bg-slate-50">Ver tablero completo</button>
        @else
            <div class="flex items-center rounded-lg border border-slate-200 bg-slate-100 p-1 shadow-sm">
                <button type="button" wire:click="$set('viewMode', 'list')" class="flex-1 rounded-md px-4 py-2 text-xs font-black transition-colors {{ $viewMode === 'list' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-900' }}">Lista</button>
                <button type="button" wire:click="$set('viewMode', 'kanban')" class="flex-1 rounded-md px-4 py-2 text-xs font-black transition-colors {{ $viewMode === 'kanban' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-900' }}">Kanban</button>
            </div>
            <a href="{{ route('orders.status.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-5 py-3 text-xs font-black text-slate-700 shadow-sm hover:bg-slate-50">Volver al modulo</a>
        @endif
    </div>

    @if($isDetailMode)
        <div class="rounded-lg border border-slate-200 bg-white px-4 py-3 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Vista enfocada</p>
            <p class="mt-1 text-xs font-bold text-slate-500">El kanban muestra solo lo abierto para ubicar visualmente la fase actual. Vuelve al tablero completo para ver todas las partidas.</p>
        </div>
    @else
        <div class="flex flex-col gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3 shadow-sm lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Agrupar piezas</p>
                        <p class="mt-1 text-xs font-bold text-slate-500">Marca las partidas que viajan juntas. Si una OC ya aparece como grupo, usa “Seleccionar piezas” para combinarla con otras en un viaje físico.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <span class="inline-flex items-center rounded-lg bg-slate-100 px-3 py-2 text-xs font-black text-slate-700">{{ count($selectedGroupItemIds) }} seleccionadas</span>
                <button type="button" wire:click="createItemGroup" @disabled(count($selectedGroupItemIds) < 2) class="rounded-lg bg-slate-950 px-4 py-2.5 text-xs font-black text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50">Crear viaje</button>
                <button type="button" wire:click="clearGroupSelection" class="rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-xs font-black text-slate-700 hover:bg-slate-50">Limpiar</button>
            </div>
        </div>
        @error('group') <p class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-xs font-bold text-red-700">{{ $message }}</p> @enderror
    @endif

        @if($viewMode === 'list' && !$isDetailMode)
        <div class="space-y-4">
            @foreach($phaseDefinitions as $phase => $phaseData)
                @php
                    $columnItems = $kanbanColumns[$phase] ?? collect();
                    $sourceGroups = collect($itemGroups);
                    $columnGroups = $sourceGroups->filter(function ($group) use ($phase, $orderStatus, $phaseDefinitions, $visibleItemIds) {
                        $items = $orderStatus->items->whereIn('id', $group['item_ids'] ?? []);
                        if ($items->pluck('id')->intersect($visibleItemIds)->isEmpty()) {
                            return false;
                        }
                        $firstItem = $items->first();
                        $groupPhase = $firstItem ? $this->phaseForBoardItem($firstItem) : null;
                        return ($groupPhase ?: \App\Services\OrderStatusService::PHASE_PURCHASE) === $phase;
                    });
                    if ($columnItems->isEmpty() && $columnGroups->isEmpty()) continue;
                @endphp
                <div class="rounded-lg border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div class="bg-slate-50 border-b border-slate-200 px-4 py-3 flex items-center justify-between">
                        <h2 class="text-sm font-black text-slate-900">{{ $phaseData['label'] }}</h2>
                        <span class="inline-flex items-center justify-center rounded-md bg-slate-200 text-slate-700 px-2 py-1 text-xs font-black">{{ $columnItems->count() + $columnGroups->count() }}</span>
                    </div>
                    <div class="divide-y divide-slate-100">
                        @foreach($columnGroups as $groupKey => $group)
                            @php
                                $groupItems = $orderStatus->items->whereIn('id', $group['item_ids'] ?? [])->values();
                                $groupQuantity = $groupItems->sum(fn ($groupItem) => (float) $groupItem->quantity_ordered);
                                $supplierLabel = $group['supplier_label'] ?? 'Proveedor pendiente';
                            @endphp
                            <div class="flex items-center justify-between px-4 py-3 hover:bg-slate-50">
                                <div class="flex items-center gap-3">
                                    <span class="rounded-full bg-indigo-100 px-2 py-1 text-[9px] font-black uppercase text-indigo-800">Grupo</span>
                                    <div>
                                        <p class="text-sm font-black text-slate-950">{{ $group['label'] ?? 'Grupo de piezas' }}</p>
                                        <p class="text-xs text-slate-500">{{ $groupItems->count() }} piezas &middot; Total {{ number_format($groupQuantity, 0) }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-4">
                                    <p class="text-xs font-bold text-slate-500">{{ $supplierLabel }}</p>
                                    <button type="button" wire:click="selectGroupForTravel('{{ $groupKey }}')" class="rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-black text-slate-700 hover:bg-slate-50">Seleccionar piezas</button>
                                    <button type="button" wire:click="openGroup('{{ $groupKey }}')" class="rounded-md bg-slate-900 px-3 py-1.5 text-xs font-black text-white hover:bg-slate-800">Abrir</button>
                                </div>
                            </div>
                        @endforeach
                        
                        @foreach($columnItems as $item)
                            @php
                                $isCancelled = $item->status === \App\Services\OrderStatusService::STATUS_CANCELLED_SUPPLIER;
                            @endphp
                            <div class="flex items-center justify-between px-4 py-3 {{ $isCancelled ? 'bg-rose-50/70' : 'hover:bg-slate-50' }}">
                                <div class="flex items-center gap-3">
                                    @if($isCancelled)
                                        <span class="inline-flex h-5 w-5 items-center justify-center rounded border border-rose-200 bg-rose-100 text-[10px] font-black text-rose-700">×</span>
                                    @else
                                        <button type="button" wire:click="toggleGroupItem({{ $item->id }})" class="inline-flex h-5 w-5 items-center justify-center rounded border text-[10px] font-black {{ in_array($item->id, $selectedGroupItemIds, true) ? 'border-slate-950 bg-slate-950 text-white' : 'border-slate-300 bg-white text-transparent hover:border-slate-500' }}" aria-label="Seleccionar {{ $item->part_name ?: 'partida' }}">✓</button>
                                    @endif
                                    <div>
                                        <p class="text-sm font-black text-slate-950">{{ $item->part_name ?: $item->product?->name ?: 'Producto' }}</p>
                                        <p class="text-xs font-bold text-slate-500">{{ $item->part_number ?: 'Sin PN' }} &middot; Cant: {{ number_format((float) $item->quantity_ordered, 0) }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-4">
                                    <div class="text-right">
                                        <p class="text-xs font-bold text-slate-900">{{ $item->supplier?->name ?: 'Sin proveedor' }}</p>
                                        <p class="text-[10px] font-bold {{ $isCancelled ? 'text-rose-700' : 'text-slate-500' }}">{{ $statusService->humanStatus($item->status) }}</p>
                                    </div>
                                    @if($isCancelled)
                                        <button type="button" wire:click="openReplacementForm({{ $item->id }})" class="rounded-md bg-rose-600 px-3 py-1.5 text-xs font-black text-white hover:bg-rose-700">Reemplazar</button>
                                        <span class="rounded-md bg-rose-100 px-3 py-1.5 text-xs font-black text-rose-700">Cancelada</span>
                                    @else
                                        <button type="button" wire:click="selectItem({{ $item->id }})" class="rounded-md bg-slate-900 px-3 py-1.5 text-xs font-black text-white hover:bg-slate-800">Abrir</button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="overflow-x-auto pb-3" data-testid="order-status-kanban-board">
        <div class="flex min-w-max gap-4">
            @foreach($phaseDefinitions as $phase => $phaseData)
                @php
                    $columnItems = $kanbanColumns[$phase] ?? collect();
                    $sourceGroups = $selectedGroupKey && isset($itemGroups[$selectedGroupKey])
                        ? collect([$selectedGroupKey => $itemGroups[$selectedGroupKey]])
                        : ($isDetailMode ? collect() : collect($itemGroups));
                    $columnGroups = $sourceGroups->filter(function ($group) use ($phase, $orderStatus, $phaseDefinitions, $visibleItemIds, $isDetailMode) {
                        $items = $orderStatus->items->whereIn('id', $group['item_ids'] ?? []);
                        if (! $isDetailMode && $items->pluck('id')->intersect($visibleItemIds)->isEmpty()) {
                            return false;
                        }

                        $firstItem = $items->first();
                        $groupPhase = $firstItem ? $this->phaseForBoardItem($firstItem) : null;

                        return ($groupPhase ?: \App\Services\OrderStatusService::PHASE_PURCHASE) === $phase;
                    });
                    $phaseDocs = $statusDocuments->where('phase', $phase);
                    $isCurrentPhase = $orderStatus->current_phase === $phase || $activePhase === $phase;
                    $displayCount = $columnItems->count() + $columnGroups->count();
                @endphp
                <div class="flex min-h-[560px] w-[310px] shrink-0 flex-col rounded-lg border border-slate-200 bg-white shadow-sm" data-testid="order-status-phase-{{ $phase }}">
                    <button type="button" wire:click="setPhase('{{ $phase }}')" class="border-b border-slate-200 px-4 py-3 text-left hover:bg-slate-50">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-[10px] font-black uppercase text-slate-400">Fase order status</p>
                                <h2 class="mt-1 text-[13px] font-black leading-tight {{ $isCurrentPhase ? 'text-red-700' : 'text-slate-950' }}">{{ $phaseData['label'] }}</h2>
                            </div>
                            <span class="inline-flex h-7 min-w-7 items-center justify-center rounded-md {{ $isCurrentPhase ? 'bg-red-50 text-red-700' : 'bg-slate-100 text-slate-700' }} px-2 text-xs font-black">{{ $displayCount }}</span>
                        </div>
                        <p class="mt-2 text-[10px] font-bold text-slate-400">{{ $phaseDocs->count() }} docs adjuntos</p>
                    </button>

                    <div class="flex-1 space-y-3 p-3">
                        @foreach($columnGroups as $groupKey => $group)
                            @php
                                $groupItems = $orderStatus->items->whereIn('id', $group['item_ids'] ?? [])->values();
                                $groupQuantity = $groupItems->sum(fn ($groupItem) => (float) $groupItem->quantity_ordered);
                                $groupReceived = $groupItems->sum(fn ($groupItem) => (float) $groupItem->quantity_received);
                                $groupDelivered = $groupItems->sum(fn ($groupItem) => (float) $groupItem->quantity_delivered);
                                $groupReceivedPct = $groupQuantity > 0 ? min(100, round(($groupReceived / $groupQuantity) * 100)) : 0;
                                $groupDeliveredPct = $groupQuantity > 0 ? min(100, round(($groupDelivered / $groupQuantity) * 100)) : 0;
                                $isSelectedGroupCard = $selectedGroupKey === $groupKey;
                                $supplierLabel = $group['supplier_label'] ?? 'Proveedor pendiente';
                                $etaValues = $groupItems->pluck('estimated_arrival_date')->filter()->unique()->values();
                            @endphp
                            <article class="rounded-lg border bg-white p-3 shadow-sm transition hover:border-slate-300 {{ $isSelectedGroupCard ? 'border-slate-950 ring-2 ring-slate-950/10' : 'border-slate-200' }}" data-testid="order-status-group-card-{{ $groupKey }}">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-black leading-snug text-slate-950">{{ $group['label'] ?? 'Grupo de piezas' }}</p>
                                        <p class="mt-1 text-xs font-bold text-slate-600">{{ $groupItems->count() }} piezas agrupadas</p>
                                    </div>
                                    <span class="shrink-0 rounded-full bg-indigo-100 px-2 py-1 text-[9px] font-black uppercase text-indigo-800">Grupo</span>
                                </div>

                                <div class="mt-3 rounded-md bg-slate-50 px-3 py-2">
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <p class="text-[9px] font-black uppercase tracking-wide text-slate-400">Cantidad total</p>
                                            <p class="text-lg font-black leading-none text-slate-950">{{ number_format($groupQuantity, 0) }}</p>
                                        </div>
                                        <div class="min-w-0 text-right">
                                            <p class="truncate text-[10px] font-bold text-slate-400">{{ $supplierLabel }}</p>
                                            <p class="text-[10px] font-bold text-slate-400">{{ $etaValues->count() === 1 ? optional($etaValues->first())->format('d/m/Y') : ($etaValues->count() > 1 ? 'Fechas mixtas' : 'Sin ETA') }}</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-3 grid grid-cols-2 gap-2">
                                    <div class="rounded-md bg-emerald-50 px-2 py-2">
                                        <div class="flex justify-between text-[9px] font-black uppercase text-emerald-700"><span>Rec</span><span>{{ $groupReceivedPct }}%</span></div>
                                        <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-emerald-100"><div class="h-full rounded-full bg-emerald-500" style="width: {{ $groupReceivedPct }}%"></div></div>
                                    </div>
                                    <div class="rounded-md bg-sky-50 px-2 py-2">
                                        <div class="flex justify-between text-[9px] font-black uppercase text-sky-700"><span>Ent</span><span>{{ $groupDeliveredPct }}%</span></div>
                                        <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-sky-100"><div class="h-full rounded-full bg-sky-500" style="width: {{ $groupDeliveredPct }}%"></div></div>
                                    </div>
                                </div>

                                <div class="mt-3 grid grid-cols-[1fr_auto] gap-2">
                                    <button type="button" wire:click="openGroup('{{ $groupKey }}')" class="rounded-md bg-slate-950 px-3 py-2 text-[11px] font-black text-white hover:bg-red-700">Abrir grupo</button>
                                    <button type="button" wire:click="ungroupItems('{{ $groupKey }}')" class="rounded-md border border-slate-200 px-3 py-2 text-[11px] font-black text-slate-700 hover:bg-slate-50">Soltar</button>
                                </div>
                            </article>
                        @endforeach

                        @forelse($columnItems as $item)
                            @php
                                $isSelectedCard = $selectedItem?->id === $item->id;
                                $ordered = max(1, (float) $item->quantity_ordered);
                                $receivedPct = min(100, round(((float) $item->quantity_received / $ordered) * 100));
                                $deliveredPct = min(100, round(((float) $item->quantity_delivered / $ordered) * 100));
                                $hasAlert = in_array($item->status, [\App\Services\OrderStatusService::STATUS_CANCELLED_SUPPLIER, \App\Services\OrderStatusService::STATUS_REPLACEMENT_REQUIRED], true);
                            @endphp
                            <article class="rounded-lg border bg-white p-3 shadow-sm transition hover:border-slate-300 {{ $isSelectedCard ? 'border-slate-950 ring-2 ring-slate-950/10' : 'border-slate-200' }}" data-testid="order-status-item-row-{{ $item->id }}">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="line-clamp-2 text-sm font-black leading-snug text-slate-950">{{ $item->part_name ?: $item->product?->name ?: 'Producto' }}</p>
                                        <p class="mt-1 truncate text-xs font-bold text-slate-600">{{ $item->part_number ?: 'Sin PN' }}{{ $item->brand ? ' &middot; '.$item->brand : '' }}</p>
                                    </div>
                                    <button type="button" wire:click.stop="toggleGroupItem({{ $item->id }})" class="shrink-0 rounded-full px-2 py-1 text-[9px] font-black uppercase {{ in_array($item->id, $selectedGroupItemIds, true) ? 'bg-slate-950 text-white' : ($hasAlert ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600') }}">
                                        {{ in_array($item->id, $selectedGroupItemIds, true) ? 'Marcada' : ($hasAlert ? 'Alerta' : 'Agrupar') }}
                                    </button>
                                </div>

                                <div class="mt-3 flex items-center justify-between gap-3 rounded-md bg-slate-50 px-3 py-2">
                                    <div>
                                        <p class="text-[9px] font-black uppercase tracking-wide text-slate-400">Cantidad</p>
                                        <p class="text-lg font-black leading-none text-slate-950">{{ number_format((float) $item->quantity_ordered, 0) }}</p>
                                    </div>
                                    <div class="min-w-0 text-right">
                                        <p class="truncate text-[10px] font-bold text-slate-400">{{ $item->supplier?->name ?: 'Sin proveedor' }}</p>
                                        <p class="text-[10px] font-bold text-slate-400">{{ $statusService->humanStatus($item->status) }}</p>
                                    </div>
                                </div>

                                <div class="mt-3 grid grid-cols-2 gap-2">
                                    <div class="rounded-md bg-emerald-50 px-2 py-2">
                                        <div class="flex justify-between text-[9px] font-black uppercase text-emerald-700"><span>Rec</span><span>{{ $receivedPct }}%</span></div>
                                        <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-emerald-100"><div class="h-full rounded-full bg-emerald-500" style="width: {{ $receivedPct }}%"></div></div>
                                    </div>
                                    <div class="rounded-md bg-sky-50 px-2 py-2">
                                        <div class="flex justify-between text-[9px] font-black uppercase text-sky-700"><span>Ent</span><span>{{ $deliveredPct }}%</span></div>
                                        <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-sky-100"><div class="h-full rounded-full bg-sky-500" style="width: {{ $deliveredPct }}%"></div></div>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <button type="button" wire:click="selectItem({{ $item->id }})" class="w-full rounded-md bg-slate-950 px-3 py-2 text-[11px] font-black text-white hover:bg-red-700">Abrir partida</button>
                                </div>
                            </article>
                        @empty
                            @if($columnGroups->isEmpty())
                                <div class="rounded-lg border border-dashed border-slate-200 bg-white/70 p-5 text-center text-xs font-bold text-slate-400">
                                    {{ $isDetailMode ? 'Sin esta partida' : 'Sin partidas' }}
                                </div>
                            @endif
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

    @endif
