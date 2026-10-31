        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div class="flex flex-col gap-3 border-b border-slate-100 bg-slate-50 px-6 py-5 xl:flex-row xl:items-center xl:justify-between">
                <div>
                    <h3 class="text-lg font-black text-slate-900">Seleccion de Partidas</h3>
                    <p class="mt-1 text-xs text-slate-500">Elige exactamente que cotizacion pasa a Oferta Cliente. Puedes combinar proveedores.</p>
                </div>
                @php
                    $excludedCount = $selectedQuote->items->where('supplier_selection_status', 'excluded')->count();
                @endphp
                <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-[10px] font-black text-slate-600">{{ $selectedQuote->supplierBidSelections->count() }} proveedor / {{ $excludedCount }} excluida(s) / {{ $selectedQuote->items->count() }} total</span>
            </div>
            <div class="overflow-x-auto border-t border-slate-100">
                <table class="w-full text-left text-[11px]">
                    <thead class="bg-slate-50 text-[9px] font-black uppercase tracking-widest text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Proveedor / Detalles</th>
                            <th class="px-4 py-3 text-right">Costo unit.</th>
                            <th class="px-4 py-3">Fecha / entrega</th>
                            <th class="px-4 py-3">Garantia / pago</th>
                                <th class="px-4 py-3 text-center">Estado</th>
                                <th class="px-4 py-3">Respuesta proveedor</th>
                                <th class="px-4 py-3 text-right">Accion</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach($selectedQuote->items as $item)
                            <tr class="bg-slate-50 border-t border-slate-200">
                                <td colspan="7" class="px-4 py-3">
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="text-sm font-black text-slate-900">{{ $item->part_number ?: 'Sin código' }}</span>
                                            <span class="text-sm font-medium text-slate-600">{{ $item->part_name ?: str($item->description)->limit(80) }}</span>
                                            @if($item->brand)
                                                <span class="rounded bg-white border border-slate-200 px-2 py-0.5 text-[10px] font-bold text-slate-500">{{ $item->brand }}</span>
                                            @endif
                                        </div>
                                        <div class="flex flex-wrap items-center gap-3">
                                            <span class="rounded bg-slate-200 px-2.5 py-1 text-[10px] font-black uppercase tracking-widest text-slate-700">
                                                Solicitado: {{ number_format((float) $item->quantity, 0) }}
                                            </span>
                                            @if($item->supplierBidSelection)
                                                <span class="rounded bg-emerald-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-widest text-emerald-800">
                                                    Selección actual: {{ $item->supplierBidSelection->supplier?->name }} ({{ $item->supplierBidSelection->currency }} {{ number_format((float) $item->supplierBidSelection->selected_unit_cost, 2) }})
                                                </span>
                                            @elseif($item->supplier_selection_status === 'excluded')
                                                <span class="rounded bg-rose-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-widest text-rose-700">Excluida: {{ $item->supplier_selection_exclusion_reason }}</span>
                                            @else
                                                <span class="rounded bg-rose-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-widest text-rose-600">Pendiente seleccionar</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            @if(! $item->supplierBidSelection && $item->supplier_selection_status !== 'excluded')
                                <tr class="bg-amber-50/50">
                                    <td colspan="7" class="px-4 py-2.5">
                                        <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-end">
                                            <p class="mr-auto text-[10px] font-bold text-amber-800">Si no se cotizará, excluye esta partida con un motivo. No puede quedar sin decisión.</p>
                                            <input wire:model="supplierSelectionExclusionReasons.{{ $item->id }}" type="text" class="h-8 min-w-0 rounded-md border border-amber-200 bg-white px-2.5 text-[10px] font-semibold lg:w-72" placeholder="Motivo de exclusión">
                                            <button type="button" wire:click="excludeSupplierSelectionItem({{ $item->id }})" wire:confirm="¿Excluir esta partida de la oferta al cliente? Quedará registrada con el motivo indicado." class="h-8 rounded-md border border-rose-200 bg-white px-3 text-[10px] font-black text-rose-700 hover:bg-rose-50">Excluir partida</button>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                            @forelse($item->supplierBidResponseItems->sortBy(fn ($bidItem) => (float) ($bidItem->unit_cost ?? 999999999)) as $bidItem)
                                @php
                                    $isSelectedLine = (int) optional($item->supplierBidSelection)->supplier_bid_response_item_id === (int) $bidItem->id;
                                    $selectable = $bidItem->is_selectable;
                                @endphp
                                <tr class="{{ $isSelectedLine ? 'bg-emerald-50/70' : (! $selectable ? 'bg-slate-50/50 text-slate-400' : 'hover:bg-slate-50') }}">
                                    <td class="px-4 py-3">
                                        <p class="font-black text-slate-900">{{ $bidItem->response?->supplier?->name ?? 'Proveedor' }}</p>
                                        <p class="text-[9px] font-bold text-slate-400">{{ $bidItem->response?->response_code }}</p>
                                        @if($bidItem->is_alternative || (float)$bidItem->quoted_quantity !== (float)$item->quantity)
                                            <div class="mt-1 flex flex-col gap-0.5">
                                                @if($bidItem->is_alternative)
                                                    <span class="text-[10px] text-amber-600 font-bold">Alternativa: {{ $bidItem->quoted_part_number }} - {{ str($bidItem->quoted_description)->limit(40) }}</span>
                                                @endif
                                                @if((float)$bidItem->quoted_quantity !== (float)$item->quantity)
                                                    <span class="text-[10px] text-rose-600 font-bold">Cotizó: {{ number_format((float) $bidItem->quoted_quantity, 0) }} pzas</span>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right font-black">{{ $bidItem->currency }} {{ number_format((float) $bidItem->unit_cost, 2) }}</td>
                                    <td class="px-4 py-3 font-medium text-slate-600">{{ optional($bidItem->estimated_arrival_date)->format('d/m/Y') ?: ($bidItem->delivery_time ?: 'N/D') }}</td>
                                    <td class="px-4 py-3 font-medium text-slate-600">{{ $bidItem->warranty ?: ($bidItem->response?->general_warranty ?: 'N/D') }}<span class="block text-[10px] font-bold text-slate-400">{{ $bidItem->response?->payment_terms ?: 'Pago N/D' }}</span></td>
                                    <td class="px-4 py-3 text-center"><span class="rounded-full px-2 py-1 text-[9px] font-black uppercase tracking-widest {{ $selectable ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $bidItem->item_status === 'not_quoted' ? 'No cotizada' : ($bidItem->is_alternative ? 'Alternativa' : 'Cotizada') }}</span></td>
                                    <td class="px-4 py-3">
                                        @php
                                            $itemSpecificDocs = $bidItem->attachments ?? collect();
                                            $supplierGeneralDocs = $bidItem->response?->supplierDocumentAttachments ?? collect();
                                        @endphp
                                        <div class="flex flex-col gap-1">
                                            @if($itemSpecificDocs->isNotEmpty())
                                                <div class="flex flex-wrap items-center gap-1">
                                                    @foreach($itemSpecificDocs as $doc)
                                                        <a
                                                            href="{{ route('tracker.attachments.show', $doc) }}"
                                                            target="_blank"
                                                            rel="noopener"
                                                            class="inline-flex items-center gap-1 rounded-md border border-indigo-200 bg-indigo-50 px-2 py-0.5 text-[10px] font-black text-indigo-700 transition hover:bg-indigo-100"
                                                            title="Prueba de esta pieza: {{ $doc->file_name }}"
                                                        >
                                                            <svg class="h-3 w-3 text-indigo-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                                                            Prueba{{ $itemSpecificDocs->count() > 1 ? ' '.$loop->iteration : '' }}
                                                        </a>
                                                    @endforeach
                                                </div>
                                            @endif
                                            @if($supplierGeneralDocs->isNotEmpty())
                                                <div class="flex flex-wrap items-center gap-1">
                                                    @foreach($supplierGeneralDocs as $document)
                                                        <a
                                                            href="{{ route('tracker.attachments.show', $document) }}"
                                                            target="_blank"
                                                            rel="noopener"
                                                            class="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-2 py-0.5 text-[10px] font-bold text-slate-700 transition hover:bg-slate-50"
                                                            title="Proforma general: {{ $document->file_name }}"
                                                        >
                                                            <svg class="h-3 w-3 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                                            Proforma{{ $supplierGeneralDocs->count() > 1 ? ' '.$loop->iteration : '' }}
                                                        </a>
                                                    @endforeach
                                                </div>
                                            @endif
                                            @if($itemSpecificDocs->isEmpty() && $supplierGeneralDocs->isEmpty())
                                                <span class="text-[10px] font-bold text-slate-400">Sin adjunto</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-right">@if($isSelectedLine)<button type="button" wire:click="removeSelectedItem({{ $item->id }})" class="rounded-md border border-rose-200 bg-white px-3 py-2 text-[10px] font-black uppercase tracking-widest text-rose-700 transition hover:bg-rose-50 hover:border-rose-300">Quitar</button>@else<button type="button" wire:click="selectResponseItem({{ $bidItem->id }})" wire:loading.attr="disabled" wire:target="selectResponseItem({{ $bidItem->id }})" @disabled(! $selectable) class="rounded-md px-3 py-2 text-[10px] font-black uppercase tracking-widest transition disabled:cursor-wait disabled:opacity-60 {{ $selectable ? 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 hover:border-slate-300' : 'cursor-not-allowed bg-slate-100 text-slate-400' }}">{{ $selectable ? 'Usar esta partida' : 'N/A' }}</button>@endif</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-4 py-4 text-center text-xs font-bold text-slate-400">Aun no hay cotizaciones para esta partida.</td></tr>
                            @endforelse
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-4 border-t border-slate-100 bg-slate-50 px-6 py-4">
                <span class="text-[10px] font-bold text-slate-500">{{ $selectedQuote->supplierBidSelections->count() }}/{{ $selectedQuote->items->count() }} seleccionadas.</span>
            </div>
        </section>
