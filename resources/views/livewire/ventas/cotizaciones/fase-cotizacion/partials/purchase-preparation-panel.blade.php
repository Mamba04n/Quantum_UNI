@php
    $activePurchaseOrders = $selectedQuote->purchaseOrders
        ->reject(fn ($order) => in_array($order->status, ['anulado', 'cancelado', 'cancelled'], true))
        ->values();
@endphp

<section class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm" data-testid="purchase-preparation-panel">
    <div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h3 class="text-sm font-black text-slate-900">Preparación de orden de compra</h3>
            <p class="mt-1 text-xs font-bold text-slate-500">Confirma cada partida o abre la fila para revisar y cambiar su proveedor.</p>
        </div>
        @if($activePurchaseOrders->isEmpty())
            <div class="flex flex-wrap items-end gap-3">
                <label class="block min-w-52">
                    <span class="mb-1 block text-[10px] font-black uppercase tracking-wider text-slate-500">Moneda de la orden</span>
                    <select wire:model="purchaseOrderCurrency" class="h-10 w-full rounded-lg border-slate-300 bg-white px-3 text-xs font-bold text-slate-700 shadow-sm">
                        <option value="USD">Dólares (USD)</option>
                        <option value="NIO">Córdobas (NIO)</option>
                    </select>
                    @error('purchaseOrderCurrency') <span class="mt-1 block text-xs font-semibold text-red-600">{{ $message }}</span> @enderror
                </label>
                <button type="button" wire:click="generatePurchaseOrdersBySupplier" class="rounded-lg bg-slate-950 px-4 py-2.5 text-xs font-black text-white hover:bg-slate-800">Preparar OC y enviar a aprobación</button>
            </div>
        @endif
    </div>

    @if($activePurchaseOrders->isNotEmpty())
        <div class="m-4 rounded-lg border border-amber-200 bg-amber-50 p-4">
            <h4 class="text-sm font-black text-amber-800">Esperando aprobación administrativa</h4>
            <p class="mt-1 text-xs font-bold text-amber-700">
                {{ $activePurchaseOrders->count() === 1 ? 'Hay 1 borrador de orden de compra pendiente de aprobación.' : 'Hay '.$activePurchaseOrders->count().' borradores de órdenes de compra pendientes de aprobación.' }}
                La orden definitiva se emitirá únicamente después de ser aprobada.
            </p>
        </div>
    @endif

    @php
        $preparedItems = $selectedQuote->purchasePreparationItems
            ->reject(fn ($item) => $item->status === \App\Models\PurchasePreparationItem::STATUS_EXCLUDED)
            ->values();
        $supplierNames = $preparedItems->pluck('finalSupplier.name')->filter()->unique()->values();
        $deliveryTimes = $preparedItems->pluck('finalSupplierQuote.delivery_time')->filter()->unique()->values();
    @endphp
    @php
        $pendingConfirmationCount = $preparedItems
            ->where('status', '!=', \App\Models\PurchasePreparationItem::STATUS_READY_FOR_PURCHASE_ORDER)
            ->count();
    @endphp
    <dl class="grid gap-x-6 gap-y-2 border-b border-gray-100 px-4 py-3 text-sm sm:grid-cols-3">
        <div><dt class="text-gray-500">Proveedores</dt><dd class="font-semibold text-gray-900">{{ $supplierNames->isEmpty() ? 'Pendiente' : $supplierNames->join(', ') }}</dd></div>
        <div><dt class="text-gray-500">Partidas</dt><dd class="font-semibold text-gray-900">{{ $preparedItems->count() }} ítems · {{ number_format((float) $preparedItems->sum('quantity_to_purchase'), 0) }} unidades</dd></div>
        <div><dt class="text-gray-500">Entrega</dt><dd class="font-semibold text-gray-900">{{ $deliveryTimes->isEmpty() ? 'Pendiente' : $deliveryTimes->join(', ') }}</dd></div>
    </dl>

    <div class="p-4">
        @if($pendingConfirmationCount > 0 && $activePurchaseOrders->isEmpty())
            <div class="mb-3 flex flex-col gap-2 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-bold text-slate-800">{{ $pendingConfirmationCount }} partida(s) pendiente(s) de confirmar</p>
                    <p class="mt-0.5 text-[11px] text-slate-500">Se validarán proveedor, costo y cantidad antes de confirmar el lote.</p>
                </div>
                <button type="button" wire:click="confirmAllFinalSuppliers" wire:confirm="Se confirmarán todos los proveedores y costos actuales. ¿Deseas continuar?" wire:loading.attr="disabled" wire:target="confirmAllFinalSuppliers" class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2.5 text-xs font-bold text-white transition hover:bg-emerald-700 disabled:cursor-wait disabled:opacity-60">
                    <span wire:loading.remove wire:target="confirmAllFinalSuppliers">Confirmar todos</span>
                    <span wire:loading wire:target="confirmAllFinalSuppliers">Confirmando...</span>
                </button>
            </div>
        @endif
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
            <div class="max-h-[560px] divide-y divide-slate-100 overflow-y-auto overscroll-contain">
                @forelse($preparedItems as $prepItem)
                    @php
                        $item = $prepItem->quotationItem;
                        $ready = $prepItem->status === \App\Models\PurchasePreparationItem::STATUS_READY_FOR_PURCHASE_ORDER;
                        $currency = $prepItem->finalSupplierQuote?->currency ?: $prepItem->originalSupplierQuote?->currency ?: 'USD';
                    @endphp
                    <button type="button" wire:click="openPurchasePreparationItem({{ $prepItem->id }})" class="group flex w-full items-center gap-3 px-3 py-2.5 text-left transition hover:bg-slate-50 focus:bg-slate-50 focus:outline-none" data-testid="purchase-preparation-item-{{ $prepItem->id }}">
                        <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-md {{ $ready ? 'bg-emerald-600 text-white' : 'border border-slate-300 bg-white text-transparent' }}">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" /></svg>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-[11px] font-black uppercase text-slate-900">{{ $item?->part_name ?: $item?->description ?: 'Partida sin nombre' }}</span>
                            <span class="mt-0.5 block truncate text-[10px] font-bold text-slate-400">{{ $item?->part_number ?: 'Sin referencia' }} · {{ $item?->brand ?: '-' }}{{ $item?->model ? ' / '.$item->model : '' }}</span>
                        </span>
                        <span class="hidden min-w-0 text-right sm:block">
                            <span class="block max-w-44 truncate text-[10px] font-black text-slate-700">{{ $prepItem->finalSupplier?->name ?: 'Proveedor pendiente' }}</span>
                            <span class="block text-[10px] font-bold text-emerald-700">{{ $currency }} {{ number_format((float) $prepItem->final_unit_cost, 2) }}</span>
                        </span>
                        <span class="shrink-0 text-right text-[10px] font-black uppercase text-slate-600">Cant. {{ number_format((float) $prepItem->quantity_to_purchase, 0) }}</span>
                        <span class="shrink-0 rounded-md px-2 py-1 text-[9px] font-black uppercase {{ $ready ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $ready ? 'Listo' : 'Revisar' }}</span>
                    </button>
                @empty
                    <div class="p-6 text-sm font-bold text-slate-500">Aún no hay partidas aprobadas disponibles para preparar la compra.</div>
                @endforelse
            </div>
        </div>
    </div>

    @if($activePurchasePreparationItemId)
        @php
            $modalPrepItem = $preparedItems->firstWhere('id', $activePurchasePreparationItemId);
            $modalItem = $modalPrepItem?->quotationItem;
            $modalOptions = $modalItem?->supplierBidResponseItems?->filter(fn ($option) => $option->is_selectable) ?? collect();
            $currentQuoteId = (int) ($modalPrepItem?->final_supplier_quote_id ?: $modalPrepItem?->original_supplier_quote_id ?: 0);
            $selectedQuoteId = (int) ($selectedFinalSupplierQuote[$activePurchasePreparationItemId] ?? $currentQuoteId);
        @endphp
        @if($modalPrepItem)
            <div class="fixed inset-0 z-50 flex items-end bg-slate-950/40 p-0 sm:items-center sm:justify-center sm:p-6" role="dialog" aria-modal="true" aria-labelledby="purchase-preparation-modal-title">
                <button type="button" wire:click="closePurchasePreparationItem" class="absolute inset-0 z-0 cursor-default" aria-label="Cerrar"></button>
                <div class="relative z-10 max-h-[92vh] w-full overflow-y-auto rounded-t-2xl bg-white shadow-2xl sm:max-w-3xl sm:rounded-2xl">
                    <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-4">
                        <div class="min-w-0">
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Preparación de OC</p>
                            <h4 id="purchase-preparation-modal-title" class="mt-1 truncate text-base font-black text-slate-950">{{ $modalItem?->part_name ?: $modalItem?->description ?: 'Partida' }}</h4>
                            <p class="mt-1 text-xs font-bold text-slate-500">{{ $modalItem?->part_number ?: 'Sin referencia' }} · Cantidad {{ number_format((float) $modalPrepItem->quantity_to_purchase, 0) }}</p>
                        </div>
                        <button type="button" wire:click="closePurchasePreparationItem" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-black text-slate-600 hover:bg-slate-50">Cerrar</button>
                    </div>

                    <div class="space-y-4 p-5">
                        <div>
                            <div class="flex items-center justify-between gap-3"><p class="text-xs font-black text-slate-900">Cotizaciones disponibles</p><span class="text-[10px] font-bold text-slate-400">Selecciona solo una</span></div>
                            <div class="mt-2 max-h-64 space-y-2 overflow-y-auto pr-1">
                                @forelse($modalOptions as $option)
                                    @php $isCurrent = (int) $option->id === $currentQuoteId; @endphp
                                    <label class="flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition {{ (int) $option->id === $selectedQuoteId ? 'border-slate-900 bg-slate-50' : 'border-slate-200 hover:border-slate-300' }}">
                                        <input type="radio" value="{{ $option->id }}" wire:model.live="selectedFinalSupplierQuote.{{ $modalPrepItem->id }}" class="h-4 w-4 border-slate-300 text-slate-900 focus:ring-slate-700">
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-xs font-black text-slate-900">{{ $option->response?->supplier?->name ?: 'Proveedor sin nombre' }} @if($isCurrent)<span class="ml-1 rounded bg-emerald-100 px-1.5 py-0.5 text-[9px] uppercase text-emerald-700">Actual</span>@endif</span>
                                            <span class="mt-1 block text-[10px] font-bold text-slate-500">{{ $option->delivery_time ?: 'Entrega no indicada' }} · {{ $option->warranty ?: 'Sin garantía' }}</span>
                                        </span>
                                        <span class="shrink-0 text-right text-xs font-black text-slate-900">{{ $option->currency ?: 'USD' }} {{ number_format((float) $option->unit_cost, 2) }}</span>
                                    </label>
                                @empty
                                    <div class="rounded-lg border border-dashed border-slate-200 p-4 text-xs font-bold text-slate-500">No hay otras cotizaciones válidas para esta partida.</div>
                                @endforelse
                            </div>
                        </div>

                        @if($selectedQuoteId && $selectedQuoteId !== $currentQuoteId)
                            <label class="block">
                                <span class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-400">Motivo del cambio *</span>
                                <input type="text" wire:model="changeSupplierReason" class="w-full rounded-lg border-slate-200 px-3 py-2 text-xs font-bold text-slate-700" placeholder="Ej. Mejor costo, plazo de entrega o disponibilidad">
                            </label>
                        @endif

                        <div class="rounded-lg border border-indigo-100 bg-indigo-50/50 p-3">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-[10px] font-black uppercase tracking-widest text-indigo-800">¿No está la cotización?</p>
                                <button type="button" wire:click="$set('activeQuickQuoteItemId', {{ $modalPrepItem->id }})" class="text-[10px] font-black text-indigo-700 hover:text-indigo-900">Añadir proveedor</button>
                            </div>
                            @if($activeQuickQuoteItemId === $modalPrepItem->id)
                                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                    <label class="block"><span class="mb-1 block text-[10px] font-black uppercase text-slate-500">Proveedor *</span><input type="text" wire:model="quickQuoteSupplierName" class="w-full rounded-lg border-slate-200 text-xs" placeholder="Nombre del proveedor"></label>
                                    <label class="block"><span class="mb-1 block text-[10px] font-black uppercase text-slate-500">Producto (opcional)</span><input type="text" wire:model="quickQuoteProductName" class="w-full rounded-lg border-slate-200 text-xs" placeholder="Si ofrece equivalente"></label>
                                    <label class="block"><span class="mb-1 block text-[10px] font-black uppercase text-slate-500">Moneda *</span><select wire:model="quickQuoteCurrency" class="w-full rounded-lg border-slate-200 text-xs">@foreach(\App\Models\Currency::query()->where('is_active', true)->orderBy('code')->get() as $currency)<option value="{{ $currency->code }}">{{ $currency->code }} · {{ $currency->name }}</option>@endforeach</select></label>
                                    <label class="block"><span class="mb-1 block text-[10px] font-black uppercase text-slate-500">Precio unitario *</span><input type="number" min="0" step="0.01" wire:model="quickQuoteUnitCost" class="w-full rounded-lg border-slate-200 text-xs" placeholder="0.00"></label>
                                    <label class="block sm:col-span-2"><span class="mb-1 block text-[10px] font-black uppercase text-slate-500">Garantía *</span><input type="text" list="quick-quote-warranty-options" wire:model="quickQuoteWarranty" class="w-full rounded-lg border-slate-200 text-xs" placeholder="Selecciona o escribe"><datalist id="quick-quote-warranty-options"><option value="Sin garantía"></option><option value="30 días"></option><option value="3 meses"></option><option value="6 meses"></option><option value="1 año"></option><option value="Garantía del fabricante"></option></datalist></label>
                                    <button type="button" wire:click="addQuickSupplierQuote({{ $modalPrepItem->id }})" class="sm:col-span-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-xs font-black text-white hover:bg-indigo-700">Guardar, seleccionar y confirmar</button>
                                </div>
                            @endif
                        </div>

                        <div class="flex flex-col-reverse gap-2 border-t border-slate-100 pt-4 sm:flex-row sm:justify-end">
                            <button type="button" wire:click="closePurchasePreparationItem" class="rounded-lg border border-slate-200 px-4 py-2.5 text-xs font-black text-slate-700 hover:bg-slate-50">Cancelar</button>
                            @if($selectedQuoteId && $selectedQuoteId !== $currentQuoteId)
                                <button type="button" wire:click="changeFinalSupplier({{ $modalPrepItem->id }})" class="rounded-lg bg-slate-950 px-4 py-2.5 text-xs font-black text-white hover:bg-slate-800">Cambiar y confirmar</button>
                            @else
                                <button type="button" wire:click="confirmFinalSupplier({{ $modalPrepItem->id }})" class="rounded-lg bg-emerald-600 px-4 py-2.5 text-xs font-black text-white hover:bg-emerald-700">Confirmar proveedor</button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endif
</section>
