@if($selectedGroupItems->isNotEmpty())
    @php
        $groupQuantity = $selectedGroupItems->sum(fn ($groupItem) => (float) $groupItem->quantity_ordered);
        $groupConfirmed = $selectedGroupItems->sum(fn ($groupItem) => (float) $groupItem->quantity_confirmed);
        $groupShipped = $selectedGroupItems->sum(fn ($groupItem) => (float) $groupItem->quantity_shipped);
        $groupReceived = $selectedGroupItems->sum(fn ($groupItem) => (float) $groupItem->quantity_received);
        $groupDelivered = $selectedGroupItems->sum(fn ($groupItem) => (float) $groupItem->quantity_delivered);
        $groupSuppliers = $selectedGroupItems->pluck('supplier.name')->filter()->unique()->values();
        $groupEtas = $selectedGroupItems->pluck('estimated_arrival_date')->filter()->unique()->values();
        $groupHasMixedPhases = $selectedGroupItems->contains(fn ($groupItem) => $this->phaseForBoardItem($groupItem) !== $activePhase);
        $expectedDocument = collect($activePhaseData['required'] ?? [])->first();
        $isDispatchAuthorizationPhase = $activePhase === \App\Services\OrderStatusService::PHASE_KARDEX_OUTPUT;
        $groupUnauthorizedItems = $selectedGroupItems->reject(
            fn ($groupItem) => $groupItem->status === \App\Services\OrderStatusService::STATUS_APPROVED_PARTIAL_DISPATCH
        );
        $groupAuthorizationRequested = $groupUnauthorizedItems->isNotEmpty()
            && $groupUnauthorizedItems->every(
                fn ($groupItem) => $groupItem->status === \App\Services\OrderStatusService::STATUS_DISPATCH_AUTHORIZATION_REQUESTED
            );
        $groupIsComplete = $selectedGroupItems->every(
            fn ($groupItem) => in_array($groupItem->status, [
                \App\Services\OrderStatusService::STATUS_DELIVERED,
                \App\Services\OrderStatusService::STATUS_CLOSED,
            ], true)
        );
    @endphp
    <section id="order-status-group-detail" class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 p-5">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <div>
                    <div class="flex flex-wrap gap-2">
                        <span class="rounded-full bg-slate-950 px-3 py-1 text-[10px] font-black uppercase tracking-wide text-white">Viaje agrupado</span>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-black uppercase tracking-wide text-slate-700">{{ $activePhaseData['label'] }}</span>
                        <span class="rounded-full bg-indigo-50 px-3 py-1 text-[10px] font-black uppercase tracking-wide text-indigo-700">{{ $selectedGroupItems->count() }} piezas</span>
                    </div>
                    <h2 class="mt-3 text-2xl font-black text-slate-950">{{ $selectedGroup['label'] ?? 'Grupo de piezas' }}</h2>
                    <p class="mt-2 text-sm font-bold text-slate-500">
                        Proveedor {{ $groupSuppliers->isEmpty() ? 'Pendiente' : $groupSuppliers->join(', ') }}
                        &middot; ETA {{ $groupEtas->isEmpty() ? 'Sin fecha' : ($groupEtas->count() === 1 ? optional($groupEtas->first())->format('d/m/Y') : 'Fechas mixtas') }}
                    </p>
                </div>
                <div class="flex flex-col gap-3">
                    <div class="grid grid-cols-3 gap-3 text-center sm:grid-cols-5">
                        <div class="rounded-lg bg-slate-50 px-4 py-3"><p class="text-[10px] font-black uppercase text-slate-400">Ord</p><p class="mt-1 text-xl font-black text-slate-950">{{ number_format($groupQuantity, 0) }}</p></div>
                        <div class="rounded-lg bg-slate-50 px-4 py-3"><p class="text-[10px] font-black uppercase text-slate-400">Conf</p><p class="mt-1 text-xl font-black text-slate-950">{{ number_format($groupConfirmed, 0) }}</p></div>
                        <div class="rounded-lg bg-slate-50 px-4 py-3"><p class="text-[10px] font-black uppercase text-slate-400">Env</p><p class="mt-1 text-xl font-black text-slate-950">{{ number_format($groupShipped, 0) }}</p></div>
                        <div class="rounded-lg bg-slate-50 px-4 py-3"><p class="text-[10px] font-black uppercase text-slate-400">Rec</p><p class="mt-1 text-xl font-black text-slate-950">{{ number_format($groupReceived, 0) }}</p></div>
                        <div class="rounded-lg bg-slate-50 px-4 py-3"><p class="text-[10px] font-black uppercase text-slate-400">Ent</p><p class="mt-1 text-xl font-black text-slate-950">{{ number_format($groupDelivered, 0) }}</p></div>
                    </div>
                    <button type="button" wire:click="backToKanban" class="inline-flex items-center justify-center rounded-md border border-slate-200 bg-white px-4 py-2 text-xs font-black text-slate-700 shadow-sm hover:bg-slate-50">
                        Ver tablero completo
                    </button>
                </div>
            </div>
        </div>

        <div class="space-y-6 p-5">
            <section class="rounded-lg border border-indigo-200 bg-indigo-50/50 p-4">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-sm font-black text-slate-900">Avance coordinado del viaje</p>
                        <p class="mt-1 max-w-2xl text-xs font-bold leading-relaxed text-slate-600">El mismo documento quedará vinculado a cada pieza y el avance se registrará individualmente, pero en una sola acción para todo el viaje.</p>
                    </div>
                    <span class="shrink-0 rounded-full bg-white px-3 py-1.5 text-[10px] font-black uppercase text-indigo-700 ring-1 ring-indigo-200">{{ $activePhaseData['label'] }}</span>
                </div>

                @if($groupIsComplete)
                    <div class="mt-4 rounded-md border border-emerald-200 bg-emerald-50 p-5">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-lg font-black text-emerald-950">Viaje entregado y culminado</p>
                                <p class="mt-1 text-xs font-bold text-emerald-800">Todas las piezas fueron entregadas al cliente. El viaje queda disponible únicamente para consulta de documentos e historial.</p>
                            </div>
                            <span class="w-fit rounded-full bg-white px-4 py-2 text-xs font-black uppercase text-emerald-700 ring-1 ring-emerald-200">{{ number_format($groupDelivered, 0) }} unidades entregadas</span>
                        </div>
                    </div>
                @elseif($groupHasMixedPhases)
                    <div class="mt-4 rounded-md border border-amber-200 bg-amber-50 p-3">
                        <p class="text-xs font-bold text-amber-800">Este viaje conserva avances anteriores distintos. Puedes adjuntar el mismo comprobante para todas las piezas desde Documentos; no es necesario separarlas. Para avanzar una fase operativa, las piezas deben volver a estar alineadas.</p>
                    </div>
                @elseif($activePhase === \App\Services\OrderStatusService::PHASE_WAREHOUSE)
                    <div class="mt-4 rounded-md border border-amber-200 bg-amber-50 p-3 text-xs font-bold text-amber-800">La recepción exige condición, evidencia y costo por cada pieza. Regístrala desde cada partida para no mezclar controles de bodega.</div>
                @elseif($isDispatchAuthorizationPhase && $groupUnauthorizedItems->isNotEmpty())
                    <div class="mt-4 rounded-md border border-amber-200 bg-amber-50 p-4">
                        <p class="text-xs font-black text-amber-950">{{ $groupAuthorizationRequested ? 'Autorización de salida pendiente' : 'Este viaje requiere autorización de Operaciones' }}</p>
                        <p class="mt-1 text-[11px] font-bold text-amber-800">
                            {{ $groupAuthorizationRequested
                                ? 'La solicitud ya fue enviada. Operaciones debe aprobarla antes de registrar la salida Kardex.'
                                : 'Solicita el permiso para las '.$groupUnauthorizedItems->count().' piezas. La salida permanecerá bloqueada hasta su aprobación.' }}
                        </p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @if(!$groupAuthorizationRequested)
                                <button type="button" wire:click="requestGroupDispatchAuthorization" wire:loading.attr="disabled" wire:target="requestGroupDispatchAuthorization" class="rounded-md bg-amber-700 px-4 py-2.5 text-xs font-black text-white hover:bg-amber-800 disabled:opacity-60">
                                    <span wire:loading.remove wire:target="requestGroupDispatchAuthorization">Solicitar autorización de salida</span>
                                    <span wire:loading wire:target="requestGroupDispatchAuthorization">Enviando solicitud...</span>
                                </button>
                            @elseif(auth()->user()?->can('order_status.manage'))
                                <button type="button" wire:click="authorizeGroupDispatch" wire:confirm="¿Autorizar la salida Kardex de todas las piezas de este viaje?" wire:loading.attr="disabled" wire:target="authorizeGroupDispatch" class="rounded-md bg-emerald-700 px-4 py-2.5 text-xs font-black text-white hover:bg-emerald-800 disabled:opacity-60">
                                    <span wire:loading.remove wire:target="authorizeGroupDispatch">Autorizar salida del viaje</span>
                                    <span wire:loading wire:target="authorizeGroupDispatch">Autorizando...</span>
                                </button>
                            @else
                                <span class="rounded-md bg-white px-3 py-2 text-[10px] font-black uppercase text-amber-800 ring-1 ring-amber-200">Esperando a Operaciones</span>
                            @endif
                        </div>
                    </div>
                @else
                    @if($activePhase === \App\Services\OrderStatusService::PHASE_KARDEX_OUTPUT)
                        @php
                            $groupSubtotal = $selectedGroupItems->sum(function ($groupItem) {
                                $unitPrice = (float) ($groupItem->saleOrderItem?->unit_price ?? 0);
                                $qty = $this->defaultQuantityForPhase($groupItem, \App\Services\OrderStatusService::PHASE_KARDEX_OUTPUT);
                                return $qty * $unitPrice;
                            });
                            $calcIva = $invoiceApplyIva ? round($groupSubtotal * ($invoiceIvaRate / 100), 2) : 0;
                            $calcIr = $invoiceApplyIr ? round($groupSubtotal * ($invoiceIrRate / 100), 2) : 0;
                            $calcTotal = $groupSubtotal + $calcIva - $calcIr;
                        @endphp
                        <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-xs space-y-5">
                            {{-- Encabezado Factura estilo Odoo --}}
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-4">
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Factura de Cliente del Viaje (Talonario)</p>
                                    <div class="mt-1 flex items-center gap-2">
                                        <h3 class="text-xl font-black text-slate-950">Factura #</h3>
                                        <input wire:model.live="dispatchInvoiceNumber" type="text" class="w-28 rounded-xl border border-slate-200 bg-slate-50 px-3 py-1.5 text-base font-black text-slate-950 focus:bg-white focus:ring-2 focus:ring-slate-300" placeholder="03">
                                    </div>
                                    <p class="mt-0.5 text-xs text-slate-500">Salida de Kardex y emisión agrupada de talonario físico para {{ $selectedGroupItems->count() }} partidas.</p>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <a
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        data-navigate-ignore="true"
                                        href="{{ route('finance.invoices.preprinted-preview', [
                                            'order_status_id' => $orderStatus->id,
                                            'item_ids' => $selectedGroupItems->pluck('id')->join(','),
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
                                        <span>Ver machote del viaje</span>
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
                                </div>
                            </div>

                            {{-- Tabla de Líneas de Factura (Partidas y Precios) estilo Odoo --}}
                            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                                <div class="border-b border-slate-100 bg-slate-50/70 px-4 py-2.5">
                                    <p class="text-[11px] font-black uppercase tracking-wider text-slate-700">Líneas de Factura del Viaje</p>
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
                                            @foreach($selectedGroupItems as $gItem)
                                                @php
                                                    $gAvailable = max(0, (float) $gItem->quantity_kardex_entry - (float) $gItem->quantity_kardex_output);
                                                    $gQty = $this->defaultQuantityForPhase($gItem, \App\Services\OrderStatusService::PHASE_KARDEX_OUTPUT);
                                                    $gPrice = (float) ($gItem->saleOrderItem?->unit_price ?? 0);
                                                    $gLineTotal = $gQty * $gPrice;
                                                @endphp
                                                <tr>
                                                    <td class="px-4 py-3">
                                                        <p class="font-bold text-slate-900">{{ $gItem->part_name ?: $gItem->product?->name ?: 'Producto' }}</p>
                                                        <p class="text-[11px] font-medium text-slate-400">
                                                            [{{ $gItem->part_number ?: $gItem->product?->sku ?: '-' }}]
                                                            @if($gItem->brand || $gItem->model)
                                                                · {{ $gItem->brand }} {{ $gItem->model ? '/ '.$gItem->model : '' }}
                                                            @endif
                                                        </p>
                                                    </td>
                                                    <td class="px-4 py-3 text-center font-bold text-slate-500">{{ number_format($gAvailable, 0) }}</td>
                                                    <td class="px-4 py-3 text-center font-black text-slate-900">{{ $gQty }}</td>
                                                    <td class="px-4 py-3 text-right font-bold text-slate-700">C$ {{ number_format($gPrice, 2) }}</td>
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
                                                    <td class="px-4 py-3 text-right font-black text-slate-950">C$ {{ number_format($gLineTotal, 2) }}</td>
                                                </tr>
                                            @endforeach
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
                                        <span class="font-bold text-slate-900">C$ {{ number_format($groupSubtotal, 2) }}</span>
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
                    @endif
                    <form wire:submit.prevent="completeActivePhase" class="mt-4 grid gap-3 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] lg:items-end">
                        @if($activePhase === \App\Services\OrderStatusService::PHASE_KARDEX_OUTPUT)
                        <div class="rounded-md border border-violet-200 bg-white px-3 py-2 text-xs font-bold text-violet-800">El PDF se genera automaticamente; no requiere archivo manual.</div>
                        @else
                        <label class="block">
                            <span class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-500">{{ $expectedDocument ?: 'Documento de la fase' }}</span>
                            <input wire:model="documentFile" type="file" class="block w-full rounded-md border border-slate-200 bg-white p-2 text-xs">
                            @error('documentFile') <p class="mt-1 text-xs font-bold text-red-700">{{ $message }}</p> @enderror
                        </label>
                        @endif
                        <label class="block">
                            <span class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-500">Observación del viaje</span>
                            <input wire:model="notes" type="text" class="block w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-xs" placeholder="Tracking, guía, llegada o comentario">
                        </label>
                        <div class="flex flex-wrap items-center gap-2">
                            @if($previousStage = $this->previousStageInfo())
                                <button
                                    type="button"
                                    wire:click="revertStage"
                                    wire:loading.attr="disabled"
                                    wire:target="revertStage"
                                    class="rounded-md border border-amber-300 bg-amber-50 px-4 py-2.5 text-xs font-bold text-amber-800 hover:bg-amber-100 disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                    <span wire:loading.remove wire:target="revertStage">Volver a: {{ $previousStage['label'] }}</span>
                                    <span wire:loading wire:target="revertStage">Regresando...</span>
                                </button>
                            @endif
                            <button type="submit" wire:loading.attr="disabled" wire:target="completeActivePhase,documentFile" class="rounded-md bg-slate-950 px-5 py-2.5 text-xs font-black text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60">
                                <span wire:loading.remove wire:target="completeActivePhase">{{ $activePhase === \App\Services\OrderStatusService::PHASE_KARDEX_OUTPUT ? 'Emitir Factura del Viaje' : 'Guardar y avanzar '.$selectedGroupItems->count().' piezas' }}</span>
                                <span wire:loading wire:target="completeActivePhase">Procesando viaje...</span>
                            </button>
                        </div>
                    </form>
                    @error('phase') <p class="mt-2 text-xs font-bold text-red-700">{{ $message }}</p> @enderror
                    @error('document') <p class="mt-2 text-xs font-bold text-red-700">{{ $message }}</p> @enderror
                    @error('item') <p class="mt-2 text-xs font-bold text-red-700">{{ $message }}</p> @enderror
                    @error('authorization') <p class="mt-2 text-xs font-bold text-red-700">{{ $message }}</p> @enderror
                @endif
            </section>

            <section class="rounded-lg border border-slate-200">
                <div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h3 class="text-sm font-black text-slate-900">Items agrupados</h3>
                        <p class="mt-1 text-xs font-bold text-slate-500">Vista conjunta para piezas que viajan o se gestionan como un mismo paquete.</p>
                    </div>
                    <button type="button" wire:click="ungroupItems('{{ $selectedGroupKey }}')" class="rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-xs font-black text-slate-700 hover:bg-slate-50">Soltar grupo</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-[980px] w-full text-left text-xs">
                        <thead class="border-b border-slate-200 bg-white text-[10px] font-black uppercase text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Pieza</th>
                                <th class="px-4 py-3">Referencia</th>
                                <th class="px-4 py-3">Proveedor</th>
                                <th class="px-4 py-3 text-right">Ord</th>
                                <th class="px-4 py-3 text-right">Env</th>
                                <th class="px-4 py-3 text-right">Rec</th>
                                <th class="px-4 py-3 text-right">Ent</th>
                                <th class="px-4 py-3">ETA</th>
                                <th class="px-4 py-3">Estado</th>
                                <th class="px-4 py-3 text-right">Accion</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($selectedGroupItems as $groupItem)
                                <tr>
                                    <td class="px-4 py-3 font-black text-slate-900">{{ $groupItem->part_name ?: $groupItem->product?->name ?: 'Producto' }}</td>
                                    <td class="px-4 py-3 font-bold text-slate-600">{{ $groupItem->part_number ?: 'N/D' }}</td>
                                    <td class="px-4 py-3 font-bold text-slate-600">{{ $groupItem->supplier?->name ?: 'Pendiente' }}</td>
                                    <td class="px-4 py-3 text-right font-black">{{ number_format((float) $groupItem->quantity_ordered, 0) }}</td>
                                    <td class="px-4 py-3 text-right font-black">{{ number_format((float) $groupItem->quantity_shipped, 0) }}</td>
                                    <td class="px-4 py-3 text-right font-black">{{ number_format((float) $groupItem->quantity_received, 0) }}</td>
                                    <td class="px-4 py-3 text-right font-black">{{ number_format((float) $groupItem->quantity_delivered, 0) }}</td>
                                    <td class="px-4 py-3 font-bold text-slate-600">{{ optional($groupItem->estimated_arrival_date)->format('d/m/Y') ?: '-' }}</td>
                                    <td class="px-4 py-3 font-bold text-slate-600">{{ $statusService->humanStatus($groupItem->status) }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <button type="button" wire:click="selectItem({{ $groupItem->id }})" class="rounded-lg bg-slate-950 px-3 py-2 text-[10px] font-black text-white hover:bg-red-700">Abrir</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </section>
@endif
