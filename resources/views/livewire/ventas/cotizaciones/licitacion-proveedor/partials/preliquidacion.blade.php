@php
    $totals = $this->preliquidationTotals();
    $control = 'preliq-input';
@endphp
<section aria-label="Preliquidación" class="preliq-sheet" x-data="{ trackEdit(event) { if ([...event.target.attributes].some(a => a.name.startsWith('wire:model') &amp;&amp; /^(preliquidation\.|supplierBidItems\.|supplierBidExtraItems\.|supplierItemProformas\.)/.test(a.value))) detailsDirty = true } }" x-on:keydown.enter="if ($event.target.matches('input:not([type=file])')) { $event.preventDefault(); const inputs = [...$el.querySelectorAll('input:not([type=file]):not(:disabled), select:not(:disabled)')].filter(el => el.getClientRects().length); inputs[inputs.indexOf($event.target) + 1]?.focus(); }" x-on:focusin="if ($event.target.matches('input[type=number]')) $event.target.select()" x-on:input="trackEdit($event)" x-on:change="trackEdit($event)">
    <div class="preliq-config">
        <label class="text-sm font-medium text-gray-700">Transporte
            <select wire:model.live="preliquidation.transport" class="{{ $control }}">
                <option value="air">✈️ Aéreo</option>
                <option value="sea">🚢 Marítimo</option>
            </select>
        </label>
        <label class="text-sm font-medium text-gray-700">Impuestos
            <select wire:model.live="preliquidation.taxed" class="{{ $control }}"><option value="1">Con impuestos</option><option value="0">Sin impuestos</option></select>
        </label>
        <label class="text-sm font-medium text-gray-700">Margen de utilidad (%)<input type="number" min="0" max="99.99" step="0.01" wire:model.live.debounce.400ms="preliquidation.margin" class="{{ $control }}"></label>
        <label class="text-sm font-medium text-gray-700">Tipo de cambio C$ / USD<input type="number" min="0.01" step="0.0001" wire:model.live.debounce.400ms="preliquidation.exchange" class="{{ $control }}"></label>
    </div>
    @if($errors->any())<div role="alert" class="text-sm text-rose-700">@foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>@endif
    @foreach($preliquidationLegacyCosts as $field => $amount)<p class="text-sm text-amber-800">{{ $field === 'freight' ? 'Freight' : 'Manejo' }} anterior: USD {{ number_format($amount, 2) }} en total. Falta peso para convertirlo a tarifa por libra; define la tarifa antes de guardar.</p>@endforeach
    @if($preliquidationChange)<p role="status" class="text-xs text-gray-500">{{ $preliquidationChange }}</p>@endif
    @if($totals['complete'] < $totals['count'])<p class="text-sm text-amber-800">Cálculos provisionales: hay partidas con datos incompletos.</p>@endif
    @include('livewire.ventas.cotizaciones.licitacion-proveedor.partials.preliquidacion-items')
    @if($totals['weight'] <= 0)<p class="text-sm text-amber-700">Ingresa el peso de las partidas para distribuir los costos de importación.</p>@endif
    <div class="preliq-financial">
        <div class="preliq-costs">
            @foreach([
                'Importación' => ['handling' => 'Manejo', 'freight' => 'Freight cost', 'customs' => 'Desaduanaje', 'local_transport' => 'Transporte y facturación'],
                'Impuestos / finanzas' => ['commission' => 'Comisión transferencia', 'financing' => 'Financiamiento', 'iva' => 'IVA', 'dai' => 'DAI', 'isc' => 'ISC']
            ] as $costTitle => $costFields)
                <div class="preliq-cost-panel">
                    <h3>{{ $costTitle }}</h3>
                    <div class="overflow-x-auto">
                    <table class="preliq-cost-table">
                        <thead><tr><th>Concepto</th><th>Tarifa</th><th>USD</th><th>C$</th></tr></thead>
                        <tbody>
                        @foreach($costFields as $field => $label)
                    @php
                        $percent = in_array($field, ['commission', 'financing', 'iva', 'dai', 'isc']);
                        $perPound = in_array($field, ['handling', 'freight']);
                        $amount = $totals['costs'][$field];
                    @endphp
                    <tr class="hover:bg-gray-50/50">
                        <th scope="row" class="px-5 py-2 font-medium text-gray-700">{{ $label }}</th>
                        <td class="px-5 py-2">
                            @if($percent || $perPound)
                                <div class="flex items-center gap-2"><input aria-label="{{ $label }} {{ $percent ? 'porcentaje' : 'USD por libra' }}" type="number" min="0" step="{{ $perPound ? 'any' : '0.01' }}" wire:model.live.debounce.400ms="preliquidation.{{ $field }}" @disabled(in_array($field, ['iva', 'dai', 'isc']) && !($preliquidation['taxed'] ?? true)) class="{{ $control }} max-w-32 disabled:bg-gray-50"><span>{{ $percent ? '%' : 'USD/lb' }}</span></div>
                            @else
                                <span class="tabular-nums">{{ $totals['weight'] > 0 ? number_format($totals['cost_rates'][$field], 4) : '—' }} USD/lb</span>
                            @endif
                        </td>
                        <td class="px-5 py-2 text-right tabular-nums">
                            @if($perPound)
                                <input aria-label="{{ $label }} total USD"
                                       type="number"
                                       min="0"
                                       step="0.01"
                                       value="{{ $amount > 0 ? number_format($amount, 2, '.', '') : '' }}"
                                       wire:change="updateCostTotal('{{ $field }}', $event.target.value)"
                                       x-on:input="detailsDirty = true"
                                       class="{{ $control }} max-w-40 text-right"
                                       placeholder="0.00"
                                       wire:key="cost-total-{{ $field }}-{{ round($amount, 2) }}">
                            @elseif($percent)
                                {{ number_format($amount, 2) }}
                            @else
                                <input aria-label="{{ $label }} USD" type="number" min="0" step="0.01" wire:model.live.debounce.400ms="preliquidation.{{ $field }}" class="{{ $control }} max-w-40 text-right">
                            @endif
                        </td>
                        <td class="px-5 py-2 text-right tabular-nums">{{ number_format($totals['costs_nio'][$field], 2) }}</td>
                    </tr>

                        @endforeach
                        </tbody>
                    </table>
                    </div>
                </div>
            @endforeach
        </div>
        <aside class="preliq-results" aria-label="Resultado financiero">
            <h3>Resultado financiero</h3>
            <dl>
            @foreach(['exw' => 'Costo EXW', 'ddp' => 'Costo DDP', 'sales' => 'Precio de venta', 'gross_profit' => 'Utilidad bruta', 'net_profit' => 'Utilidad neta'] as $field => $label)
                <div class="{{ in_array($field, ['sales', 'net_profit']) ? 'preliq-result-positive' : '' }}">
                    <dt>{{ $label }} <span tabindex="0" role="img" aria-label="{{ $field === 'sales' ? 'Precio final del cliente por cantidad; sin precio manual, se aplica el margen' : ($field === 'net_profit' ? 'Precio de venta menos costo DDP' : ($field === 'gross_profit' ? 'Precio de venta menos costo EXW' : ($field === 'ddp' ? 'CIF más impuestos, comisiones, desaduanaje y transporte' : 'Precio unitario por cantidad'))) }}" title="{{ $field === 'sales' ? 'Precio final por cantidad; sin precio manual: DDP / (1 - margen)' : ($field === 'net_profit' ? 'Venta - DDP' : ($field === 'gross_profit' ? 'Venta - EXW' : ($field === 'ddp' ? 'CIF + impuestos + comisiones + gastos de importación' : 'Precio unitario x cantidad'))) }}">&#9432;</span></dt>
                    <dd>$ {{ number_format($totals[$field], 2) }}<small>C$ {{ number_format($totals['nio'][$field], 2) }}</small></dd>
                </div>
            @endforeach
                <div><dt>Margen neto</dt><dd>{{ number_format($totals['net_margin'], 2) }} %<small>Bruto: {{ number_format($totals['gross_margin'], 2) }} %</small></dd></div>
            </dl>
        </aside>
    </div>
</section>
