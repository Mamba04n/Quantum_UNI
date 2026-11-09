@if($selectedItem)
    @php
        $isDeliveryComplete = in_array($selectedItem->status, [
            \App\Services\OrderStatusService::STATUS_DELIVERED,
            \App\Services\OrderStatusService::STATUS_CLOSED,
        ], true);
    @endphp
    <section id="order-status-item-detail" class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-4 py-4">
            <div class="grid gap-4 lg:grid-cols-[minmax(260px,1fr)_420px_auto] lg:items-start">
                <div class="min-w-0">
                    <div class="flex flex-wrap gap-2">
                        <span class="rounded-full bg-slate-950 px-2.5 py-1 text-[9px] font-black uppercase tracking-wide text-white">Order Status</span>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[9px] font-black uppercase tracking-wide text-slate-700">{{ $activePhaseData['label'] }}</span>
                        <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-[9px] font-black uppercase tracking-wide text-emerald-700">{{ $statusService->humanStatus($selectedItem->status) }}</span>
                    </div>
                    <h2 class="mt-2 truncate text-xl font-black text-slate-950">{{ $selectedItem->part_name ?: $selectedItem->product?->name ?: 'Producto' }}</h2>
                    <p class="mt-2 text-sm font-bold text-slate-500">{{ $selectedItem->part_number ?: 'Sin referencia' }} &middot; {{ $selectedItem->brand ?: '-' }} {{ $selectedItem->model ? '/ '.$selectedItem->model : '' }} &middot; Proveedor {{ $selectedItem->supplier?->name ?: 'Pendiente' }}</p>
                </div>
                <div class="grid grid-cols-6 gap-1.5 text-center">
                    <div class="rounded-md bg-slate-50 px-2 py-2"><p class="text-[9px] font-black uppercase text-slate-400">Ord</p><p class="text-base font-black text-slate-950">{{ number_format((float) $selectedItem->quantity_ordered, 0) }}</p></div>
                    <div class="rounded-md bg-slate-50 px-2 py-2"><p class="text-[9px] font-black uppercase text-slate-400">Conf</p><p class="text-base font-black text-slate-950">{{ number_format((float) $selectedItem->quantity_confirmed, 0) }}</p></div>
                    <div class="rounded-md bg-slate-50 px-2 py-2"><p class="text-[9px] font-black uppercase text-slate-400">Env</p><p class="text-base font-black text-slate-950">{{ number_format((float) $selectedItem->quantity_shipped, 0) }}</p></div>
                    <div class="rounded-md bg-slate-50 px-2 py-2"><p class="text-[9px] font-black uppercase text-slate-400">Rec</p><p class="text-base font-black text-slate-950">{{ number_format((float) $selectedItem->quantity_received, 0) }}</p></div>
                    <div class="rounded-md bg-slate-50 px-2 py-2"><p class="text-[9px] font-black uppercase text-slate-400">Sal</p><p class="text-base font-black text-slate-950">{{ number_format((float) $selectedItem->quantity_kardex_output, 0) }}</p></div>
                    <div class="rounded-md bg-slate-50 px-2 py-2"><p class="text-[9px] font-black uppercase text-slate-400">Desp</p><p class="text-base font-black text-slate-950">{{ number_format((float) $selectedItem->quantity_dispatched, 0) }}</p></div>
                </div>
                <button type="button" wire:click="backToKanban" class="inline-flex items-center justify-center rounded-md border border-slate-200 bg-white px-4 py-2 text-xs font-black text-slate-700 shadow-sm hover:bg-slate-50 lg:self-start">
                    Ver tablero completo
                </button>
            </div>
        </div>

        <div class="p-4">
            <main class="space-y-4">
                @if($selectedItem->status === \App\Services\OrderStatusService::STATUS_WAITING_OTHER_ITEMS)
                    @php
                        $waitingItems = $orderStatus->items->whereIn('id', $selectedItem->waiting_for_item_ids ?: []);
                    @endphp
                    <section class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="text-sm font-black text-amber-900">Esta partida está reservada para una entrega conjunta</p>
                                <p class="mt-1 text-xs font-bold text-amber-800">No debe salir de Kardex hasta que se resuelvan las partidas seleccionadas abajo.</p>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" wire:click="prepareWaitForOtherItems" class="rounded-md bg-white px-3 py-2 text-xs font-black text-amber-900 ring-1 ring-amber-200 hover:bg-amber-100">Editar espera</button>
                                <button type="button" wire:click="authorizePartialDispatch" wire:confirm="¿Las partidas esperadas ya están listas para salir juntas?" class="rounded-md bg-amber-700 px-3 py-2 text-xs font-black text-white hover:bg-amber-800">Liberar para salida</button>
                            </div>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @forelse($waitingItems as $waitingItem)
                                <span class="rounded-md bg-white px-3 py-2 text-xs font-bold text-slate-700 ring-1 ring-amber-200">
                                    {{ $waitingItem->part_number ?: $waitingItem->part_name ?: 'Partida #'.$waitingItem->id }}
                                    <span class="ml-1 text-slate-400">· {{ $statusService->humanStatus($waitingItem->status) }}</span>
                                </span>
                            @empty
                                <span class="text-xs font-bold text-amber-800">Aún no se registraron partidas relacionadas.</span>
                            @endforelse
                        </div>
                    </section>
                @endif

                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
                    <div class="flex flex-col gap-1 border-b border-slate-100 bg-slate-50/50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">{{ $isDeliveryComplete ? 'Entrega completada' : 'Avance de partida: '.$activePhaseData['label'] }}</h3>
                            <p class="text-[11px] font-medium text-slate-500">{{ $isDeliveryComplete ? 'Esta partida culminó su proceso.' : 'Completa los datos necesarios y avanza a la siguiente fase.' }}</p>
                        </div>
                    </div>

                    @if($isDeliveryComplete)
                        <div class="p-4">
                            <div class="rounded-xl border border-emerald-200 bg-emerald-50/80 p-4">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-600 text-sm font-black text-white">✓</span>
                                    <div>
                                        <p class="text-sm font-black text-emerald-950">Producto entregado al cliente</p>
                                        <p class="text-xs text-emerald-800">El proceso de esta partida terminó.</p>
                                    </div>
                                </div>
                                <div class="mt-3 grid gap-2 sm:grid-cols-3">
                                    <div class="rounded-lg bg-white/90 p-2.5"><p class="text-[9px] font-black uppercase text-emerald-700">Cantidad</p><p class="text-base font-black text-slate-950">{{ number_format((float) $selectedItem->quantity_delivered, 0) }}</p></div>
                                    <div class="rounded-lg bg-white/90 p-2.5"><p class="text-[9px] font-black uppercase text-emerald-700">Fecha de entrega</p><p class="text-xs font-bold text-slate-950">{{ optional($selectedItem->delivered_at)->format('d/m/Y H:i') ?: 'Registrada' }}</p></div>
                                    <div class="rounded-lg bg-white/90 p-2.5"><p class="text-[9px] font-black uppercase text-emerald-700">Estado</p><p class="text-xs font-bold text-slate-950">{{ $statusService->humanStatus($selectedItem->status) }}</p></div>
                                </div>
                            </div>
                        </div>
                    @elseif($activePhase === \App\Services\OrderStatusService::PHASE_WAREHOUSE)
                        <div class="p-4">
                            @if((float) $selectedItem->quantity_received > 0 || (float) $selectedItem->quantity_kardex_entry > 0)
                                <div class="rounded-xl border border-emerald-200 bg-emerald-50/80 p-4">
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <p class="text-xs font-black text-emerald-950">Recepción registrada por Bodega</p>
                                            <p class="text-xs text-emerald-800">Los datos de entrada fueron capturados desde Órdenes por recibir.</p>
                                        </div>
                                        <a href="{{ route('warehouse.incoming-orders') }}" class="inline-flex h-8 items-center rounded-xl bg-white px-3 text-xs font-bold text-emerald-800 shadow-xs ring-1 ring-emerald-200 hover:bg-emerald-100">Ver en Bodega</a>
                                    </div>
                                    <div class="mt-3 grid gap-2 sm:grid-cols-4">
                                        <div class="rounded-lg bg-white/90 p-2.5"><p class="text-[9px] font-black uppercase text-emerald-700">Recibido</p><p class="text-sm font-black text-slate-950">{{ number_format((float) $selectedItem->quantity_received, 2) }}</p></div>
                                        <div class="rounded-lg bg-white/90 p-2.5"><p class="text-[9px] font-black uppercase text-emerald-700">Kardex entrada</p><p class="text-sm font-black text-slate-950">{{ number_format((float) $selectedItem->quantity_kardex_entry, 2) }}</p></div>
                                        <div class="rounded-lg bg-white/90 p-2.5"><p class="text-[9px] font-black uppercase text-emerald-700">Fecha</p><p class="text-xs font-bold text-slate-950">{{ optional($selectedItem->warehouse_received_date)->format('d/m/Y') ?: '-' }}</p></div>
                                        <div class="rounded-lg bg-white/90 p-2.5"><p class="text-[9px] font-black uppercase text-emerald-700">Estado</p><p class="text-xs font-bold text-slate-950">{{ $statusService->humanStatus($selectedItem->status) }}</p></div>
                                    </div>
                                </div>
                            @else
                                <div class="rounded-xl border border-amber-200 bg-amber-50/80 p-4">
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <p class="text-xs font-black text-amber-950">Pendiente de recepción por Bodega</p>
                                            <p class="text-xs text-amber-800">Bodega debe registrar la llegada física desde Órdenes por recibir para crear la entrada al Kardex.</p>
                                        </div>
                                        <a href="{{ route('warehouse.incoming-orders') }}" class="inline-flex h-8 items-center rounded-xl bg-white px-3 text-xs font-bold text-amber-900 shadow-xs ring-1 ring-amber-200 hover:bg-amber-100">Abrir Bodega</a>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="p-4 space-y-4">
                            {{-- Inputs de la fase activa --}}
                            @if($activePhase === \App\Services\OrderStatusService::PHASE_SUPPLIER_CONFIRMATION)
                                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                    <label class="block"><span class="text-[10px] font-black uppercase text-slate-500">Cantidad confirmada *</span><input wire:model="quantity" type="number" step="1" min="1" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-200"></label>
                                    <label class="block"><span class="text-[10px] font-black uppercase text-slate-500">Fecha confirmación</span><input wire:model="supplierConfirmedAt" type="date" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-200"></label>
                                    <label class="block"><span class="text-[10px] font-black uppercase text-slate-500">ETA estimado</span><input wire:model="supplierEta" type="date" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-200"></label>
                                    <label class="block"><span class="text-[10px] font-black uppercase text-slate-500">Referencia proveedor</span><input wire:model="supplierReference" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-200" placeholder="Proforma, PO aceptada"></label>
                                </div>
                            @elseif($activePhase === \App\Services\OrderStatusService::PHASE_TRANSIT)
                                <div class="max-w-xs">
                                    <label class="block"><span class="text-[10px] font-black uppercase text-slate-500">Cantidad que sale del proveedor *</span><input wire:model="quantity" type="number" step="1" min="1" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-200"></label>
                                </div>
                            @elseif($activePhase === \App\Services\OrderStatusService::PHASE_CUSTOMS)
                                <div class="max-w-xs">
                                    <label class="block"><span class="text-[10px] font-black uppercase text-slate-500">Cantidad que entra a aduana *</span><input wire:model="quantity" type="number" step="1" min="1" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-200"></label>
                                </div>
                            @elseif($activePhase === \App\Services\OrderStatusService::PHASE_KARDEX_OUTPUT)
                                @php
                                    $unitPrice = (float) ($selectedItem->saleOrderItem?->unit_price ?? 0);
                                    $availableQty = max(0, (float) $selectedItem->quantity_kardex_entry - (float) $selectedItem->quantity_kardex_output);
                                    $calcSubtotal = $quantity * $unitPrice;
                                    $calcIva = $invoiceApplyIva ? round($calcSubtotal * ($invoiceIvaRate / 100), 2) : 0;
                                    $calcIr = $invoiceApplyIr ? round($calcSubtotal * ($invoiceIrRate / 100), 2) : 0;
                                    $calcTotal = $calcSubtotal + $calcIva - $calcIr;
                                @endphp
                                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs space-y-5">
                                    {{-- Encabezado Factura estilo Odoo --}}
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-4">
                                        <div>
                                            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Factura de Cliente (Talonario)</p>
                                            <div class="mt-1 flex items-center gap-2">
                                                <h3 class="text-xl font-black text-slate-950">Factura #</h3>
                                                <input wire:model.live="dispatchInvoiceNumber" type="text" class="w-28 rounded-xl border border-slate-200 bg-slate-50 px-3 py-1.5 text-base font-black text-slate-950 focus:bg-white focus:ring-2 focus:ring-slate-300" placeholder="03">
                                            </div>
                                            <p class="mt-0.5 text-xs text-slate-500">Salida de Kardex y emisión de talonario físico para esta entrega.</p>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <a
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                data-navigate-ignore="true"
                                                href="{{ route('finance.invoices.preprinted-preview', [
                                                    'order_status_id' => $orderStatus->id,
                                                    'item_ids' => $selectedItem->id,
                                                    'quantity' => $quantity,
                                                    'invoice_number' => $dispatchInvoiceNumber ?: '01',
                                                    'customer_name' => $invoiceCustomerName,
                                                    'customer_ruc' => $invoiceCustomerRuc,
                                                    'customer_address' => $invoiceCustomerAddress,
                                                    'customer_phone' => $invoiceCustomerPhone,
                                                    'issue_date' => $invoiceIssueDate,
                                                    'due_date' => $invoiceDueDate,
                                                    'condition' => $invoiceCondition,
                                                    'seller' => $invoiceSeller,
                                                    'apply_iva' => $invoiceApplyIva ? 1 : 0,
                                                    'apply_ir' => $invoiceApplyIr ? 1 : 0,
                                                    'iva_rate' => $invoiceIvaRate,
                                                    'ir_rate' => $invoiceIrRate,
                                                ]) }}"
                                                class="inline-flex h-9 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 text-xs font-bold text-slate-700 shadow-xs hover:bg-slate-50 transition"
                                            >
                                                <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                <span>Ver machote</span>
                                            </a>
                                        </div>
                                    </div>

                                    {{-- Bloque de 2 columnas con metadatos del cliente y factura --}}
                                    <div class="grid gap-6 md:grid-cols-2">
                                        {{-- Columna Izquierda: Datos del Cliente --}}
                                        <div class="space-y-3">
                                            <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Datos del Cliente</p>
                                            <div>
                                                <label class="block text-[11px] font-bold text-slate-600">Cliente / Razón Social *</label>
                                                <input wire:model.live="invoiceCustomerName" type="text" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-900 focus:ring-2 focus:ring-slate-300" placeholder="Nombre completo">
                                            </div>
                                            <div class="grid grid-cols-2 gap-2">
                                                <div>
                                                    <label class="block text-[11px] font-bold text-slate-600">RUC / Cédula</label>
                                                    <input wire:model.live="invoiceCustomerRuc" type="text" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-900 focus:ring-2 focus:ring-slate-300" placeholder="RUC">
                                                </div>
                                                <div>
                                                    <label class="block text-[11px] font-bold text-slate-600">Teléfono</label>
                                                    <input wire:model.live="invoiceCustomerPhone" type="text" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-900 focus:ring-2 focus:ring-slate-300" placeholder="Teléfono">
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-[11px] font-bold text-slate-600">Dirección</label>
                                                <input wire:model.live="invoiceCustomerAddress" type="text" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-900 focus:ring-2 focus:ring-slate-300" placeholder="Dirección exacta">
                                            </div>
                                        </div>

                                        {{-- Columna Derecha: Condiciones de Factura --}}
                                        <div class="space-y-3">
                                            <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Términos y Fechas</p>
                                            <div class="grid grid-cols-2 gap-2">
                                                <div>
                                                    <label class="block text-[11px] font-bold text-slate-600">Fecha de Factura</label>
                                                    <input wire:model.live="invoiceIssueDate" type="date" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-900 focus:ring-2 focus:ring-slate-300">
                                                </div>
                                                <div>
                                                    <label class="block text-[11px] font-bold text-slate-600">Fecha Vencimiento</label>
                                                    <input wire:model.live="invoiceDueDate" type="date" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-900 focus:ring-2 focus:ring-slate-300">
                                                </div>
                                            </div>
                                            <div class="grid grid-cols-2 gap-2">
                                                <div>
                                                    <label class="block text-[11px] font-bold text-slate-600">Términos de Pago</label>
                                                    <select wire:model.live="invoiceCondition" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-900 focus:ring-2 focus:ring-slate-300">
                                                        <option value="contado">Contado</option>
                                                        <option value="credito">Crédito (30 días)</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-[11px] font-bold text-slate-600">Vendedor</label>
                                                    <input wire:model.live="invoiceSeller" type="text" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-900 focus:ring-2 focus:ring-slate-300" placeholder="Responsable">
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-[11px] font-bold text-slate-600">Cantidad a Facturar (Kardex Salida) *</label>
                                                <input wire:model.live="quantity" type="number" step="1" min="1" max="{{ max(1, (int)$availableQty) }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-black text-slate-900 focus:ring-2 focus:ring-slate-300">
                                                @error('quantity') <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p> @enderror
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Tabla de Líneas de Factura (Partidas y Precios) estilo Odoo --}}
                                    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                                        <div class="border-b border-slate-100 bg-slate-50/70 px-4 py-2.5">
                                            <p class="text-[11px] font-black uppercase tracking-wider text-slate-700">Líneas de Factura / Partidas</p>
                                        </div>
                                        <div class="overflow-x-auto">
                                            <table class="w-full text-left text-xs">
                                                <thead class="border-b border-slate-100 bg-slate-50/30 text-[10px] font-black uppercase tracking-wider text-slate-500">
                                                    <tr>
                                                        <th class="px-4 py-2.5">Producto / Referencia</th>
                                                        <th class="px-4 py-2.5 text-center">Disponible</th>
                                                        <th class="px-4 py-2.5 text-center">Cantidad</th>
                                                        <th class="px-4 py-2.5 text-right">Precio Unitario</th>
                                                        <th class="px-4 py-2.5 text-center">Impuestos</th>
                                                        <th class="px-4 py-2.5 text-right">Subtotal</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-slate-100">
                                                    <tr>
                                                        <td class="px-4 py-3">
                                                            <p class="font-bold text-slate-900">{{ $selectedItem->part_name ?: $selectedItem->product?->name ?: 'Producto' }}</p>
                                                            <p class="text-[11px] font-medium text-slate-400">
                                                                [{{ $selectedItem->part_number ?: $selectedItem->product?->sku ?: '-' }}]
                                                                @if($selectedItem->brand || $selectedItem->model)
                                                                    · {{ $selectedItem->brand }} {{ $selectedItem->model ? '/ '.$selectedItem->model : '' }}
                                                                @endif
                                                            </p>
                                                        </td>
                                                        <td class="px-4 py-3 text-center font-bold text-slate-500">{{ number_format($availableQty, 0) }}</td>
                                                        <td class="px-4 py-3 text-center font-black text-slate-900">{{ $quantity }}</td>
                                                        <td class="px-4 py-3 text-right font-bold text-slate-700">C$ {{ number_format($unitPrice, 2) }}</td>
                                                        <td class="px-4 py-3 text-center">
                                                            <div class="inline-flex flex-wrap justify-center gap-1">
                                                                @if($invoiceApplyIva)
                                                                    <span class="rounded-md bg-violet-100 px-1.5 py-0.5 text-[10px] font-bold text-violet-800">15% IVA</span>
                                                                @endif
                                                                @if($invoiceApplyIr)
                                                                    <span class="rounded-md bg-rose-100 px-1.5 py-0.5 text-[10px] font-bold text-rose-800">2% IR</span>
                                                                @endif
                                                                @if(!$invoiceApplyIva && !$invoiceApplyIr)
                                                                    <span class="text-[11px] text-slate-400">0%</span>
                                                                @endif
                                                            </div>
                                                        </td>
                                                        <td class="px-4 py-3 text-right font-black text-slate-950">C$ {{ number_format($calcSubtotal, 2) }}</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    {{-- Sección Inferior: Configuración de Impuestos (Izq) y Resumen de Totales (Der) --}}
                                    <div class="grid gap-6 sm:grid-cols-2 sm:items-start pt-2">
                                        <div class="rounded-xl border border-slate-100 bg-slate-50/60 p-4 space-y-2.5">
                                            <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Opciones de Impuestos y Retenciones</p>
                                            <div class="space-y-2">
                                                <label class="flex cursor-pointer items-center gap-2.5">
                                                    <input wire:model.live="invoiceApplyIva" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-400">
                                                    <span class="text-xs font-bold text-slate-700">Aplicar IVA (15%) a la factura</span>
                                                </label>
                                                <label class="flex cursor-pointer items-center gap-2.5">
                                                    <input wire:model.live="invoiceApplyIr" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-400">
                                                    <span class="text-xs font-bold text-slate-700">Aplicar Retención en la Fuente IR (2%)</span>
                                                </label>
                                            </div>
                                        </div>

                                        <div class="space-y-2 border-t border-slate-100 pt-3 sm:border-t-0 sm:pt-0">
                                            <div class="flex items-center justify-between text-xs text-slate-600 font-semibold px-2">
                                                <span>Subtotal sin impuestos:</span>
                                                <span class="font-bold text-slate-900">C$ {{ number_format($calcSubtotal, 2) }}</span>
                                            </div>
                                            @if($invoiceApplyIva)
                                                <div class="flex items-center justify-between text-xs text-violet-700 font-semibold px-2">
                                                    <span>IVA 15%:</span>
                                                    <span class="font-bold">+ C$ {{ number_format($calcIva, 2) }}</span>
                                                </div>
                                            @endif
                                            @if($invoiceApplyIr)
                                                <div class="flex items-center justify-between text-xs text-rose-700 font-semibold px-2">
                                                    <span>Retención IR 2%:</span>
                                                    <span class="font-bold">- C$ {{ number_format($calcIr, 2) }}</span>
                                                </div>
                                            @endif
                                            <div class="flex items-center justify-between rounded-xl bg-slate-900 px-4 py-3 text-white">
                                                <span class="text-xs font-black uppercase tracking-wider">Total Factura:</span>
                                                <span class="text-base font-black">C$ {{ number_format($calcTotal, 2) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @elseif($activePhase === \App\Services\OrderStatusService::PHASE_LOGISTICS)
                                <div class="max-w-xs">
                                    <label class="block"><span class="text-[10px] font-black uppercase text-slate-500">Cantidad enviada al cliente *</span><input wire:model="quantity" type="number" step="1" min="1" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-200"></label>
                                </div>
                            @elseif($activePhase === \App\Services\OrderStatusService::PHASE_DELIVERY)
                                <div class="max-w-xs">
                                    <label class="block"><span class="text-[10px] font-black uppercase text-slate-500">Cantidad entregada al cliente *</span><input wire:model="quantity" type="number" step="1" min="1" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-200"></label>
                                </div>
                            @endif

                            {{-- Archivo opcional y notas --}}
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="block">
                                    <span class="text-[10px] font-black uppercase text-slate-500">Adjuntar evidencia o documento (opcional)</span>
                                    <input wire:model="documentFile" type="file" class="mt-1 w-full rounded-xl border border-slate-200 bg-white p-2 text-xs focus:outline-none">
                                    @error('documentFile') <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p> @enderror
                                </label>
                                <label class="block">
                                    <span class="text-[10px] font-black uppercase text-slate-500">Observaciones (opcional)</span>
                                    <input wire:model="notes" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-xs focus:ring-2 focus:ring-slate-200" placeholder="Comentario o referencia">
                                </label>
                            </div>

                            @error('phase') <p class="text-xs font-bold text-rose-600">{{ $message }}</p> @enderror
                            @error('document') <p class="text-xs font-bold text-rose-600">{{ $message }}</p> @enderror
                            @error('item') <p class="text-xs font-bold text-rose-600">{{ $message }}</p> @enderror
                            @error('quantity') <p class="text-xs font-bold text-rose-600">{{ $message }}</p> @enderror
                            @error('authorization') <p class="text-xs font-bold text-rose-600">{{ $message }}</p> @enderror

                            {{-- Botones de acción --}}
                            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3">
                                <button
                                    wire:click="requestSupplierCancellation"
                                    type="button"
                                    class="inline-flex h-9 items-center rounded-xl border border-rose-200 bg-white px-3.5 text-xs font-bold text-rose-600 shadow-xs transition hover:border-rose-300 hover:bg-rose-50"
                                >
                                    Incidencia: cancelar por proveedor
                                </button>

                                <div class="flex flex-wrap items-center gap-2">
                                    @if($previousStage = $this->previousStageInfo())
                                        <button
                                            type="button"
                                            wire:click="revertStage"
                                            wire:loading.attr="disabled"
                                            wire:target="revertStage"
                                            class="inline-flex h-9 items-center justify-center rounded-xl border border-amber-300 bg-amber-50 px-4 text-xs font-bold text-amber-800 shadow-xs hover:bg-amber-100 transition disabled:opacity-60"
                                        >
                                            <span wire:loading.remove wire:target="revertStage">Volver a: {{ $previousStage['label'] }}</span>
                                            <span wire:loading wire:target="revertStage">Regresando...</span>
                                        </button>
                                    @endif

                                    <button
                                        wire:click="completeActivePhase"
                                        type="button"
                                        class="inline-flex h-9 items-center gap-1.5 rounded-xl bg-slate-900 px-5 text-xs font-bold text-white shadow-xs transition hover:bg-black disabled:opacity-60"
                                        wire:loading.attr="disabled"
                                        wire:target="completeActivePhase,documentFile"
                                    >
                                        <svg class="h-4 w-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        <span wire:loading.remove wire:target="completeActivePhase">
                                            {{ $activePhase === \App\Services\OrderStatusService::PHASE_KARDEX_OUTPUT ? 'Emitir Factura' : ($documentFile ? 'Guardar documento y avanzar fase' : 'Avanzar a la siguiente fase') }}
                                        </span>
                                        <span wire:loading wire:target="completeActivePhase">Avanzando...</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif
                </section>


                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
                    <div class="border-b border-slate-100 bg-slate-50/50 px-4 py-3">
                        <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Documentos financieros</h3>
                    </div>
                    <div class="divide-y divide-slate-100">
                        @forelse ($financialDocuments as $document)
                            <div class="grid grid-cols-[1fr_auto] gap-3 px-4 py-3 text-sm">
                                <div class="min-w-0">
                                    <p class="font-black text-slate-900">{{ $document['type'] }} - {{ $document['number'] }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ strtoupper($document['currency'] ?? 'NIO') === 'NIO' ? 'C$' : strtoupper($document['currency']) }} {{ number_format((float) $document['amount'], 2) }}</p>
                                    @if(!empty($document['amount_original']) && !empty($document['original_currency']))
                                        <p class="mt-0.5 text-[10px] text-slate-400">{{ $document['original_currency'] }} {{ number_format((float) $document['amount_original'], 2) }}@if(!empty($document['exchange_rate'])) · tasa {{ number_format((float) $document['exchange_rate'], 4) }}@endif</p>
                                    @endif
                                </div>
                                @if($document['url'])
                                    <a href="{{ $document['url'] }}" class="self-center rounded-lg border border-slate-200 px-3 py-2 text-xs font-black text-slate-700 hover:bg-slate-50">Bajar</a>
                                @else
                                    <span class="self-center text-xs font-bold text-rose-500">Sin archivo</span>
                                @endif
                            </div>
                        @empty
                            <p class="px-4 py-5 text-sm font-bold text-slate-500">Sin documentos financieros.</p>
                        @endforelse
                    </div>
                </section>
            </main>
        </div>
    </section>

    @if($showCancelConfirmation && !$isDeliveryComplete)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/45 p-4" role="dialog" aria-modal="true" aria-labelledby="cancel-order-title">
            <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-rose-100 text-rose-700">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div>
                        <h3 id="cancel-order-title" class="text-lg font-black text-slate-950">Confirmar cancelación de partida</h3>
                        <p class="text-xs text-slate-500">Esta acción registrará que el proveedor no abastecerá esta partida.</p>
                    </div>
                </div>

                {{-- Resumen de la partida --}}
                <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3.5 text-xs">
                    <div class="flex justify-between font-semibold text-slate-700">
                        <span>Partida:</span>
                        <span class="font-bold text-slate-900">{{ $selectedItem->product?->name ?: $selectedItem->description ?: 'Partida #'.$selectedItem->id }}</span>
                    </div>
                    @if($selectedItem->part_number)
                        <div class="mt-1 flex justify-between text-slate-600">
                            <span>No. de parte / Código:</span>
                            <span class="font-mono font-medium text-slate-800">{{ $selectedItem->part_number }}</span>
                        </div>
                    @endif
                    <div class="mt-1 flex justify-between text-slate-600">
                        <span>Cantidad ordenada:</span>
                        <span class="font-bold text-slate-800">{{ (float) $selectedItem->quantity_ordered }}</span>
                    </div>
                </div>

                <div class="mt-4 space-y-3">
                    {{-- Proveedor a cancelar --}}
                    <div>
                        <label class="block text-[11px] font-black uppercase tracking-wide text-slate-600">
                            Proveedor a cancelar
                        </label>
                        <select wire:model="cancelSupplierId" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm focus:border-rose-400 focus:outline-none focus:ring-2 focus:ring-rose-200">
                            <option value="">Selecciona o confirma el proveedor...</option>
                            @foreach($replacementSuppliers as $supplier)
                                <option value="{{ $supplier->id }}">{{ $supplier->name }}{{ $supplier->contact_name ? ' · '.$supplier->contact_name : '' }}</option>
                            @endforeach
                        </select>
                        @error('cancelSupplierId') <p class="mt-1 text-xs font-bold text-rose-700">{{ $message }}</p> @enderror
                    </div>

                    {{-- Motivo de cancelación --}}
                    <div>
                        <label class="block text-[11px] font-black uppercase tracking-wide text-slate-600">
                            Motivo de cancelación <span class="text-rose-600">*</span>
                        </label>
                        <textarea wire:model="cancelReason" rows="3" class="mt-1 w-full rounded-xl border border-slate-200 bg-white p-3 text-sm text-slate-800 shadow-sm placeholder:text-slate-400 focus:border-rose-400 focus:outline-none focus:ring-2 focus:ring-rose-200" placeholder="Ej.: El proveedor notificó que la pieza está descontinuada / sin disponibilidad."></textarea>
                        @error('cancelReason') <p class="mt-1 text-xs font-bold text-rose-700">{{ $message }}</p> @enderror
                    </div>

                    {{-- Doble verificación --}}
                    <div class="rounded-xl border border-rose-200 bg-rose-50/60 p-3">
                        <label class="flex items-start gap-2.5 cursor-pointer">
                            <input type="checkbox" wire:model.live="cancelConfirmation" class="mt-0.5 h-4 w-4 rounded border-rose-300 text-rose-600 focus:ring-rose-400">
                            <span class="text-xs font-medium text-slate-800">
                                Confirmo la cancelación definitiva de esta partida por parte del proveedor. Entiendo que quedará registrada en la trazabilidad del pedido.
                            </span>
                        </label>
                        @error('cancelConfirmation') <p class="mt-1 text-xs font-bold text-rose-700">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" wire:click="closeSupplierCancellation" class="rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition">
                        Volver sin cancelar
                    </button>
                    <button
                        type="button"
                        wire:click="cancelBySupplier"
                        @if(! $cancelConfirmation) disabled @endif
                        class="rounded-xl bg-rose-600 px-4 py-2.5 text-xs font-black text-white shadow-sm transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-50"
                        wire:loading.attr="disabled"
                        wire:target="cancelBySupplier"
                    >
                        <span wire:loading.remove wire:target="cancelBySupplier">Confirmar cancelación de partida</span>
                        <span wire:loading wire:target="cancelBySupplier">Cancelando...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if($showReplacementForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/45 p-4" role="dialog" aria-modal="true" aria-labelledby="replacement-order-title">
            <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-amber-100 text-lg font-black text-amber-800">↻</div>
                <h3 id="replacement-order-title" class="mt-4 text-lg font-black text-slate-950">Gestionar reemplazo de partida</h3>
                <p class="mt-2 text-sm leading-6 text-slate-600">El proveedor canceló esta pieza. Puedes iniciar el reemplazo ahora; el portal del cliente mostrará que la partida está siendo gestionada y conservará el motivo de la incidencia.</p>
                <label class="mt-4 block">
                    <span class="text-[10px] font-black uppercase tracking-wide text-slate-500">Instrucción para reemplazo</span>
                    <textarea wire:model="replacementReason" rows="4" class="mt-1 w-full rounded-lg border-slate-200 text-sm" placeholder="Proveedor alterno, nueva cotización o acción requerida."></textarea>
                    @error('replacementReason') <p class="mt-1 text-xs font-bold text-rose-700">{{ $message }}</p> @enderror
                </label>
                <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" wire:click="closeReplacementForm" class="rounded-lg border border-slate-200 px-4 py-2.5 text-xs font-black text-slate-700 hover:bg-slate-50">No reemplazar por ahora</button>
                    <button type="button" wire:click="saveReplacementCandidate" class="rounded-lg bg-amber-600 px-4 py-2.5 text-xs font-black text-white hover:bg-amber-700" wire:loading.attr="disabled" wire:target="saveReplacementCandidate,replacementDocuments">
                        <span wire:loading.remove wire:target="saveReplacementCandidate">Registrar propuesta</span>
                        <span wire:loading wire:target="saveReplacementCandidate">Registrando...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
@endif
