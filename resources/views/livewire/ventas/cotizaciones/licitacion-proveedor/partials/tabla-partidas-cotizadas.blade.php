@php
    $cellControl = 'h-9 w-full rounded-lg border border-slate-300 bg-white px-2 text-sm leading-5 text-slate-900 placeholder:text-slate-400 shadow-sm transition-colors hover:border-slate-400 focus:border-slate-800 focus:bg-white focus:ring-2 focus:ring-slate-200 disabled:bg-slate-100 disabled:text-slate-500';
@endphp

<section aria-labelledby="supplier-items-heading" class="min-w-0">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
        <h4 id="supplier-items-heading" class="text-sm font-semibold text-slate-900">Partidas</h4>
        <div class="flex flex-wrap items-center gap-3">
            <span x-cloak x-show="dirtyItems.length || detailsDirty" role="status" class="text-sm font-medium text-amber-700">Cambios sin guardar</span>
            <button type="button" wire:click="saveAllSupplierResponseItems" wire:loading.attr="disabled" wire:target="saveAllSupplierResponseItems" x-bind:disabled="saving || uploading || !@js((bool)$selectedRfqDocument?->supplier)" class="inline-flex h-10 items-center justify-center gap-1.5 rounded-xl bg-slate-800 px-4 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-slate-900 disabled:opacity-50">
                <span wire:loading.remove wire:target="saveAllSupplierResponseItems">Guardar partidas</span>
                <span wire:loading wire:target="saveAllSupplierResponseItems">Guardando...</span>
            </button>
            @if($selectedRfqDocument)
                <details class="relative">
                    <summary class="inline-flex h-10 cursor-pointer items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 text-sm font-semibold text-slate-700 shadow-sm transition-colors hover:bg-slate-50">
                        <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M7.5 12 12 16.5m0 0 4.5-4.5M12 16.5V3"/></svg>
                        <span>Importar / exportar</span>
                    </summary>
                    <div class="absolute right-0 z-40 mt-1 w-64 space-y-1 rounded-xl border border-gray-200 bg-white p-2 shadow-lg">
                        <a href="{{ route('sales.quotations.template.supplier-response', ['quotationRequest' => $selectedQuote->id, 'rfqDocument' => $selectedRfqDocument->id]) }}" class="block rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Descargar formato Excel</a>
                        <label class="block cursor-pointer rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            Cargar respuesta Excel
                            <input type="file" wire:model="supplierBidBulkFile" accept=".xlsx,.xls" class="sr-only">
                        </label>
                    </div>
                </details>
            @endif
        </div>
    </div>

    <div class="overflow-x-auto overflow-y-auto border-b border-slate-200 max-h-[65vh]" role="region" aria-label="Hoja de trabajo de partidas cotizadas" tabindex="0">
        <table class="supplier-worksheet w-full table-fixed border-separate border-spacing-0 text-left text-sm">
            <colgroup>
                <col class="w-[11%]">
                <col class="w-[10%]">
                <col class="w-[21%]">
                <col class="w-[8%]">
                <col class="w-[11%]">
                <col class="w-[11%]">
                <col class="w-[11%]">
                <col class="w-[9%]">
                <col class="w-[8%]">
            </colgroup>
            <thead class="sticky top-0 z-20 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="border-b border-slate-200 px-2 py-3 font-semibold">Respuesta</th>
                    <th class="border-b border-slate-200 px-2 py-3 font-semibold">Parte</th>
                    <th class="border-b border-slate-200 px-2 py-3 font-semibold">Descripción</th>
                    <th class="border-b border-slate-200 px-1 py-3 text-center font-semibold">Cant.</th>
                    <th class="border-b border-slate-200 px-2 py-3 text-right font-semibold">Precio {{ $supplierCurrency }}</th>
                    <th class="border-b border-slate-200 px-2 py-3 font-semibold">Garantía</th>
                    <th class="border-b border-slate-200 px-2 py-3 font-semibold">Entrega</th>
                    <th class="border-b border-slate-200 px-2 py-3 font-semibold">Estado</th>
                    <th class="border-b border-l border-slate-200 px-2 py-3 text-center font-semibold">Evidencia</th>
                </tr>
            </thead>
            <tbody>
                @forelse($responseItems as $item)
                    @php
                        $row = $supplierBidItems[$item->id] ?? [];
                        $status = $row['item_status'] ?? 'quoted';
                        $description = $item->part_name ?: $item->description ?: 'Sin descripción';
                        $search = implode(' ', array_filter([$item->part_number, $description, $row['quoted_part_number'] ?? null, $row['quoted_description'] ?? null, $row['brand'] ?? null, $row['part_model'] ?? null, $row['serial_number'] ?? null]));
                        $saved = $activeSupplierResponse?->items?->firstWhere('quotation_request_item_id', $item->id);
                        $persisted = filled($row['response_item_id'] ?? null);
                    @endphp
                    <tr wire:key="supplier-bid-table-row-{{ $item->id }}" data-supplier-row data-item-id="{{ $item->id }}"
                        x-data="{ itemKey:@js((string)$item->id), price:@js((string)($row['unit_cost'] ?? '')), warranty:@js((string)($row['warranty'] ?? '')), delivery:@js((string)($row['delivery_time'] ?? '')), responseStatus:@js($status), persisted:@js($persisted), searchText:@js($search), isDirty(){return dirtyItems.map(String).includes(this.itemKey)}, hasRequiredData(){return this.responseStatus!=='not_quoted'&&Number(this.price)>0&&String(this.warranty || '').trim()!==''&&String(this.delivery || '').trim()!==''}, displayState(){if(this.isDirty())return 'pending';if(this.responseStatus==='not_quoted')return 'unavailable';return (this.persisted || $wire.supplierBidItems[this.itemKey]?.response_item_id)?'complete':'pending'} }"
                        x-on:supplier-bid-draft-saved.window="if($event.detail.savedIds.map(String).includes(itemKey))persisted=true" x-bind:class="displayState()==='complete'?'bg-emerald-50':(displayState()==='unavailable'?'bg-slate-50':'bg-amber-50')" class="align-middle supplier-capture-row">
                        <td class="border-b border-slate-100 px-2 py-2">
                            <select x-model="responseStatus" x-bind:disabled="saving" wire:model="supplierBidItems.{{ $item->id }}.item_status" x-on:change="markDirty(itemKey); if(responseStatus === 'not_quoted') $wire.set('supplierBidItems.{{ $item->id }}.quoted_quantity', {{ (float)$item->quantity ?: 1 }}, false)" class="h-9 w-full min-w-0 rounded-lg border border-slate-300 bg-white pl-2 pr-7 py-0 text-sm font-medium text-slate-700 shadow-sm transition-colors hover:border-slate-400 focus:border-slate-800 focus:ring-2 focus:ring-slate-200" aria-label="Respuesta para {{ $item->part_number }}">
                                <option value="quoted">Cotizada</option>
                                <option value="alternative">Alternativa</option>
                                <option value="not_quoted">Sin respuesta</option>
                            </select>
                        </td>
                        <td class="border-b border-slate-100 px-2 py-2.5 font-semibold text-slate-900"><span class="block truncate" title="{{ $item->part_number ?: 'Sin referencia' }}">{{ $item->part_number ?: 'Sin referencia' }}</span></td>
                        <td class="border-b border-slate-100 px-2 py-2 text-slate-600"><span class="block truncate" title="{{ $description }}">{{ $description }}</span></td>
                        <td class="border-b border-slate-100 px-1 py-2 text-center font-semibold tabular-nums text-slate-700"><span title="Cantidad definida en la solicitud" aria-label="Cantidad solicitada de {{ $item->part_number }}">{{ (float) $item->quantity }}</span></td>
                        <td class="border-b border-slate-100 px-2 py-2"><input x-model="price" x-bind:disabled="saving" data-supplier-price wire:model="supplierBidItems.{{ $item->id }}.unit_cost" x-on:input="markDirty(itemKey)" x-on:keydown.shift.tab="const row = $event.target.closest('[data-supplier-row]'); const rows = [...row.closest('tbody').querySelectorAll('[data-supplier-row]')].filter(r => r.offsetParent !== null); const input = rows[rows.indexOf(row) - 1]?.querySelector('[data-supplier-delivery]'); if(input) { $event.preventDefault(); input.focus(); input.select(); }" type="number" min="0" step="0.01" inputmode="decimal" placeholder="0.00" class="{{ $cellControl }} text-right font-semibold tabular-nums" aria-label="Precio unitario de {{ $item->part_number }}"></td>
                        <td class="border-b border-slate-100 px-2 py-2"><input x-model="warranty" x-bind:disabled="saving" wire:model="supplierBidItems.{{ $item->id }}.warranty" x-on:input="markDirty(itemKey)" type="text" list="warranty-options-{{ $item->id }}" class="{{ $cellControl }}" placeholder="Ej. 6 meses" aria-label="Garantía de {{ $item->part_number }}"><datalist id="warranty-options-{{ $item->id }}"><option value="Sin garantía"><option value="30 días"><option value="3 meses"><option value="6 meses"><option value="1 año"></datalist></td>
                        <td class="border-b border-slate-100 px-2 py-2"><input x-model="delivery" x-bind:disabled="saving" data-supplier-delivery wire:model="supplierBidItems.{{ $item->id }}.delivery_time" x-on:input="markDirty(itemKey)" x-on:keydown.tab="if(!$event.shiftKey) { const row = $event.target.closest('[data-supplier-row]'); const rows = [...row.closest('tbody').querySelectorAll('[data-supplier-row]')].filter(r => r.offsetParent !== null); const input = rows[rows.indexOf(row) + 1]?.querySelector('[data-supplier-price]'); if(input) { $event.preventDefault(); input.focus(); input.select(); } }" type="text" class="{{ $cellControl }}" placeholder="Ej. 5 a 7 días" aria-label="Entrega de {{ $item->part_number }}"></td>
                        <td class="border-b border-slate-100 px-2 py-2.5 text-xs sm:text-sm"><span x-show="displayState()==='complete'" class="font-semibold text-emerald-700">Guardada</span><span x-show="displayState()==='unavailable'" class="font-semibold text-slate-600">Sin resp.</span><span x-show="displayState()==='pending'" class="font-semibold text-amber-700" x-text="isDirty()?'Sin guardar':'Pendiente'"></span>@error("supplierBidItems.{$item->id}")<span class="mt-0.5 block max-w-40 truncate text-xs text-rose-700" title="{{ $message }}">{{ $message }}</span>@enderror</td>
                        <td class="border-b border-slate-100 px-2 py-2 text-center"><div class="flex items-center justify-center gap-1"><label class="relative inline-flex cursor-pointer items-center justify-center rounded-lg p-2 text-slate-500 hover:bg-slate-50 focus-within:ring-2 focus-within:ring-slate-200" title="Adjuntar evidencia de {{ $item->part_number }}"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94a3 3 0 1 1 4.243 4.243L8.552 18.32a1.5 1.5 0 0 1-2.122-2.122l7.693-7.693"/></svg><input type="file" multiple wire:model="supplierItemProformas.{{ $item->id }}" x-bind:disabled="saving || uploading" x-on:change="markDirty(@js((string)$item->id))" class="absolute inset-0 w-full cursor-pointer opacity-0" aria-label="Adjuntar evidencia de {{ $item->part_number }}"></label><button type="button" wire:click="toggleSupplierBidItemDetail({{ $item->id }})" class="inline-flex items-center justify-center gap-0.5 rounded-lg px-2 py-1.5 text-xs font-medium text-slate-500 transition-colors hover:bg-white hover:text-slate-900 focus:ring-2 focus:ring-slate-200" aria-expanded="{{ $expandedSupplierBidItemId === (int)$item->id ? 'true' : 'false' }}" aria-label="Editar detalles de {{ $item->part_number }}"><svg class="h-3.5 w-3.5 transition-transform {{ $expandedSupplierBidItemId === (int)$item->id ? 'rotate-90' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 18 6-6-6-6"/></svg></button></div></td>
                    </tr>

                    @if($expandedSupplierBidItemId === (int)$item->id)
                        <tr wire:key="supplier-bid-table-detail-{{ $item->id }}" class="bg-slate-50"><td colspan="9" class="border-b border-slate-200 px-3 py-3">
                            <div class="grid grid-cols-2 items-end gap-3 md:grid-cols-12">
                                @foreach([['quoted_part_number','No. parte cotizado','md:col-span-2'],['quoted_description','Descripción cotizada','col-span-2 md:col-span-3'],['brand','Marca','md:col-span-1'],['part_model','Modelo','md:col-span-1'],['serial_number','No. serie','md:col-span-2']] as [$field,$label,$width])
                                    <label class="block min-w-0 {{ $width }}"><span class="mb-1 block truncate text-xs font-semibold leading-5 text-slate-600" title="{{ $label }}">{{ $label }}</span><input x-bind:disabled="saving" x-on:input="markDirty(@js((string)$item->id))" wire:model="supplierBidItems.{{ $item->id }}.{{ $field }}" class="{{ $cellControl }} border-slate-200 bg-white"></label>
                                @endforeach
                                <label class="col-span-2 block min-w-0 md:col-span-2"><span class="mb-1 block truncate text-xs font-semibold leading-5 text-slate-600">Comentario interno</span><input x-bind:disabled="saving" x-on:input="markDirty(@js((string)$item->id))" wire:model="supplierBidItems.{{ $item->id }}.notes" class="{{ $cellControl }} border-slate-200 bg-white"></label>
                                <div class="col-span-2 flex items-end md:col-span-1"><button type="button" wire:click="addSupplierBidResponseLine({{ $item->id }})" class="inline-flex h-8 w-full items-center justify-center rounded-md border border-slate-200 bg-white px-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Desglosar</button></div>
                            </div>
                            <label class="mt-3 block text-sm text-slate-600">Adjuntos de la partida<input x-bind:disabled="saving" type="file" x-on:change="markDirty(@js((string)$item->id))" wire:model="supplierItemProformas.{{ $item->id }}" multiple class="mt-1 block max-w-full text-sm"></label>
                            @if(!empty($supplierItemProformas[$item->id]))<p class="mt-1 text-sm text-amber-700">{{ count($supplierItemProformas[$item->id]) }} archivo(s) sin guardar</p>@endif
                            @if($saved?->attachments?->isNotEmpty())<div class="mt-3 flex flex-wrap gap-1.5">@foreach($saved->attachments as $document)<a href="{{ route('tracker.attachments.show',$document) }}" target="_blank" class="rounded-md border border-slate-200 bg-white px-2 py-1 text-xs text-slate-700 hover:underline">{{ $document->file_name }}</a>@endforeach</div>@endif
                        </td></tr>
                    @endif

                    @foreach(collect($supplierBidExtraItems)->filter(fn ($extra) => (int)($extra['quotation_request_item_id'] ?? 0) === (int)$item->id) as $extraKey => $extra)
                        @php
                            $extraStatus = $extra['item_status'] ?? 'quoted';
                            $extraPart = $extra['quoted_part_number'] ?: 'Nuevo componente';
                            $extraDescription = $extra['quoted_description'] ?: 'Desglose de '.$description;
                            $extraSearch = implode(' ',array_filter([$item->part_number,$extraPart,$extraDescription,$extra['brand'] ?? null,$extra['part_model'] ?? null,$extra['serial_number'] ?? null]));
                            $extraPersisted = filled($extra['response_item_id'] ?? null);
                        @endphp
                        <tr wire:key="supplier-bid-extra-row-{{ $extraKey }}" data-supplier-row data-item-id="{{ $extraKey }}"
                            x-data="{ itemKey:@js((string)$extraKey), price:@js((string)($extra['unit_cost'] ?? '')), warranty:@js((string)($extra['warranty'] ?? '')), delivery:@js((string)($extra['delivery_time'] ?? '')), responseStatus:@js($extraStatus), persisted:@js($extraPersisted), searchText:@js($extraSearch), isDirty(){return dirtyItems.map(String).includes(this.itemKey)}, hasRequiredData(){return this.responseStatus!=='not_quoted'&&Number(this.price)>0&&String(this.warranty || '').trim()!==''&&String(this.delivery || '').trim()!==''}, displayState(){if(this.isDirty())return 'pending';if(this.responseStatus==='not_quoted')return 'unavailable';return (this.persisted || $wire.supplierBidExtraItems[this.itemKey]?.response_item_id)?'complete':'pending'} }"
                            x-on:supplier-bid-draft-saved.window="if($event.detail.savedIds.map(String).includes(itemKey))persisted=true" x-bind:class="displayState()==='complete'?'bg-emerald-50':(displayState()==='unavailable'?'bg-slate-50':'bg-amber-50')" class="align-middle supplier-capture-row">
                            <td class="border-b border-slate-100 px-2 py-2">
                                <select x-model="responseStatus" x-bind:disabled="saving" wire:model="supplierBidExtraItems.{{ $extraKey }}.item_status" x-on:change="markDirty(itemKey); if(responseStatus === 'not_quoted') $wire.set('supplierBidExtraItems.{{ $extraKey }}.quoted_quantity', {{ (float)($extra['quoted_quantity'] ?? 1) ?: 1 }}, false)" class="h-9 w-full min-w-0 rounded-lg border border-slate-300 bg-white pl-2 pr-7 py-0 text-sm font-medium text-slate-700 shadow-sm transition-colors hover:border-slate-400 focus:border-slate-800 focus:ring-2 focus:ring-slate-200" aria-label="Respuesta para línea desglosada">
                                    <option value="quoted">Cotizada</option>
                                    <option value="alternative">Alternativa</option>
                                    <option value="not_quoted">Sin respuesta</option>
                                </select>
                            </td>
                            <td class="border-b border-slate-100 px-2 py-2.5 font-semibold text-slate-900"><span class="block truncate" title="{{ $extraPart }}">{{ $extraPart }}</span><span class="block text-xs font-normal text-slate-500">Desglose</span></td>
                            <td class="border-b border-slate-100 px-2 py-2 text-slate-600"><span class="block truncate" title="{{ $extraDescription }}">{{ $extraDescription }}</span></td>
                            <td class="border-b border-slate-100 px-1 py-2"><input x-bind:disabled="saving" x-on:input="markDirty(itemKey)" wire:model="supplierBidExtraItems.{{ $extraKey }}.quoted_quantity" type="number" min="0.01" step="0.01" inputmode="decimal" placeholder="Cant." class="{{ $cellControl }} px-1 text-center font-semibold tabular-nums" aria-label="Cantidad de línea desglosada"></td>
                            <td class="border-b border-slate-100 px-2 py-2"><input x-model="price" x-bind:disabled="saving" data-supplier-price wire:model="supplierBidExtraItems.{{ $extraKey }}.unit_cost" x-on:input="markDirty(itemKey)" x-on:keydown.shift.tab="const row = $event.target.closest('[data-supplier-row]'); const rows = [...row.closest('tbody').querySelectorAll('[data-supplier-row]')].filter(r => r.offsetParent !== null); const input = rows[rows.indexOf(row) - 1]?.querySelector('[data-supplier-delivery]'); if(input) { $event.preventDefault(); input.focus(); input.select(); }" type="number" min="0" step="0.01" inputmode="decimal" placeholder="0.00" class="{{ $cellControl }} text-right font-semibold tabular-nums" aria-label="Precio de línea desglosada"></td>
                            <td class="border-b border-slate-100 px-2 py-2"><input x-model="warranty" x-bind:disabled="saving" wire:model="supplierBidExtraItems.{{ $extraKey }}.warranty" x-on:input="markDirty(itemKey)" class="{{ $cellControl }}" placeholder="Ej. 6 meses" aria-label="Garantía de línea desglosada"></td>
                            <td class="border-b border-slate-100 px-2 py-2"><input x-model="delivery" x-bind:disabled="saving" data-supplier-delivery wire:model="supplierBidExtraItems.{{ $extraKey }}.delivery_time" x-on:input="markDirty(itemKey)" x-on:keydown.tab="if(!$event.shiftKey) { const row = $event.target.closest('[data-supplier-row]'); const rows = [...row.closest('tbody').querySelectorAll('[data-supplier-row]')].filter(r => r.offsetParent !== null); const input = rows[rows.indexOf(row) + 1]?.querySelector('[data-supplier-price]'); if(input) { $event.preventDefault(); input.focus(); input.select(); } }" class="{{ $cellControl }}" placeholder="Ej. 5 a 7 días" aria-label="Entrega de línea desglosada"></td>
                            <td class="border-b border-slate-100 px-2 py-2.5 text-xs sm:text-sm"><span x-show="displayState()==='complete'" class="font-semibold text-emerald-700">Guardada</span><span x-show="displayState()==='unavailable'" class="font-semibold text-slate-600">Sin resp.</span><span x-show="displayState()==='pending'" class="font-semibold text-amber-700" x-text="isDirty()?'Sin guardar':'Pendiente'"></span>@error("supplierBidExtraItems.{$extraKey}")<span class="mt-0.5 block max-w-40 truncate text-xs text-rose-700" title="{{ $message }}">{{ $message }}</span>@enderror</td>
                            <td class="border-b border-slate-100 px-2 py-2 text-center"><div class="flex items-center justify-center gap-1"><label class="relative inline-flex cursor-pointer items-center justify-center rounded-lg p-2 text-slate-500 hover:bg-slate-50 focus-within:ring-2 focus-within:ring-slate-200" title="Adjuntar evidencia del desglose"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94a3 3 0 1 1 4.243 4.243L8.552 18.32a1.5 1.5 0 0 1-2.122-2.122l7.693-7.693"/></svg><input type="file" multiple wire:model="supplierItemProformas.{{ $extraKey }}" x-bind:disabled="saving || uploading" x-on:change="markDirty(@js((string)$extraKey))" class="absolute inset-0 w-full cursor-pointer opacity-0" aria-label="Adjuntar evidencia del desglose"></label><button type="button" wire:click="toggleSupplierBidItemDetail('{{ $extraKey }}')" class="inline-flex items-center justify-center gap-0.5 rounded-lg px-2 py-1.5 text-xs font-medium text-slate-500 hover:bg-white hover:text-slate-900 focus:ring-2 focus:ring-slate-200" aria-label="Editar detalles del desglose"><svg class="h-3.5 w-3.5 transition-transform {{ $expandedSupplierBidItemId === (string)$extraKey ? 'rotate-90' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 18 6-6-6-6"/></svg></button></div></td>
                        </tr>
                        @if($expandedSupplierBidItemId === (string)$extraKey)
                            <tr wire:key="supplier-bid-extra-detail-{{ $extraKey }}" class="bg-slate-50"><td colspan="9" class="border-b border-slate-200 px-3 py-3"><div class="grid grid-cols-2 items-end gap-3 md:grid-cols-12">
                                @foreach([['quoted_part_number','No. parte cotizado','md:col-span-2'],['quoted_description','Descripción cotizada','col-span-2 md:col-span-3'],['brand','Marca','md:col-span-1'],['part_model','Modelo','md:col-span-1'],['serial_number','No. serie','md:col-span-2'],['notes','Comentario interno','col-span-2 md:col-span-2']] as [$field,$label,$width])<label class="block min-w-0 {{ $width }}"><span class="mb-1 block truncate text-xs font-semibold leading-5 text-slate-600" title="{{ $label }}">{{ $label }}</span><input x-bind:disabled="saving" x-on:input="markDirty(@js((string)$extraKey))" wire:model="supplierBidExtraItems.{{ $extraKey }}.{{ $field }}" class="{{ $cellControl }} border-slate-200 bg-white"></label>@endforeach
                                <div class="col-span-2 flex items-end md:col-span-1"><button type="button" wire:click="removeSupplierBidResponseLine('{{ $extraKey }}')" wire:confirm="¿Quitar esta línea de la respuesta del proveedor?" class="inline-flex h-8 w-full items-center justify-center rounded-md border border-rose-200 bg-white px-2 text-sm font-semibold text-rose-700 hover:bg-rose-50">Quitar</button></div>
                            </div><label class="mt-3 block text-sm">Adjuntos del desglose<input x-bind:disabled="saving" type="file" x-on:change="markDirty(@js((string)$extraKey))" wire:model="supplierItemProformas.{{ $extraKey }}" multiple class="mt-1 block max-w-full text-sm"></label></td></tr>
                        @endif
                    @endforeach
                @empty
                    <tr><td colspan="9" class="px-5 py-8 text-center text-sm text-slate-500">No hay partidas vinculadas al RFQ seleccionado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
