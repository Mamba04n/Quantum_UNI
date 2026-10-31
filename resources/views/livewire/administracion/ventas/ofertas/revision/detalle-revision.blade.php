        <main class="mx-auto w-full max-w-6xl">
            @php
                $quote = $selectedReview->quotationRequest;
                $items = $selectedReview->offerVersion?->items ?? collect();
                if ($items->isEmpty()) {
                    $items = $quote->items->where('is_approved', true);
                    if ($items->isEmpty()) { $items = $quote->items; }
                }
                $subtotal = (float) ($selectedReview->offerVersion?->subtotal ?? $items->sum('line_total'));
                $taxPercent = (float) ($quote->tax_percent ?? 15);
                $iva = round($subtotal * ($taxPercent / 100), 2);
                $total = round($subtotal + $iva, 2);
                $costInNio = function ($item) {
                    $quoteItem = $item->quotationItem ?? $item;
                    if ((float) ($quoteItem->supplier_unit_cost_nio ?? 0) > 0) {
                        return (float) $quoteItem->supplier_unit_cost_nio;
                    }

                    $selection = $quoteItem->supplierBidSelection;
                    $originalCost = (float) ($selection?->selected_unit_cost ?? $quoteItem->supplier_unit_cost_original ?? $quoteItem->unit_cost ?? 0);
                    $currency = $selection?->currency ?? $quoteItem->supplier_currency ?? 'NIO';

                    return (float) app(\App\Services\ExchangeRateService::class)
                        ->convert($originalCost, $currency, 'NIO')['amount'];
                };
                $costTotal = $items->sum(function ($item) use ($costInNio) {
                    $quoteItem = $item->quotationItem ?? $item;
                    return $costInNio($item)
                        * (float) ($item->quantity ?? $quoteItem->supplierBidSelection?->selected_quantity ?? $quoteItem->quantity ?? 0);
                });
                $margin = $subtotal - $costTotal;
                $marginPercent = $subtotal > 0 ? ($margin / $subtotal) * 100 : 0;
            @endphp

            <x-order-context :quotation="$quote" class="mb-6" />
            <div class="mb-6 flex items-center justify-between rounded-xl bg-white p-4 shadow-sm border border-slate-200">
                <button type="button" wire:click="selectReview(null)" class="flex items-center gap-2 text-sm font-black text-slate-500 transition hover:text-slate-900">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    Volver a pendientes
                </button>
                <div class="flex items-center gap-4">
                    <div class="text-right">
                        <p class="text-[10px] font-black uppercase text-slate-400">Total Oferta</p>
                        <p class="font-black text-slate-900">C$ {{ number_format($total, 2) }}</p>
                    </div>
                </div>
            </div>

            <div class="erp-open-panel shadow-md border-0 rounded-2xl overflow-hidden bg-white">
                <header class="bg-slate-900 px-8 py-8 text-white relative overflow-hidden">
                    <div class="absolute inset-0 opacity-10 bg-[url('https://www.transparenttextures.com/patterns/cubes.png')] mix-blend-overlay"></div>
                    <div class="relative z-10 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <span class="inline-flex rounded-full bg-amber-500/20 px-3 py-1 text-[10px] font-black uppercase tracking-widest text-amber-300">Revision Requerida</span>
                            <h3 class="mt-4 text-3xl font-black">{{ $quote->quote_to_name }}</h3>
                            <p class="mt-2 text-sm font-medium text-slate-300">Ref: {{ $quote->reference }} &middot; Preparado por {{ $quote->user?->name ?? $selectedReview->requester?->name ?? 'Sistema' }}</p>
                        </div>
                        <div class="flex flex-col gap-2 min-w-[200px]">
                            <a href="{{ route('sales.offers.preview', $quote) }}" target="_blank" class="flex w-full items-center justify-center gap-2 rounded-xl bg-white/10 px-4 py-3 text-xs font-black text-white backdrop-blur-md transition hover:bg-white/20">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                Vista previa PDF
                            </a>
                        </div>
                    </div>
                </header>

                <div class="p-8 space-y-8">
                    <section>
                        <h4 class="text-xs font-black uppercase tracking-widest text-slate-400 mb-4">Resumen Financiero</h4>
                        <div class="grid grid-cols-2 gap-4 md:grid-cols-4 lg:grid-cols-6">
                            <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                                <span class="block text-[10px] font-black uppercase text-slate-400">Subtotal</span>
                                <strong class="mt-1 block text-sm text-slate-900">C$ {{ number_format($subtotal, 2) }}</strong>
                            </div>
                            <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                                <span class="block text-[10px] font-black uppercase text-slate-400">IVA ({{ number_format($taxPercent, 0) }}%)</span>
                                <strong class="mt-1 block text-sm text-slate-900">C$ {{ number_format($iva, 2) }}</strong>
                            </div>
                            <div class="rounded-xl border border-slate-100 bg-slate-50 p-4 lg:col-span-2">
                                <span class="block text-[10px] font-black uppercase text-slate-400">Costo Proveedor</span>
                                <strong class="mt-1 block text-sm text-slate-900">C$ {{ number_format($costTotal, 2) }}</strong>
                            </div>
                            <div class="rounded-xl border {{ $marginPercent < 15 ? 'border-amber-200 bg-amber-50' : 'border-emerald-200 bg-emerald-50' }} p-4 lg:col-span-2">
                                <div class="flex items-center justify-between">
                                    <span class="block text-[10px] font-black uppercase {{ $marginPercent < 15 ? 'text-amber-700' : 'text-emerald-700' }}">Margen de Ganancia</span>
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-black {{ $marginPercent < 15 ? 'bg-amber-200 text-amber-900' : 'bg-emerald-200 text-emerald-900' }}">{{ number_format($marginPercent, 2) }}%</span>
                                </div>
                                <strong class="mt-1 block text-sm {{ $marginPercent < 15 ? 'text-amber-900' : 'text-emerald-900' }}">C$ {{ number_format($margin, 2) }}</strong>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-xl border border-slate-200 bg-white overflow-hidden shadow-sm">
                        <div class="border-b border-slate-100 bg-slate-50/50 px-6 py-4 flex items-center justify-between">
                            <h4 class="text-xs font-black uppercase tracking-widest text-slate-500">Detalle de Partidas</h4>
                            <span class="text-[10px] font-bold text-slate-400">{{ $items->count() }} items</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="border-b border-slate-100 text-[9px] font-black uppercase tracking-widest text-slate-400 bg-white">
                                    <tr>
                                        <th class="px-6 py-4">Producto</th>
                                        <th class="px-6 py-4">Proveedor</th>
                                        <th class="px-6 py-4 text-right">Costo Unit.</th>
                                        <th class="px-6 py-4 text-right">Precio Unit. Cliente</th>
                                        <th class="px-6 py-4 text-right">Margen</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50 bg-white">
                                    @foreach($items as $item)
                                        @php
                                            $quoteItem = $item->quotationItem ?? $item;
                                            $selection = $quoteItem->supplierBidSelection;
                                            $costOriginal = (float) ($selection?->selected_unit_cost ?? $quoteItem->supplier_unit_cost_original ?? $quoteItem->unit_cost ?? 0);
                                            $cost = $costInNio($item);
                                            $price = (float) ($item->unit_price ?? 0);
                                            $itemMargin = $price > 0 ? (($price - $cost) / $price) * 100 : 0;
                                            $partNumber = $item->part_number ?: ($quoteItem->part_number ?? null);
                                            $partName = $item->description ?: ($quoteItem->part_name ?? $quoteItem->description ?? null);
                                            $brand = $item->brand ?: ($quoteItem->brand ?? null);
                                            $quantity = (float) ($item->quantity ?? $quoteItem->quantity ?? 0);
                                        @endphp
                                        <tr class="hover:bg-slate-50/50 transition-colors">
                                            <td class="px-6 py-4">
                                                <p class="font-black text-slate-900">{{ $partNumber ?: 'N/D' }}</p>
                                                <p class="mt-0.5 text-slate-500">{{ $partName }}</p>
                                                <div class="mt-1 flex items-center gap-2">
                                                    <span class="inline-flex items-center rounded-md bg-slate-100 px-1.5 py-0.5 text-[9px] font-bold text-slate-600">{{ $brand ?: 'Sin marca' }}</span>
                                                    <span class="text-[10px] font-bold text-slate-400">Cant: {{ number_format($quantity, 0) }}</span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-2 py-1 text-[10px] font-bold text-slate-700">
                                                    <svg class="h-3 w-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                                    {{ $selection?->supplier?->name ?: $quoteItem->selectedSupplier?->name ?: 'Sin proveedor' }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 text-right">
                                                <span class="font-black text-slate-500">{{ $selection?->currency ?: 'USD' }}</span>
                                                <span class="font-black text-slate-900 ml-1">{{ number_format($costOriginal, 2) }}</span>
                                            </td>
                                            <td class="px-6 py-4 text-right">
                                                <span class="font-black text-indigo-400">C$</span>
                                                <span class="font-black text-indigo-900 ml-1">{{ number_format($price, 2) }}</span>
                                            </td>
                                            <td class="px-6 py-4 text-right">
                                                <span class="inline-flex rounded-md px-2 py-1 text-[10px] font-black {{ $itemMargin < 15 ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700' }}">
                                                    {{ number_format($itemMargin, 2) }}%
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>

                    @php
                        $supplierResponses = $quote->supplierBidResponses->groupBy('supplier_id');
                    @endphp
                    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div class="flex flex-col gap-2 border-b border-slate-100 bg-slate-50/50 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h4 class="text-xs font-black uppercase tracking-widest text-slate-500">Cotizaciones y documentos de proveedores</h4>
                                <p class="mt-1 text-[11px] font-medium text-slate-500">Respuestas recibidas para esta solicitud. Los archivos se abren únicamente para visualización.</p>
                            </div>
                            <span class="rounded-full bg-white px-3 py-1 text-[10px] font-black text-slate-600 ring-1 ring-slate-200">{{ $supplierResponses->count() }} proveedor(es)</span>
                        </div>

                        <div class="divide-y divide-slate-100">
                            @forelse($supplierResponses as $responses)
                                @php($supplier = $responses->first()->supplier)
                                <div class="px-6 py-5">
                                    <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                        <div class="flex items-center gap-2">
                                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-900 text-[10px] font-black text-white">{{ str($supplier?->name ?: 'PR')->substr(0, 2)->upper() }}</span>
                                            <div>
                                                <p class="text-sm font-black text-slate-900">{{ $supplier?->name ?: 'Proveedor sin identificar' }}</p>
                                                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">{{ $responses->count() }} respuesta(s) registrada(s)</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="space-y-2">
                                        @foreach($responses as $response)
                                            <div class="grid gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
                                                <div class="min-w-0">
                                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                                        <span class="text-xs font-black text-slate-900">{{ $response->response_code ?: 'Respuesta proveedor' }}</span>
                                                        <span class="text-[10px] font-bold text-slate-500">{{ $response->created_at?->format('d/m/Y H:i') }}</span>
                                                        <span class="rounded bg-slate-100 px-2 py-0.5 text-[9px] font-black text-slate-600">{{ $response->currency }}</span>
                                                        <span class="text-[10px] font-bold text-slate-500">{{ $response->items->count() }} partida(s) · {{ $response->currency }} {{ number_format($response->quoted_total, 2) }}</span>
                                                    </div>
                                                    @if($response->supplierDocumentAttachments->isNotEmpty())
                                                        <div class="mt-2 flex flex-wrap gap-1.5">
                                                            @foreach($response->supplierDocumentAttachments as $document)
                                                                <a href="{{ route('tracker.attachments.show', $document) }}" target="_blank" rel="noopener" class="inline-flex max-w-full items-center gap-1 rounded-md border border-indigo-100 bg-indigo-50 px-2 py-1 text-[10px] font-black text-indigo-700 hover:bg-indigo-100" title="Visualizar {{ $document->file_name }}">
                                                                    <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                                                    <span class="truncate">{{ $document->file_name }}</span>
                                                                </a>
                                                            @endforeach
                                                        </div>
                                                    @else
                                                        <p class="mt-2 text-[10px] font-bold text-slate-400">Sin documento adjunto.</p>
                                                    @endif
                                                </div>
                                                <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Solo visualización</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @empty
                                <div class="px-6 py-10 text-center text-sm font-bold text-slate-400">No hay cotizaciones de proveedores registradas para esta oferta.</div>
                            @endforelse
                        </div>
                    </section>

                    <section class="rounded-xl border border-slate-200 bg-slate-50 p-6">
                        <h4 class="text-xs font-black uppercase tracking-widest text-slate-500 mb-4">Resolucion</h4>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4-.8L3 20l1.3-3.4A7.4 7.4 0 013 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                            </div>
                            <textarea wire:model="reviewReason" rows="3" class="w-full rounded-xl border-0 bg-white py-3 pl-10 pr-4 text-sm text-slate-900 shadow-sm ring-1 ring-inset ring-slate-200 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:leading-6" placeholder="Escribe un motivo obligatorio si vas a rechazar o solicitar cambios. Opcional si apruebas..."></textarea>
                        </div>
                        <div class="mt-5 flex flex-col sm:flex-row items-center justify-end gap-3">
                            <button type="button" wire:click="requestChanges" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-amber-50 px-6 py-3 text-sm font-black text-amber-700 shadow-sm ring-1 ring-inset ring-amber-200 hover:bg-amber-100 transition">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12.066 11.2a1 1 0 000 1.6l5.334 4A1 1 0 0019 16V8a1 1 0 00-1.6-.8l-5.333 4zM4.066 11.2a1 1 0 000 1.6l5.334 4A1 1 0 0011 16V8a1 1 0 00-1.6-.8l-5.334 4z"></path></svg>
                                Solicitar Cambios
                            </button>
                            <button type="button" wire:click="reject" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-rose-50 px-6 py-3 text-sm font-black text-rose-700 shadow-sm ring-1 ring-inset ring-rose-200 hover:bg-rose-100 transition">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                                Rechazar
                            </button>
                            <button type="button" wire:click="approve" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-8 py-3 text-sm font-black text-white shadow-sm hover:bg-emerald-500 transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                Aprobar y Autorizar Envio
                            </button>
                        </div>
                    </section>
                </div>
            </div>
        </main>
