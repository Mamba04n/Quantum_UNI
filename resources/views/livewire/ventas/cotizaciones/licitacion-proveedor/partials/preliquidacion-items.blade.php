@php
    $optionalColumns = [
        'total_weight' => 'Peso total',
        'subtotal_exw' => 'Subtotal EXW',
        'cif_nio' => 'CIF C$',
        'ddp_nio' => 'DDP C$',
        'price_nio' => 'Venta unit. C$',
        'total_nio' => 'Venta total C$',
        'net_profit' => 'Utilidad neta USD',
        'gross_profit' => 'Utilidad bruta USD',
        'gross_margin' => 'Utilidad bruta %',
    ];
@endphp
<div class="min-w-0" x-data="{ columns: [], expanded: null, init() { try { const saved = JSON.parse(localStorage.getItem('preliq-columns-{{ auth()->id() }}') || '[]'); if (Array.isArray(saved)) this.columns = saved.filter(column => @js(array_keys($optionalColumns)).includes(column)); } catch {} this.$watch('columns', value => { try { localStorage.setItem('preliq-columns-{{ auth()->id() }}', JSON.stringify(value)) } catch {} }) } }">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <span class="text-gray-500">{{ $totals['count'] }} partidas · {{ number_format($totals['weight'], 2) }} lb · {{ $totals['complete'] }}/{{ $totals['count'] }} completas</span>
            @if($totals['count'] > 0)
                <span class="text-gray-300">|</span>
                <span class="font-medium text-slate-700">Venta total: <strong class="font-extrabold text-emerald-700">$ {{ number_format($totals['sales'], 2) }}</strong> <span class="text-xs text-gray-500">(C$ {{ number_format($totals['sales_nio'], 2) }})</span></span>
            @endif
        </div>
        <details class="relative"><summary class="cursor-pointer rounded-lg border border-gray-200 px-3 py-2 text-sm font-semibold">Columnas</summary>
            <div class="absolute left-0 z-30 grid w-64 gap-2 rounded-xl border border-gray-200 bg-white p-3 shadow-sm sm:left-auto sm:right-0">
                @foreach($optionalColumns as $column => $label)<label class="flex items-center gap-2 text-sm"><input type="checkbox" value="{{ $column }}" x-model="columns" class="rounded border-gray-300 text-slate-800">{{ $label }}</label>@endforeach
            </div>
        </details>
    </div>
    <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
        <div class="preliq-items-scroll" tabindex="0" role="region" aria-label="Partidas de preliquidación">
            <table class="preliq-items w-full whitespace-nowrap text-left text-sm">
                <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase tracking-wider text-gray-500"><tr>
                    <th scope="col">Part #</th>
                    <th scope="col">Descripción</th>
                    <th scope="col" class="text-right">Cant.</th>
                    <th scope="col" class="text-right">Peso lb</th>
                    <th scope="col" class="text-right" title="Unit Price / Costo unitario EXW cotizado">Precio unit. (EXW)</th>
                    <th scope="col" class="text-right">CIF unit. USD</th>
                    <th scope="col" class="text-right">DDP unit. USD</th>
                    <th scope="col" class="text-right" title="Escribe el precio por unidad en C$ o USD. Vacío usa el margen.">Precio final unitario</th>
                    <th scope="col" class="text-right">Venta total USD</th>
                    @foreach($optionalColumns as $column => $label)<th scope="col" x-show="columns.includes('{{ $column }}')" x-cloak class="text-right">{{ $label }}</th>@endforeach
                    <th scope="col">Acciones</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($supplierBidItems + $supplierBidExtraItems as $key => $row)
                        @php
                            $group = isset($supplierBidExtraItems[$key]) ? 'supplierBidExtraItems' : 'supplierBidItems';
                            $result = $totals['rows'][$key] ?? [];
                            $issues = $result['issues'] ?? ['No cotizada'];
                            $savedItem = $activeSupplierResponse?->items?->firstWhere('id', $row['response_item_id'] ?? null);

                            $requestItem = null;
                            if ($group === 'supplierBidItems') {
                                $requestItem = $selectedQuote?->items?->firstWhere('id', (int) $key);
                            } elseif (!empty($row['quotation_request_item_id'])) {
                                $requestItem = $selectedQuote?->items?->firstWhere('id', (int) $row['quotation_request_item_id']);
                            }

                            $reqPartNumber = $requestItem?->part_number;
                            $reqDescription = $requestItem ? ($requestItem->part_name ?: $requestItem->description) : null;
                            $reqQuantity = $requestItem ? (float) $requestItem->quantity : null;
                            $reqUnit = $requestItem?->unit ?: 'PZA';
                            $reqBrandModel = $requestItem ? implode(' / ', array_filter([$requestItem->brand, $requestItem->model])) : null;
                            $quotedQty = filled($row['quoted_quantity'] ?? null) ? (float) $row['quoted_quantity'] : null;
                            $qtyDiffers = $requestItem && $quotedQty !== null && abs($quotedQty - (float)$reqQuantity) > 0.0001;
                        @endphp
                        <tr wire:key="preliquidation-{{ $selectedSupplierId }}-{{ $key }}" id="supplier-bid-table-row-{{ $key }}" data-supplier-row data-item-id="{{ $key }}" x-bind:class="detailsDirty || !@js(filled($row['response_item_id'] ?? null)) ? 'bg-amber-50' : 'bg-emerald-50'" class="align-top transition-colors">
                            <td>
                                <input aria-label="Número de parte" wire:model="{{ $group }}.{{ $key }}.quoted_part_number" class="{{ $control }} font-semibold text-slate-900" placeholder="{{ $reqPartNumber ?: 'No. parte' }}">
                                @if(!$requestItem)
                                    <span class="mt-1 inline-flex items-center rounded bg-amber-100 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider text-amber-800">Partida adicional</span>
                                @endif
                                @error($group.'.'.$key.'.quoted_part_number')<span class="block text-xs text-rose-700">{{ $message }}</span>@enderror
                            </td>
                            <td>
                                <input aria-label="Descripción" wire:model="{{ $group }}.{{ $key }}.quoted_description" class="{{ $control }}" placeholder="{{ $reqDescription ?: 'Descripción de la partida' }}">
                                <div class="mt-1 flex items-center justify-between gap-1 text-[11px]">
                                    <span class="preliq-row-state {{ $issues ? 'text-amber-800 font-medium' : 'text-gray-400' }}">{{ $issues ? implode(' · ', $issues) : 'Completa' }} · <span x-text="detailsDirty || !@js(filled($row['response_item_id'] ?? null)) ? 'Sin guardar' : 'Guardada'"></span></span>
                                    @if($reqBrandModel)<span class="text-[10px] text-slate-400 font-normal truncate" title="Marca / Modelo: {{ $reqBrandModel }}">{{ $reqBrandModel }}</span>@endif
                                </div>
                                @error($group.'.'.$key.'.quoted_description')<span class="block text-xs text-rose-700">{{ $message }}</span>@enderror
                            </td>
                            <td>
                                <input aria-label="Cantidad cotizada" type="number" min="0.01" step="0.01" wire:model.live.debounce.300ms="{{ $group }}.{{ $key }}.quoted_quantity" class="{{ $control }} text-right tabular-nums font-semibold {{ $qtyDiffers ? 'border-amber-300 bg-amber-50 text-amber-950 ring-1 ring-amber-300' : '' }}" title="Cantidad cotizada por el proveedor">
                                @if($qtyDiffers)
                                    <span class="mt-1 block text-right text-[10px] font-bold text-amber-800 leading-tight" title="La cantidad cotizada difiere de la solicitud ({{ $reqQuantity }} {{ $reqUnit }})">
                                        Sol: {{ $reqQuantity }} {{ $reqUnit }}
                                    </span>
                                @endif
                                @error($group.'.'.$key.'.quoted_quantity')<span class="block whitespace-normal text-xs text-rose-700">{{ $message }}</span>@enderror
                            </td>
                            <td>
                                <input aria-label="Peso unitario lb" type="number" min="0" step="0.0001" wire:model.live.debounce.300ms="{{ $group }}.{{ $key }}.weight_lb" class="{{ $control }} text-right tabular-nums" placeholder="0.00">
                                @error($group.'.'.$key.'.weight_lb')<span class="block whitespace-normal text-xs text-rose-700">{{ $message }}</span>@enderror
                            </td>
                            <td>
                                <input aria-label="Precio unitario EXW USD" type="number" min="0" step="0.01" wire:model.live.debounce.300ms="{{ $group }}.{{ $key }}.unit_cost" class="{{ $control }} text-right tabular-nums font-semibold" placeholder="0.00">
                                @error($group.'.'.$key.'.unit_cost')<span class="block whitespace-normal text-xs text-rose-700">{{ $message }}</span>@enderror
                            </td>
                            @foreach(['cif', 'ddp'] as $field)<td class="text-right tabular-nums" title="{{ $preliquidationChange }}">{{ isset($result[$field]) ? number_format($result[$field], 2) : '—' }}</td>@endforeach
                            <td class="preliq-client-price" x-data="{ priceCurrency: @js((float) ($row['assigned_usd'] ?? 0) > 0 ? 'USD' : 'NIO') }">
                                @include('livewire.ventas.cotizaciones.licitacion-proveedor.partials.preliquidacion-precio-cliente')
                            </td>
                            <td class="text-right tabular-nums">{{ isset($result['total']) ? number_format($result['total'], 2) : '—' }}</td>
                            @foreach($optionalColumns as $column => $label)
                                <td x-show="columns.includes('{{ $column }}')" x-cloak class="text-right tabular-nums">
                                    @if(in_array($column, ['assigned_usd', 'assigned_nio']))
                                        <input aria-label="{{ $label }}" type="number" min="0" step="0.01" wire:model.live.debounce.300ms="{{ $group }}.{{ $key }}.{{ $column }}" class="{{ $control }} text-right tabular-nums font-semibold" placeholder="0.00">
                                    @else{{ isset($result[$column]) ? number_format($result[$column], 2) : '—' }}@endif
                                </td>
                            @endforeach
                            <td><div class="flex items-center gap-1">
                                <button type="button" x-on:click="expanded = expanded === @js((string)$key) ? null : @js((string)$key)" x-bind:aria-expanded="expanded === @js((string)$key)" class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition" title="Cálculo, notas y adjuntos" aria-label="Cálculo, notas y adjuntos"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m21 11-9 9a6 6 0 0 1-8.5-8.5l10-10a4 4 0 0 1 5.7 5.7l-10 10a2 2 0 0 1-2.8-2.8l9-9"/></svg></button>
                            </div></td>
                        </tr>
                        <tr wire:key="preliq-detail-{{ $selectedSupplierId }}-{{ $key }}" x-show="expanded === @js((string)$key)" x-cloak>
                            <td colspan="99" class="preliq-detail">
                                <div class="grid gap-4 p-3 md:grid-cols-2">
                                    <div><h4 class="mb-2 font-semibold">Cálculo unitario · USD</h4>
                                        <dl class="grid grid-cols-2 gap-x-4 gap-y-1">
                                            @foreach($result['breakdown'] ?? [] as $label => $amount)<dt>{{ $label }}</dt><dd class="text-right tabular-nums">{{ number_format($amount, 4) }}</dd>@endforeach
                                            <dt>CIF / CPT</dt><dd class="text-right">{{ number_format($result['cif'] ?? 0, 4) }}</dd>
                                            <dt>DDP</dt><dd class="text-right">{{ number_format($result['ddp'] ?? 0, 4) }}</dd>
                                            <dt>Divisor de utilidad</dt><dd class="text-right">{{ number_format($totals['divisor'], 4) }}</dd>
                                            <dt class="font-semibold">Precio de venta</dt><dd class="text-right font-semibold">{{ number_format($result['price'] ?? 0, 4) }}</dd>
                                        </dl>
                                        <p class="mt-2 text-xs text-gray-500">Desaduanaje y transporte: total del proveedor × peso unitario ÷ peso total. Cambiar el peso o cantidad modifica este reparto.</p>
                                    </div>
                                    <div class="grid content-start gap-2">
                                        @if($requestItem)
                                            <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-3 text-xs text-slate-700">
                                                <div class="flex items-center justify-between border-b border-slate-200/60 pb-1.5 mb-2">
                                                    <span class="font-bold text-slate-900">Partida original en solicitud</span>
                                                    <span class="rounded bg-slate-200/70 px-1.5 py-0.5 text-[10px] font-bold uppercase text-slate-700">Registro Solicitud</span>
                                                </div>
                                                <div class="grid grid-cols-2 gap-x-3 gap-y-1.5 text-[11px]">
                                                    <div><span class="text-slate-500">No. de parte:</span> <strong class="text-slate-800">{{ $reqPartNumber ?: 'Sin código' }}</strong></div>
                                                    <div><span class="text-slate-500">Cant. solicitada:</span> <strong class="text-slate-800">{{ $reqQuantity }} {{ $reqUnit }}</strong></div>
                                                    <div class="col-span-2"><span class="text-slate-500">Descripción:</span> <span class="font-medium text-slate-800">{{ $reqDescription }}</span></div>
                                                    @if($requestItem->brand || $requestItem->model)
                                                        <div class="col-span-2"><span class="text-slate-500">Marca / Modelo:</span> {{ implode(' / ', array_filter([$requestItem->brand, $requestItem->model])) }}</div>
                                                    @endif
                                                    @if($requestItem->serial_number)
                                                        <div class="col-span-2"><span class="text-slate-500">No. serie:</span> {{ $requestItem->serial_number }}</div>
                                                    @endif
                                                    @if($requestItem->notes)
                                                        <div class="col-span-2"><span class="text-slate-500">Notas de solicitud:</span> {{ $requestItem->notes }}</div>
                                                    @endif
                                                </div>
                                            </div>
                                        @endif
                                        @if($group === 'supplierBidExtraItems')
                                            <label>Partida de solicitud para la oferta<select wire:model="{{ $group }}.{{ $key }}.quotation_request_item_id" class="{{ $control }}"><option value="">Independiente del pedido original</option>@foreach($selectedQuote->items as $requestItemOption)<option value="{{ $requestItemOption->id }}">{{ $requestItemOption->part_number }} · {{ $requestItemOption->part_name ?: $requestItemOption->description }}</option>@endforeach</select></label>
                                        @endif
                                        <label>Garantía<input wire:model="{{ $group }}.{{ $key }}.warranty" class="{{ $control }}"></label>
                                        <label>Entrega<input wire:model="{{ $group }}.{{ $key }}.delivery_time" class="{{ $control }}"></label>
                                        <label>Observaciones<input wire:model="{{ $group }}.{{ $key }}.notes" class="{{ $control }}"></label>
                                        <label class="flex flex-wrap items-center gap-2">Adjuntar evidencia<input type="file" multiple wire:model="supplierItemProformas.{{ $key }}" class="max-w-full text-sm"></label>
                                        @foreach($savedItem?->attachments ?? [] as $attachment)<a class="text-slate-700 underline" href="{{ route('tracker.attachments.show', $attachment) }}" target="_blank" rel="noopener">{{ $attachment->file_name }}</a>@endforeach
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="99" class="p-6 text-center text-sm text-gray-500">No hay partidas asignadas a este proveedor. Usa <strong class="font-semibold text-slate-700">Modificar orden</strong> para agregar o modificar las partidas de la cotización.</td></tr>
                    @endforelse
                </tbody>
                @if(count($supplierBidItems + $supplierBidExtraItems) > 0)
                    @php
                        $allItems = $supplierBidItems + $supplierBidExtraItems;
                        $totalQuantity = collect($allItems)->where('item_status', '!=', 'not_quoted')->sum(fn ($r) => (float) ($r['quoted_quantity'] ?? 0));
                        $totalCifNio = collect($totals['rows'])->sum('cif_nio');
                        $totalCifUsd = $totals['cif'] ?? ($totals['exw'] + ($totals['costs']['handling'] ?? 0) + ($totals['costs']['freight'] ?? 0));
                    @endphp
                    <tfoot class="border-t-2 border-gray-200 bg-gray-50/95 font-bold text-xs text-slate-800">
                        <tr>
                            <td colspan="2" class="px-3 py-2.5 text-left font-bold uppercase tracking-wider text-slate-700">
                                Totales ({{ $totals['count'] }} {{ $totals['count'] === 1 ? 'partida' : 'partidas' }})
                            </td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-slate-900 font-bold" title="Cantidad total de piezas">
                                {{ number_format($totalQuantity, 0) }}
                            </td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-slate-900 font-bold" title="Peso total acumulado">
                                {{ number_format($totals['weight'], 2) }} lb
                            </td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-slate-900 font-bold" title="Costo EXW total">
                                $ {{ number_format($totals['exw'], 2) }}
                            </td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-slate-700 font-semibold" title="CIF total">
                                $ {{ number_format($totalCifUsd, 2) }}
                            </td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-slate-900 font-bold" title="DDP total">
                                $ {{ number_format($totals['ddp'], 2) }}
                            </td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-gray-400 font-normal">
                                —
                            </td>
                            <td class="px-3 py-2.5 text-right tabular-nums font-black text-emerald-800 bg-emerald-50/70 border-x border-emerald-100" title="Venta total acumulada en USD">
                                $ {{ number_format($totals['sales'], 2) }}
                            </td>
                            {{-- Columnas opcionales --}}
                            <td x-show="columns.includes('total_weight')" x-cloak class="px-3 py-2.5 text-right tabular-nums text-slate-900 font-bold">{{ number_format($totals['weight'], 2) }} lb</td>
                            <td x-show="columns.includes('subtotal_exw')" x-cloak class="px-3 py-2.5 text-right tabular-nums text-slate-900 font-bold">$ {{ number_format($totals['exw'], 2) }}</td>
                            <td x-show="columns.includes('cif_nio')" x-cloak class="px-3 py-2.5 text-right tabular-nums text-slate-900 font-bold">C$ {{ number_format($totalCifNio, 2) }}</td>
                            <td x-show="columns.includes('ddp_nio')" x-cloak class="px-3 py-2.5 text-right tabular-nums text-slate-900 font-bold">C$ {{ number_format($totals['nio']['ddp'], 2) }}</td>
                            <td x-show="columns.includes('price_nio')" x-cloak class="px-3 py-2.5 text-right tabular-nums text-gray-400 font-normal">—</td>
                            <td x-show="columns.includes('total_nio')" x-cloak class="px-3 py-2.5 text-right tabular-nums font-black text-emerald-800 bg-emerald-50/70 border-x border-emerald-100" title="Venta total acumulada en C$">C$ {{ number_format($totals['sales_nio'], 2) }}</td>
                            <td x-show="columns.includes('net_profit')" x-cloak class="px-3 py-2.5 text-right tabular-nums text-emerald-700 font-bold">$ {{ number_format($totals['net_profit'], 2) }}</td>
                            <td x-show="columns.includes('gross_profit')" x-cloak class="px-3 py-2.5 text-right tabular-nums text-emerald-700 font-bold">$ {{ number_format($totals['gross_profit'], 2) }}</td>
                            <td x-show="columns.includes('gross_margin')" x-cloak class="px-3 py-2.5 text-right tabular-nums text-slate-800 font-bold">{{ number_format($totals['gross_margin'], 2) }} %</td>
                            <td class="px-3 py-2.5 text-right"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
