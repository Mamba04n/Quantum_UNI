@php
    $canEditOfferData = $canEditOfferData && $this->canManageClientOfferEdits();
    $offerItems = $selectedQuote->items;
    $offerSubtotal = round((float) $offerItems->sum('line_total'), 2);
    $offerTaxPercent = (float) ($selectedQuote->tax_percent ?? $clientOfferTaxPercent ?? 15);
    if ($offerTaxPercent <= 0) {
        $offerTaxPercent = 15;
    }
    $offerTaxAmount = round($offerSubtotal * ($offerTaxPercent / 100), 2);
    $offerTotal = round($offerSubtotal + $offerTaxAmount, 2);
    $canRequestClientOfferReview = $canEditOfferData;
    $isCorrectionMode = $this instanceof \App\Livewire\Ventas\Cotizaciones\CorreccionesOrden\GestorCorreccionesOrden;
@endphp

<section class="min-w-0 rounded-2xl border border-gray-100 bg-white shadow-sm">
    <div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h3 class="text-base font-extrabold tracking-tight text-gray-900">Oferta Cliente</h3>
            <p class="mt-0.5 text-xs text-gray-500">Partidas cotizadas y precios basados en la preliquidación de proveedores.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if($canEditOfferData)
                <a href="{{ route('sales.quotations.template.client-offer', $selectedQuote) }}" class="inline-flex h-8 items-center rounded-lg border border-slate-300 bg-white px-3 text-[11px] font-semibold text-slate-700 shadow-xs hover:bg-slate-50">
                    Descargar Excel
                </a>
                <label class="inline-flex h-8 cursor-pointer items-center rounded-lg bg-slate-900 px-3 text-[11px] font-semibold text-white shadow-xs hover:bg-black">
                    <span wire:loading.remove wire:target="clientOfferBulkFile">Importar Excel</span>
                    <span wire:loading wire:target="clientOfferBulkFile">Cargando...</span>
                    <input type="file" wire:model="clientOfferBulkFile" accept=".xlsx,.xls" class="hidden">
                </label>
            @endif
        </div>
    </div>
    @error('clientOfferBulkFile')
        <div class="border-b border-rose-100 bg-rose-50 px-4 py-2 text-xs font-bold text-rose-700">{{ $message }}</div>
    @enderror
    <div class="space-y-4 p-4">
        @if($selectedQuote->status === \App\Models\QuotationRequest::STATUS_CUSTOMER_CHANGES_REQUESTED)
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-xs font-black uppercase tracking-widest text-amber-700">Cambios en orden</p>
                        <p class="mt-1 text-sm font-bold text-amber-900">{{ $selectedQuote->offer_review_notes ?: 'El cliente solicitó cambios en la oferta.' }}</p>
                    </div>
                    @if(!($this instanceof \App\Livewire\Ventas\Cotizaciones\CorreccionesOrden\GestorCorreccionesOrden))
                        <button type="button" wire:click="reopenClientOfferForRevision" class="rounded-lg bg-amber-600 px-4 py-2.5 text-xs font-black text-white hover:bg-amber-700">Abrir corrección de oferta</button>
                    @endif
                </div>
            </div>
        @endif
        @php
            $multipleResponses = $selectedQuote->supplierBidResponses
                ->filter(fn ($r) => filled($r->supplier_id) && $r->supplier)
                ->loadMissing('supplier');
        @endphp
        @if($canEditOfferData && $multipleResponses->count() > 1)
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-indigo-100 bg-indigo-50/50 px-3.5 py-2">
                <div class="flex items-center gap-2">
                    <svg class="h-4 w-4 text-indigo-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 3h5v5M4 20L21 3M21 16v5h-5M15 15l6 6M4 4l5 5"/></svg>
                    <span class="text-xs font-bold text-indigo-950">Preliquidación predeterminada para toda la orden:</span>
                </div>
                <div class="flex items-center gap-2">
                    <select wire:change="applySupplierResponseToAllItems($event.target.value)" class="rounded-lg border-indigo-200 bg-white py-1 pl-2 pr-6 text-xs font-semibold text-slate-800 shadow-xs focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600">
                        <option value="">Aplicar a todas las partidas...</option>
                        @foreach($multipleResponses as $resp)
                            @php
                                $via = ($resp->preliquidation['settings']['transport'] ?? 'air') === 'sea' ? 'Marítimo' : 'Aéreo';
                            @endphp
                            <option value="{{ $resp->id }}">
                                {{ $resp->supplier?->name }} ({{ $via }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        @endif

        <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-xs">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[940px] table-fixed text-left text-sm">
                    <colgroup>
                        @if($canEditOfferData && $isCorrectionMode)
                            <col class="w-[19%]">
                            <col class="w-[21%]">
                            <col class="w-[14%]">
                            <col class="w-[10%]">
                            <col class="w-[6%]">
                            <col class="w-[11%]">
                            <col class="w-[14%]">
                            <col class="w-[5%]">
                        @else
                            <col class="w-[21%]">
                            <col class="w-[23%]">
                            <col class="w-[15%]">
                            <col class="w-[11%]">
                            <col class="w-[6%]">
                            <col class="w-[11%]">
                            <col class="w-[13%]">
                        @endif
                    </colgroup>
                    <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-3 py-2.5 font-semibold">Producto</th>
                            <th class="px-3 py-2.5 font-semibold">Proveedor / Preliquidación</th>
                            <th class="px-3 py-2.5 font-semibold">Garantía</th>
                            <th class="px-3 py-2.5 text-right font-semibold">Costo prov.</th>
                            <th class="px-3 py-2.5 text-right font-semibold">Cant.</th>
                            <th class="px-3 py-2.5 text-right font-semibold">Precio cliente</th>
                            <th class="px-3 py-2.5 text-right font-semibold">Subtotal</th>
                            @if($canEditOfferData && $isCorrectionMode)
                                <th class="px-2 py-2.5 text-right font-semibold"><span class="sr-only">Acción</span></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($offerItems as $item)
                            @php
                                $selection = $item->supplierBidSelection;
                                $quotedOptions = $item->supplierBidResponseItems
                                    ->filter(fn ($ri) => $ri->is_selectable || (float)($ri->unit_cost ?? 0) > 0)
                                    ->loadMissing('response.supplier');
                                $isRemovedFromOffer = $isCorrectionMode
                                    && ! $item->is_approved
                                    && (float) $item->line_total <= 0;
                            @endphp
                            <tr wire:key="offer-item-{{ $item->id }}" class="{{ $isRemovedFromOffer ? 'bg-rose-50/70 opacity-55' : 'hover:bg-slate-50/50' }} transition-colors">
                                <td class="truncate px-3 py-2.5 font-semibold text-gray-900 {{ $isRemovedFromOffer ? 'line-through decoration-rose-500 decoration-2' : '' }}" title="{{ $item->part_name ?: $item->description }}">
                                    <div class="truncate text-xs font-semibold text-slate-900">{{ $item->part_name ?: $item->description }}</div>
                                    @if($item->part_number)
                                        <div class="text-[10px] text-slate-400 font-normal">P/N: {{ $item->part_number }}</div>
                                    @endif
                                    @if($isRemovedFromOffer)
                                        <span class="inline-flex rounded-full bg-rose-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wide text-rose-700 no-underline">Eliminado de la oferta</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-gray-700">
                                    @if($canEditOfferData && ! $isRemovedFromOffer && $quotedOptions->count() > 0)
                                        <div class="flex items-center gap-1.5">
                                             <select wire:change="selectSupplierResponseForItem({{ $item->id }}, $event.target.value, {{ $item->version }})"
                                                    class="w-full rounded-lg border-slate-200 bg-white py-1.5 pl-2 pr-6 text-xs font-semibold text-slate-800 shadow-xs focus:border-slate-800 focus:ring-1 focus:ring-slate-800"
                                                    title="Elegir cotización del proveedor y preliquidación para esta partida">
                                                @foreach($quotedOptions as $opt)
                                                    @php
                                                        $optSupName = $opt->response?->supplier?->name ?: 'Proveedor';
                                                        $optNio = (float) ($opt->preliquidated_unit_price_nio ?? 0);
                                                        $optUsd = (float) ($opt->preliquidated_unit_price ?? 0);
                                                        if ($optNio <= 0 && $optUsd <= 0 && ! empty($opt->response?->preliquidation)) {
                                                            $calc = app(\App\Services\PreliquidationCalculator::class)->forResponse($opt->response);
                                                            $rCalc = $calc['rows'][$opt->id] ?? null;
                                                            if ($rCalc) {
                                                                $optUsd = (float) ($rCalc['assigned_unit'] ?: ($rCalc['price'] ?? 0));
                                                                $optNio = (float) ($rCalc['assigned_unit_nio'] ?: ($rCalc['price_nio'] ?? 0));
                                                            }
                                                        }
                                                        $optDisplay = $optNio > 0 ? 'C$ ' . number_format($optNio, 2) : ($optUsd > 0 ? '$ ' . number_format($optUsd, 2) : 'EXW $' . number_format((float)$opt->unit_cost, 2));
                                                        $isSelectedOpt = ($selection?->supplier_bid_response_item_id === $opt->id)
                                                            || ($item->selected_supplier_bid_item_id === $opt->id)
                                                            || ($quotedOptions->count() === 1 && $item->selected_supplier_id === $opt->response?->supplier_id);
                                                        $optVia = ($opt->response?->preliquidation['settings']['transport'] ?? 'air') === 'sea' ? 'Marítimo' : 'Aéreo';
                                                    @endphp
                                                    <option value="{{ $opt->id }}" @selected($isSelectedOpt)>
                                                        {{ Str::limit($optSupName, 14) }} · {{ $optVia }} · {{ $optDisplay }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <button type="button" wire:click="openCompareSuppliersModal({{ $item->id }})"
                                                    class="inline-flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition"
                                                    title="Comparar cotizaciones y desgloses de preliquidación">
                                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 3h5v5M4 20L21 3M21 16v5h-5M15 15l6 6M4 4l5 5"/></svg>
                                            </button>
                                        </div>
                                        @if($quotedOptions->count() > 1)
                                            <span class="mt-0.5 block text-[10px] font-semibold text-indigo-700">
                                                {{ $quotedOptions->count() }} cotizaciones disponibles
                                            </span>
                                        @else
                                            <span class="mt-0.5 block text-[10px] font-medium text-slate-400">
                                                1 preliquidación disponible
                                            </span>
                                        @endif
                                    @else
                                        <div class="flex items-center justify-between gap-1">
                                            @php
                                                $respItem = $selection?->responseItem ?: $quotedOptions->first();
                                                $supName = $selection?->supplier?->name ?: ($respItem?->response?->supplier?->name ?: 'Sin respuesta');
                                                $transport = ($respItem?->response?->preliquidation['settings']['transport'] ?? 'air') === 'sea' ? '🚢 Marítimo' : '✈️ Aéreo';
                                            @endphp
                                            <div class="min-w-0">
                                                <span class="truncate block text-xs font-semibold text-slate-800" title="{{ $supName }}">
                                                    {{ $supName }}
                                                </span>
                                                <span class="text-[10px] text-slate-500 font-medium">{{ $transport }}</span>
                                            </div>
                                            @if($quotedOptions->count() > 0)
                                                <button type="button" wire:click="openCompareSuppliersModal({{ $item->id }})"
                                                        class="inline-flex h-6 w-6 flex-shrink-0 items-center justify-center rounded text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition"
                                                        title="Ver desglose">
                                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7Z"/></svg>
                                                </button>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-gray-700">
                                    @if($canEditOfferData && ! $isRemovedFromOffer)
                                        <div class="space-y-1">
                                            <select wire:change="updateWarrantyType({{ $item->id }}, $event.target.value, {{ (int) ($item->version ?? 1) }})"
                                                    class="w-full rounded-lg border-slate-200 bg-white py-1.5 px-2 text-xs font-semibold text-slate-800 shadow-xs focus:border-slate-800 focus:ring-1 focus:ring-slate-800"
                                                    title="Selecciona la clasificación de garantía">
                                                <option value="">Sin garantía</option>
                                                <option value="A1" @selected(strtoupper((string)$item->warranty_type) === 'A1')>A1 (6-12 meses)</option>
                                                <option value="A2" @selected(strtoupper((string)$item->warranty_type) === 'A2')>A2 (6 meses)</option>
                                                <option value="B1" @selected(strtoupper((string)$item->warranty_type) === 'B1')>B1 (6 meses)</option>
                                                <option value="B2" @selected(strtoupper((string)$item->warranty_type) === 'B2')>B2 (2-3 meses)</option>
                                                <option value="C1" @selected(strtoupper((string)$item->warranty_type) === 'C1')>C1 (2 meses)</option>
                                                <option value="C2" @selected(strtoupper((string)$item->warranty_type) === 'C2')>C2 (1 mes)</option>
                                            </select>
                                            @if(filled($item->warranty_type))
                                                <label class="inline-flex items-center gap-1.5 cursor-pointer text-[10px] font-bold text-indigo-700 hover:text-indigo-900 select-none">
                                                    <input type="checkbox"
                                                           wire:change="updateWarrantyExtension({{ $item->id }}, $event.target.checked, {{ (int) ($item->version ?? 1) }})"
                                                           @checked($item->has_warranty_extension)
                                                           class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 h-3.5 w-3.5">
                                                    <span>+50% Plus</span>
                                                </label>
                                            @endif
                                        </div>
                                    @else
                                        @if(!empty($item->warranty_type))
                                            @php
                                                $wCode = strtoupper((string) $item->warranty_type);
                                                $wStyles = [
                                                    'A1' => 'bg-sky-50 text-sky-700 border-sky-200',
                                                    'A2' => 'bg-sky-50/60 text-sky-600 border-sky-100',
                                                    'B1' => 'bg-teal-50 text-teal-700 border-teal-200',
                                                    'B2' => 'bg-teal-50/60 text-teal-600 border-teal-100',
                                                    'C1' => 'bg-amber-50 text-amber-700 border-amber-200',
                                                    'C2' => 'bg-amber-50/60 text-amber-600 border-amber-100',
                                                ];
                                                $badgeClass = $wStyles[$wCode] ?? 'bg-slate-50 text-slate-700 border-slate-200';
                                            @endphp
                                            <div class="flex flex-col gap-0.5">
                                                <span class="inline-flex items-center w-max rounded-md border px-2 py-0.5 text-[11px] font-bold {{ $badgeClass }}">
                                                    {{ $wCode }}
                                                </span>
                                                @if($item->has_warranty_extension)
                                                    <span class="inline-flex items-center w-max rounded-md bg-indigo-50 px-1.5 py-0.5 text-[9px] font-black text-indigo-700 border border-indigo-200">
                                                        +50% Plus
                                                    </span>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-xs text-slate-400 font-medium">Sin garantía</span>
                                        @endif
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-right font-medium tabular-nums text-xs text-gray-600">
                                    {{ $item->supplier_currency ?: ($selection?->currency ?: 'USD') }} {{ number_format((float) ($item->supplier_unit_cost_original ?? $selection?->selected_unit_cost ?? $item->unit_cost), 2) }}
                                </td>
                                <td class="px-3 py-2.5 text-right font-semibold tabular-nums text-xs text-gray-900">
                                    {{ number_format((float) $item->quantity, 0) }}
                                </td>
                                <td class="px-3 py-2.5 text-right tabular-nums text-xs font-bold text-slate-900">
                                    C$ {{ number_format((float) $item->unit_price, 2) }}
                                </td>
                                <td class="px-3 py-2.5 text-right font-bold tabular-nums text-xs text-gray-900">
                                    C$ {{ number_format((float) $item->line_total, 2) }}
                                </td>
                                @if($canEditOfferData && $isCorrectionMode)
                                    <td class="px-2 py-2.5 text-right">
                                        @if($isRemovedFromOffer)
                                            <button type="button" wire:click="restoreClientOfferItem({{ $item->id }}, {{ $item->version }})" class="rounded-lg border border-emerald-200 bg-white px-2 py-1 text-[10px] font-bold text-emerald-700 hover:bg-emerald-50">Restaurar</button>
                                        @else
                                            <button type="button" wire:click="removeClientOfferItem({{ $item->id }}, {{ $item->version }})" wire:confirm="¿Quitar esta partida de la oferta?" class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-rose-200 bg-white text-rose-600 hover:bg-rose-50" title="Quitar"><svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12"/></svg></button>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ ($canEditOfferData && $isCorrectionMode) ? 8 : 7 }}" class="px-4 py-8 text-center text-sm text-gray-500">No hay partidas seleccionadas para ofertar.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
            <div class="flex flex-wrap items-center gap-6">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Partidas cotizadas</p>
                    <p class="text-sm font-black text-slate-900">{{ $offerItems->count() }} {{ $offerItems->count() === 1 ? 'partida' : 'partidas' }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Moneda oferta</p>
                    <p class="text-sm font-black text-slate-900">{{ $selectedQuote->currency ?: 'NIO' }}</p>
                </div>
                @if($offerTaxPercent > 0)
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Subtotal</p>
                        <p class="text-sm font-black text-slate-900">C$ {{ number_format($offerSubtotal, 2) }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">IVA ({{ number_format($offerTaxPercent, 0) }}%)</p>
                        <p class="text-sm font-black text-slate-900">C$ {{ number_format($offerTaxAmount, 2) }}</p>
                    </div>
                @endif
            </div>
            <div class="flex items-center gap-3">
                <div class="text-right">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Total Oferta Cliente</p>
                    <p class="text-xl font-black text-slate-950">C$ {{ number_format($offerTotal, 2) }}</p>
                </div>
            </div>
        </div>

        <details class="quotation-disclosure group" @if($errors->has('offer*')) open @endif>
            <summary class="inline-flex cursor-pointer list-none items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 shadow-xs transition hover:bg-slate-50 focus:outline-none">
                <svg class="h-3.5 w-3.5 text-slate-500 transition-transform duration-200 group-open:rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                <span>Condiciones de la oferta y notas del PDF</span>
            </summary>
            <div class="mt-3 flex items-center justify-end">
                @if($canEditOfferData)
                    <button type="button" wire:click="saveClientOfferNotes" wire:loading.attr="disabled" wire:target="saveClientOfferNotes" class="inline-flex items-center gap-1.5 rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white shadow-xs transition hover:bg-slate-900 disabled:opacity-60">
                        <span wire:loading.remove wire:target="saveClientOfferNotes">Guardar condiciones</span>
                        <span wire:loading wire:target="saveClientOfferNotes">Guardando...</span>
                    </button>
                @endif
            </div>
            <div class="py-3">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-700">Validez</label>
                        @if($canEditOfferData)
                            <input type="text" wire:model="offerValidity" class="w-full rounded-lg border-transparent bg-gray-50 px-3 py-1.5 text-xs transition-all focus:bg-white focus:ring-2 focus:ring-slate-200" placeholder="Ej. 15 días">
                        @else
                            <div class="rounded-lg bg-gray-50 px-3 py-1.5 text-xs text-gray-700">{{ $selectedQuote->validity_days ?: '15 días' }}</div>
                        @endif
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-700">Condición de pago</label>
                        @if($canEditOfferData)
                            <input type="text" list="client-offer-payment-terms" wire:model="offerPaymentTerms" class="w-full rounded-lg border-transparent bg-gray-50 px-3 py-1.5 text-xs transition-all focus:bg-white focus:ring-2 focus:ring-slate-200" placeholder="Ej. Contado">
                            <datalist id="client-offer-payment-terms">
                                <option value="Contado"></option>
                                <option value="Crédito a 30 días"></option>
                                <option value="Crédito a 60 días"></option>
                                <option value="50% anticipo y 50% contra entrega"></option>
                            </datalist>
                        @else
                            <div class="rounded-lg bg-gray-50 px-3 py-1.5 text-xs text-gray-700">{{ $selectedQuote->payment_terms ?: 'Contado' }}</div>
                        @endif
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-700">Tiempo de entrega</label>
                        @if($canEditOfferData)
                            <input type="text" list="client-offer-delivery-times" wire:model="offerDeliveryTime" class="w-full rounded-lg border-transparent bg-gray-50 px-3 py-1.5 text-xs transition-all focus:bg-white focus:ring-2 focus:ring-slate-200" placeholder="Ej. 15 a 20 días hábiles">
                            <datalist id="client-offer-delivery-times">
                                <option value="Entrega inmediata"></option>
                                <option value="5 a 10 días hábiles"></option>
                                <option value="15 a 20 días hábiles"></option>
                                <option value="4 a 6 semanas"></option>
                            </datalist>
                        @else
                            <div class="rounded-lg bg-gray-50 px-3 py-1.5 text-xs text-gray-700">{{ $selectedQuote->delivery_time ?: 'Por confirmar' }}</div>
                        @endif
                    </div>
                    <div class="sm:col-span-3">
                        <label class="mb-1 block text-xs font-semibold text-gray-700">Información técnica y notas</label>
                        @if($canEditOfferData)
                            <textarea wire:model="offerNotes" class="w-full rounded-lg border-transparent bg-gray-50 px-3 py-1.5 text-xs transition-all focus:bg-white focus:ring-2 focus:ring-slate-200" rows="2" placeholder="Garantía, disponibilidad y observaciones para el cliente"></textarea>
                        @else
                            <div class="rounded-lg bg-gray-50 px-3 py-1.5 text-xs text-gray-700">{!! nl2br(e($selectedQuote->offer_notes ?: 'Garantía según especificaciones del fabricante.')) !!}</div>
                        @endif
                    </div>
                </div>
            </div>
        </details>

        <div class="border-t border-slate-200 pt-3">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="client-offer-company" class="mb-1 block text-sm font-semibold text-gray-800">
                        Empresa emisora / Formato de oferta
                    </label>
                    @if($canEditOfferData)
                        <select id="client-offer-company" wire:model.live="companyIssuer" wire:change="saveCompanyIssuer" class="w-full rounded-lg border-gray-300 text-sm focus:border-slate-500 focus:ring-slate-200">
                            <option value="quantum">🔴 Quantum Soluciones S.A. (Formato original)</option>
                            <option value="powersales">🔵 Power Sales (The Best Option)</option>
                            <option value="marcos_leiva">🟢 Marcos Cecilio Leiva Ordoñez</option>
                        </select>
                    @else
                        @php
                            $companyLabels = [
                                'quantum' => 'Quantum Soluciones S.A.',
                                'powersales' => 'Power Sales',
                                'marcos_leiva' => 'Marcos Cecilio Leiva Ordoñez',
                            ];
                        @endphp
                        <p class="text-sm font-semibold text-gray-800">{{ $companyLabels[$companyIssuer] ?? 'Quantum Soluciones S.A.' }}</p>
                    @endif
                    <p class="mt-1 text-xs text-gray-500">Aplica el membrete, logo, colores y cuentas bancarias en la oferta y PDF.</p>
                </div>
                <div>
                    <label for="client-offer-tax" class="mb-1 block text-sm font-semibold text-gray-800">Impuesto de la oferta al cliente</label>
                    @if($canEditOfferData)
                        <select id="client-offer-tax" wire:model="clientOfferTaxPercent" wire:change="saveClientOfferTaxPercent" wire:loading.attr="disabled" wire:target="saveClientOfferTaxPercent,generateClientOfferPdf" class="w-full rounded-lg border-gray-300 text-sm focus:border-slate-400 focus:ring-slate-200">
                            <option value="0">Sin impuesto (0%)</option>
                            <option value="15">Con IVA (15%)</option>
                            @if(! in_array((int) $offerTaxPercent, [0, 15], true))
                                <option value="{{ (int) $offerTaxPercent }}">Con IVA ({{ (int) $offerTaxPercent }}%)</option>
                            @endif
                        </select>
                        @error('clientOfferTaxPercent')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                    @else
                        <p class="text-sm text-gray-700">{{ $offerTaxPercent > 0 ? 'Con IVA ('.number_format($offerTaxPercent, 0).'%)' : 'Sin impuesto (0%)' }} · Oferta bloqueada para edición.</p>
                    @endif
                    <p class="mt-1 text-xs text-gray-500">Se aplica al total y al PDF del cliente. No modifica los impuestos de preliquidación.</p>
                </div>
            </div>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-3">
            <div class="flex items-center gap-2">
                @php
                    $isPdfGenerated = filled($selectedQuote->client_offer_pdf_path) && ($selectedQuote->status === \App\Models\QuotationRequest::STATUS_FINAL_PDF_GENERATED || $selectedQuote->offerVersions->isNotEmpty());
                @endphp
                @if($isPdfGenerated)
                    <span class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                        <svg class="h-3.5 w-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Oferta PDF generada
                    </span>
                    <a href="{{ route('sales.offers.download-final-pdf', $selectedQuote->id) }}" target="_blank" class="inline-flex h-8 items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-700 hover:bg-slate-50 transition shadow-2xs" title="Descargar documento PDF oficial">
                        <svg class="h-3.5 w-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Descargar PDF
                    </a>
                @else
                    <span class="inline-flex items-center gap-1.5 text-xs text-amber-700 font-medium">
                        <svg class="h-4 w-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        Genera el PDF para emitir la oferta oficial
                    </span>
                @endif
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('sales.offers.preview', $selectedQuote) }}" target="_blank" class="inline-flex h-9 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50">
                    <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    Vista previa PDF
                </a>
                <button
                    type="button"
                    wire:click="generateClientOfferPdf"
                    wire:loading.attr="disabled"
                    wire:target="generateClientOfferPdf"
                    class="inline-flex h-9 items-center gap-1.5 rounded-xl {{ $isPdfGenerated ? 'bg-slate-800' : 'bg-slate-900 ring-2 ring-emerald-500/40' }} px-4 text-xs font-semibold text-white shadow-xs transition hover:bg-black disabled:opacity-60"
                    title="{{ $isPdfGenerated ? 'Regenerar PDF de la Oferta Cliente' : 'Generar PDF oficial para habilitar el avance a decisión del cliente' }}"
                >
                    <svg class="h-4 w-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l3.414 3.414A1 1 0 0117 7.414V19a2 2 0 01-2 2z"/></svg>
                    <span wire:loading.remove wire:target="generateClientOfferPdf">{{ $isPdfGenerated ? 'Regenerar PDF de Oferta' : 'Generar PDF de Oferta (Obligatorio)' }}</span>
                    <span wire:loading wire:target="generateClientOfferPdf">Generando PDF...</span>
                </button>
            </div>
        </div>
    </div>
</section>

@if($showClientOfferBulkModal)
    <div
        class="fixed inset-0 z-50 flex items-center justify-center p-4 backdrop-blur-sm sm:p-6"
        style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 99999; background-color: rgba(15, 23, 42, 0.75); display: flex; align-items: center; justify-content: center; overflow-y: auto;"
        x-data
        x-on:keydown.escape.window="$wire.closeClientOfferBulkImport()"
    >
        <div
            class="relative w-full max-w-5xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
            style="max-height: 88vh; height: 88vh; display: flex; flex-direction: column; width: 100%; max-width: 64rem; margin: auto;"
        >
            <div class="flex shrink-0 items-center justify-between border-b border-slate-200 bg-slate-50 px-6 py-4">
                <div class="flex min-w-0 items-center gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-slate-950 text-white">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l3.414 3.414A1 1 0 0117 7.414V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-lg font-black text-slate-950">Vista previa de precios de Oferta Cliente (Excel)</h3>
                        <p class="mt-0.5 truncate text-sm font-bold text-slate-500">Cotización: {{ $selectedQuote->reference }} · {{ count($clientOfferBulkPreview) }} partida(s) detectadas</p>
                    </div>
                </div>
                <button type="button" wire:click="closeClientOfferBulkImport" class="rounded-lg p-2 text-slate-400 hover:bg-slate-200 hover:text-slate-700" aria-label="Cerrar vista previa">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="flex-1 space-y-4 overflow-y-auto p-6 text-xs" style="flex: 1 1 auto; overflow-y: auto; min-height: 0;">
                <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
                    <table class="w-full whitespace-nowrap text-left text-sm">
                        <thead class="sticky top-0 border-b border-slate-200 bg-slate-100 text-xs uppercase tracking-wider text-slate-600">
                            <tr>
                                <th class="px-5 py-3 font-black">#</th>
                                <th class="px-5 py-3 font-black">Producto</th>
                                <th class="px-5 py-3 font-black">Proveedor</th>
                                <th class="px-5 py-3 text-right font-black">Costo NIO</th>
                                <th class="px-5 py-3 text-right font-black">Cant.</th>
                                <th class="px-5 py-3 text-right font-black">Precio cliente</th>
                                <th class="px-5 py-3 font-black">Garantía</th>
                                <th class="px-5 py-3 font-black">50 Plus</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($clientOfferBulkPreview as $row)
                                <tr wire:key="client-offer-preview-{{ $row['item_id'] }}" class="hover:bg-slate-50/70">
                                    <td class="px-5 py-3 font-black text-slate-400">{{ $loop->iteration }}</td>
                                    <td class="px-5 py-3 font-bold text-slate-800">{{ $row['product'] ?: 'Sin descripción' }}</td>
                                    <td class="px-5 py-3 text-slate-600">{{ $row['supplier'] ?: 'Sin proveedor' }}</td>
                                    <td class="px-5 py-3 text-right font-bold text-slate-700">C$ {{ number_format((float) $row['supplier_cost_nio'], 2) }}</td>
                                    <td class="px-5 py-3 text-right font-black text-slate-900">{{ number_format((float) $row['quantity'], 0) }}</td>
                                    <td class="px-5 py-3 text-right font-black text-slate-950">C$ {{ number_format((float) $row['unit_price'], 2) }}</td>
                                    <td class="px-5 py-3 text-slate-600">{{ $row['warranty_type'] ?: 'Ninguna' }}</td>
                                    <td class="px-5 py-3">
                                        <span class="inline-flex rounded-lg px-2.5 py-1 text-xs font-black {{ $row['has_warranty_extension'] ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' : 'bg-slate-100 text-slate-600' }}">{{ $row['has_warranty_extension'] ? 'Sí' : 'No' }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex shrink-0 flex-col items-center justify-between gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4 sm:flex-row" style="flex-shrink: 0;">
                <div class="flex items-center gap-3 text-sm font-bold text-slate-500">
                    <span class="inline-flex h-9 min-w-9 items-center justify-center rounded-full bg-slate-200 px-2 font-black text-slate-700">{{ count($clientOfferBulkPreview) }}</span>
                    partida(s) listas para aplicar
                </div>
                <div class="flex w-full items-center justify-end gap-2 sm:w-auto">
                    <button type="button" wire:click="closeClientOfferBulkImport" class="rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-black text-slate-700 hover:bg-slate-100">Cancelar</button>
                    <button type="button" wire:click="applyClientOfferBulkImport" wire:loading.attr="disabled" wire:target="applyClientOfferBulkImport" class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-950 px-5 py-3 text-sm font-black text-white shadow-sm hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60">
                        <svg wire:loading.remove wire:target="applyClientOfferBulkImport" class="h-5 w-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        <span wire:loading.remove wire:target="applyClientOfferBulkImport">Aplicar precios a la oferta</span>
                        <span wire:loading wire:target="applyClientOfferBulkImport">Aplicando precios...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif

@if($comparingOfferItemId)
    @php
        $comparingItem = $offerItems->firstWhere('id', $comparingOfferItemId);
        $comparingOptions = $comparingItem ? $comparingItem->supplierBidResponseItems
            ->filter(fn ($ri) => $ri->is_selectable || (float)($ri->unit_cost ?? 0) > 0)
            ->loadMissing('response.supplier') : collect();
    @endphp
    @if($comparingItem)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
             x-data x-on:keydown.escape.window="$wire.closeCompareSuppliersModal()">
            <div class="relative w-full max-w-4xl overflow-hidden rounded-2xl bg-white shadow-2xl border border-gray-100 animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50/80 px-6 py-4">
                    <div>
                        <h3 class="text-base font-extrabold tracking-tight text-gray-900">
                            Comparativa de Proveedores · Partida
                        </h3>
                        <p class="mt-0.5 text-xs text-gray-500 font-medium">
                            {{ $comparingItem->part_number ? '[' . $comparingItem->part_number . '] ' : '' }}{{ $comparingItem->part_name ?: $comparingItem->description }} · Cantidad: {{ (float) $comparingItem->quantity }} {{ $comparingItem->unit ?: 'PZA' }}
                        </p>
                    </div>
                    <button type="button" wire:click="closeCompareSuppliersModal" class="rounded-xl p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700 transition" aria-label="Cerrar modal">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="p-6 overflow-x-auto max-h-[70vh]">
                    <table class="w-full text-left text-sm whitespace-nowrap">
                        <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-4 py-3 font-semibold">Proveedor</th>
                                <th class="px-3 py-3 font-semibold">Parte / Marca</th>
                                <th class="px-3 py-3 text-right font-semibold">EXW (USD)</th>
                                <th class="px-3 py-3 text-right font-semibold">Peso (lb)</th>
                                <th class="px-3 py-3 text-right font-semibold">DDP (USD)</th>
                                <th class="px-3 py-3 text-right font-semibold">Margen %</th>
                                <th class="px-3 py-3 text-right font-bold text-slate-900 bg-slate-100/60">Venta USD</th>
                                <th class="px-3 py-3 text-right font-bold text-slate-900 bg-slate-100/60">Venta C$</th>
                                <th class="px-3 py-3 font-semibold">Entrega</th>
                                <th class="px-3 py-3 font-semibold">Garantía</th>
                                <th class="px-4 py-3 text-right font-semibold">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($comparingOptions as $opt)
                                @php
                                    $isCur = ($comparingItem->selected_supplier_bid_item_id === $opt->id)
                                        || ($comparingItem->supplierBidSelection?->supplier_bid_response_item_id === $opt->id)
                                        || ($comparingItem->selected_supplier_id === $opt->response?->supplier_id);
                                    $optUsd = (float)($opt->preliquidated_unit_price ?? 0);
                                    $optNio = (float)($opt->preliquidated_unit_price_nio ?? 0);
                                    if ($optUsd <= 0 && $optNio <= 0 && !empty($opt->response?->preliquidation)) {
                                        $calc = app(\App\Services\PreliquidationCalculator::class)->forResponse($opt->response);
                                        $rowCalc = $calc['rows'][$opt->id] ?? null;
                                        if ($rowCalc) {
                                            $optUsd = (float) ($rowCalc['assigned_unit'] ?: ($rowCalc['price'] ?? 0));
                                            $optNio = (float) ($rowCalc['assigned_unit_nio'] ?: ($rowCalc['price_nio'] ?? 0));
                                        }
                                    }
                                    $weightLb = (float) ($opt->response?->preliquidation['weights'][$opt->id] ?? 0);
                                    $marginPct = (float) ($opt->margin_percentage ?? $opt->response?->preliquidation['settings']['margin'] ?? 30);
                                @endphp
                                <tr class="{{ $isCur ? 'bg-emerald-50/70 font-medium' : 'hover:bg-gray-50/50' }} transition-colors">
                                    <td class="px-4 py-3 font-semibold text-slate-900">
                                        <div class="flex items-center gap-1.5">
                                            <span>{{ $opt->response?->supplier?->name ?: 'Proveedor' }}</span>
                                            <span class="inline-flex items-center gap-0.5 rounded px-1.5 py-0.5 text-[9px] font-bold {{ ($opt->response?->preliquidation['settings']['transport'] ?? 'air') === 'sea' ? 'bg-cyan-100 text-cyan-800' : 'bg-sky-100 text-sky-800' }}">
                                                {{ ($opt->response?->preliquidation['settings']['transport'] ?? 'air') === 'sea' ? '🚢 Marítimo' : '✈️ Aéreo' }}
                                            </span>
                                        </div>
                                        @if($isCur)
                                            <span class="mt-1 inline-flex items-center rounded-md bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800">
                                                Seleccionado
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 text-xs text-slate-600">
                                        <div>{{ $opt->quoted_part_number ?: '-' }}</div>
                                        <div class="text-[11px] text-slate-400">{{ $opt->quoted_brand ?: '' }}</div>
                                    </td>
                                    <td class="px-3 py-3 text-right tabular-nums text-slate-700">
                                        ${{ number_format((float)$opt->unit_cost, 2) }}
                                    </td>
                                    <td class="px-3 py-3 text-right tabular-nums text-slate-700">
                                        {{ number_format($weightLb, 2) }} lb
                                    </td>
                                    <td class="px-3 py-3 text-right tabular-nums text-slate-700">
                                        ${{ number_format((float)($opt->ddp_unit_cost ?? 0), 2) }}
                                    </td>
                                    <td class="px-3 py-3 text-right tabular-nums text-slate-700">
                                        {{ number_format($marginPct, 1) }}%
                                    </td>
                                    <td class="px-3 py-3 text-right tabular-nums font-bold text-slate-900 bg-slate-50/50">
                                        ${{ number_format($optUsd, 2) }}
                                    </td>
                                    <td class="px-3 py-3 text-right tabular-nums font-bold text-slate-900 bg-slate-50/50">
                                        C$ {{ number_format($optNio, 2) }}
                                    </td>
                                    <td class="px-3 py-3 text-xs text-slate-600">
                                        {{ $opt->delivery_time ?: ($opt->response?->general_delivery_time ?: 'Por confirmar') }}
                                    </td>
                                    <td class="px-3 py-3 text-xs text-slate-600">
                                        {{ $opt->warranty ?: ($opt->response?->general_warranty ?: 'Sin garantía') }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        @if($isCur)
                                            <span class="text-xs font-bold text-emerald-700">Precio activo</span>
                                        @else
                                            <button type="button"
                                                    wire:click="selectSupplierResponseForItem({{ $comparingItem->id }}, {{ $opt->id }}, {{ $comparingItem->version }})"
                                                    class="inline-flex items-center justify-center rounded-xl bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-slate-900 transition">
                                                Aplicar a la oferta
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="px-4 py-6 text-center text-sm text-gray-500">
                                        No hay cotizaciones de proveedores registradas para esta partida.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end gap-2 border-t border-gray-100 bg-gray-50 px-6 py-3.5">
                    <button type="button" wire:click="closeCompareSuppliersModal" class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    @endif
@endif
