@if($showManageItemsModal ?? false)
    @php
        $itemEditorStage = $quotationItemEditorStage ?? 'customer_request';
        $showSupplierPricing = in_array($itemEditorStage, ['client_offer', 'purchase_preparation', 'supplier_response', 'supplier_bid', 'supplier_selection'], true);
        $showClientPricing = $itemEditorStage === 'client_offer';
        $itemEditorContext = match ($itemEditorStage) {
            'supplier_bid' => ['title' => 'Partidas a cotizar', 'description' => 'Organiza los productos, cantidades y precios de referencia para proveedores.', 'badge' => 'Licitación proveedor'],
            'supplier_response' => ['title' => 'Partidas de la orden', 'description' => 'Agrega, importa o modifica los productos, cantidades y precios unitarios del proveedor.', 'badge' => 'Respuesta proveedor'],
            'client_offer' => ['title' => 'Partidas y precios', 'description' => 'Prepara los productos, costos de referencia y precios que formarán la oferta al cliente.', 'badge' => 'Oferta cliente'],
            'customer_approval' => ['title' => 'Partidas de la oferta', 'description' => 'Consulta y corrige la identificación de las partidas aprobadas por el cliente.', 'badge' => 'Aprobación cliente'],
            'purchase_preparation', 'purchase_order_generated' => ['title' => 'Partidas de compra', 'description' => 'Revisa los productos y el costo de proveedor utilizado para preparar la compra.', 'badge' => 'Orden de compra'],
            default => ['title' => 'Partidas de la solicitud', 'description' => 'Agrega, importa o corrige los productos solicitados por el cliente.', 'badge' => 'Solicitud cliente'],
        };
    @endphp
    <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-950/60 p-4 backdrop-blur-sm">
        <div class="relative w-full max-w-[96vw] 2xl:max-w-7xl rounded-2xl bg-white shadow-2xl border border-slate-200/80 overflow-hidden flex flex-col max-h-[92vh]">
            
            {{-- Header --}}
            <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/80 px-6 py-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-slate-900 text-white flex items-center justify-center shadow-sm">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-sm font-black tracking-tight text-slate-900">{{ $itemEditorContext['title'] }}</h3>
                            <span class="rounded-lg bg-slate-200/70 px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-600">{{ $itemEditorContext['badge'] }}</span>
                        </div>
                        <p class="text-xs font-medium text-slate-500">
                            {{ $itemEditorContext['description'] }}
                        </p>
                    </div>
                </div>
                <button type="button" wire:click="closeManageItemsModal" class="rounded-xl p-2 text-slate-400 hover:bg-slate-200/70 hover:text-slate-700 transition">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Toolbar --}}
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-white px-6 py-3">
                <div class="flex items-center gap-2">
                    <button type="button" wire:click="addEditableQuotationItem" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 hover:border-slate-300 transition shadow-xs">
                        <svg class="h-4 w-4 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
                        Agregar producto
                    </button>
                    <button type="button" wire:click="openBulkImportModalForQuote" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 hover:border-slate-300 hover:bg-slate-50 transition shadow-xs">
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Importar productos
                    </button>
                </div>
                <div class="text-right text-xs">
                    <span class="font-black text-slate-900">{{ count($editableQuotationItems) }}</span> partida(s) &middot;
                    Cantidad total: <strong class="font-black text-slate-900">{{ collect($editableQuotationItems)->sum(fn ($i) => (float) ($i['quantity'] ?? 0)) }}</strong>
                </div>
            </div>

            {{-- Body / Table --}}
            <div class="p-6 space-y-4 overflow-y-auto flex-1 text-xs">
                @if(session()->has('error'))
                    <div class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 font-bold text-xs">
                        {{ session('error') }}
                    </div>
                @endif

                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                    <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs" style="min-width: {{ $showClientPricing ? '1820px' : ($showSupplierPricing ? '1680px' : '1380px') }};">
                        <thead class="border-b border-slate-100 bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-2 py-3 text-center" style="width: 44px; min-width: 44px;">#</th>
                                <th class="px-2 py-3" style="width: 155px; min-width: 155px;">Código / Ref. *</th>
                                <th class="px-2 py-3" style="width: 250px; min-width: 250px;">Descripción / Nombre *</th>
                                <th class="px-2 py-3" style="width: 200px; min-width: 200px;">Proveedor</th>
                                <th class="px-2 py-3 text-center" style="width: 90px; min-width: 90px;">Cant. *</th>
                                <th class="px-2 py-3 text-center" style="width: 100px; min-width: 100px;">Unidad</th>
                                <th class="px-2 py-3" style="width: 130px; min-width: 130px;">Marca</th>
                                <th class="px-2 py-3" style="width: 125px; min-width: 125px;">Modelo</th>
                                <th class="px-2 py-3" style="width: 145px; min-width: 145px;">Número de serie</th>
                                @if($showSupplierPricing)
                                    <th class="px-2 py-3 text-center" style="width: 95px; min-width: 95px;">Moneda</th>
                                    <th class="px-2 py-3 text-right" style="width: 135px; min-width: 135px;">Precio / Costo</th>
                                @endif
                                @if($showClientPricing)
                                    <th class="px-2 py-3 text-right" style="width: 140px; min-width: 140px;">Precio cliente NIO</th>
                                @endif
                                <th class="px-2 py-3" style="width: 220px; min-width: 220px;">Notas</th>
                                <th class="px-1 py-3 text-center" style="width: 44px; min-width: 44px;"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium">
                            @foreach($editableQuotationItems as $index => $item)
                                <tr class="hover:bg-slate-50/60 transition-colors">
                                    <td class="px-2 py-2 text-center text-slate-400 font-mono font-bold" style="width: 44px; min-width: 44px;">
                                        {{ $index + 1 }}
                                    </td>
                                    <td class="px-1.5 py-1.5" style="width: 155px; min-width: 155px;">
                                        <input
                                            type="text"
                                            wire:model="editableQuotationItems.{{ $index }}.part_code"
                                            placeholder="Código de parte"
                                            class="w-full rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-mono font-bold text-slate-900 focus:border-slate-900 focus:ring-1 focus:ring-slate-900 bg-white"
                                        >
                                    </td>
                                    <td class="px-1.5 py-1.5" style="width: 250px; min-width: 250px;">
                                        <input
                                            type="text"
                                            wire:model="editableQuotationItems.{{ $index }}.part_name"
                                            placeholder="Nombre de la pieza"
                                            class="w-full rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-800 focus:border-slate-900 focus:ring-1 focus:ring-slate-900 bg-white"
                                        >
                                    </td>
                                    <td class="px-1.5 py-1.5" style="width: 200px; min-width: 200px;">
                                        <select
                                            wire:model="editableQuotationItems.{{ $index }}.supplier_id"
                                            class="w-full rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-slate-700 focus:border-slate-900 focus:ring-1 focus:ring-slate-900 bg-white"
                                        >
                                            <option value="">-- Sin asignar --</option>
                                            @php
                                                $modalQuote = $selectedQuote ?? ($this->selectedQuote() ?? null);
                                                $quoteRfqSuppliers = $modalQuote ? $modalQuote->supplierRfqDocuments->map(fn($d) => $d->supplier)->filter()->unique('id') : collect();
                                                $quoteRfqSupplierIds = $quoteRfqSuppliers->pluck('id')->all();
                                                $modalSuppliers = $suppliers ?? \App\Models\Entity::where('type', 'proveedor')->where('status', '!=', 'inactivo')->orderBy('name')->get();
                                                $otherSuppliers = $modalSuppliers->reject(fn($s) => in_array($s->id, $quoteRfqSupplierIds));
                                            @endphp
                                            @if($quoteRfqSuppliers->isNotEmpty())
                                                <optgroup label="Proveedores con RFQ">
                                                    @foreach($quoteRfqSuppliers as $sup)
                                                        <option value="{{ $sup->id }}">{{ $sup->name }}</option>
                                                    @endforeach
                                                </optgroup>
                                            @endif
                                            @if($otherSuppliers->isNotEmpty())
                                                <optgroup label="Otros proveedores">
                                                    @foreach($otherSuppliers as $sup)
                                                        <option value="{{ $sup->id }}">{{ $sup->name }}</option>
                                                    @endforeach
                                                </optgroup>
                                            @endif
                                        </select>
                                    </td>
                                    <td class="px-1.5 py-1.5 text-center" style="width: 90px; min-width: 90px;">
                                        <input
                                            type="number"
                                            min="1"
                                            step="1"
                                            wire:model="editableQuotationItems.{{ $index }}.quantity"
                                            class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs text-center font-black text-slate-900 focus:border-slate-900 focus:ring-1 focus:ring-slate-900 bg-white [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                        >
                                    </td>
                                    <td class="px-1.5 py-1.5 text-center" style="width: 100px; min-width: 100px;">
                                        <input
                                            type="text"
                                            wire:model="editableQuotationItems.{{ $index }}.unit"
                                            placeholder="PZA / Unidad"
                                            class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs text-center font-medium text-slate-700 focus:border-slate-900 focus:ring-1 focus:ring-slate-900 bg-white"
                                        >
                                    </td>
                                    <td class="px-1.5 py-1.5" style="width: 130px; min-width: 130px;">
                                        <input
                                            type="text"
                                            wire:model="editableQuotationItems.{{ $index }}.part_brand"
                                            placeholder="Marca (opcional)"
                                            class="w-full rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs text-slate-700 focus:border-slate-900 focus:ring-1 focus:ring-slate-900 bg-white"
                                        >
                                    </td>
                                    <td class="px-1.5 py-1.5" style="width: 125px; min-width: 125px;">
                                        <input
                                            type="text"
                                            wire:model="editableQuotationItems.{{ $index }}.part_model"
                                            placeholder="Modelo (opcional)"
                                            class="w-full rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs text-slate-700 focus:border-slate-900 focus:ring-1 focus:ring-slate-900 bg-white"
                                        >
                                    </td>
                                    <td class="px-1.5 py-1.5" style="width: 145px; min-width: 145px;">
                                        <input
                                            type="text"
                                            wire:model="editableQuotationItems.{{ $index }}.part_serial_number"
                                            placeholder="Serie (opcional)"
                                            class="w-full rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-700 focus:border-slate-900 focus:ring-1 focus:ring-slate-900"
                                        >
                                    </td>
                                    @if($showSupplierPricing)
                                        <td class="px-1.5 py-1.5 text-center" style="width: 95px; min-width: 95px;">
                                            <select wire:model="editableQuotationItems.{{ $index }}.supplier_currency" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs font-bold text-slate-800 focus:border-slate-900 focus:ring-1 focus:ring-slate-900">
                                                <option value="USD">USD</option>
                                                <option value="NIO">NIO</option>
                                            </select>
                                        </td>
                                        <td class="px-1.5 py-1.5 text-right" style="width: 135px; min-width: 135px;">
                                            <input type="number" min="0" step="0.01" wire:model="editableQuotationItems.{{ $index }}.supplier_unit_cost" class="w-full rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-right text-xs font-bold tabular-nums text-slate-900 focus:border-slate-900 focus:ring-1 focus:ring-slate-900 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" placeholder="0.00">
                                        </td>
                                    @endif
                                    @if($showClientPricing)
                                        <td class="px-1.5 py-1.5 text-right" style="width: 140px; min-width: 140px;">
                                            <input type="number" min="0" step="0.01" wire:model="editableQuotationItems.{{ $index }}.client_unit_price" class="w-full rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-right text-xs font-bold tabular-nums text-slate-900 focus:border-slate-900 focus:ring-1 focus:ring-slate-900 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" placeholder="0.00">
                                        </td>
                                    @endif
                                    <td class="px-1.5 py-1.5" style="width: 220px; min-width: 220px;">
                                        <input
                                            type="text"
                                            wire:model="editableQuotationItems.{{ $index }}.notes"
                                            placeholder="Observaciones del producto"
                                            class="w-full rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-700 focus:border-slate-900 focus:ring-1 focus:ring-slate-900"
                                        >
                                    </td>
                                    <td class="px-1 py-1.5 text-center" style="width: 44px; min-width: 44px;">
                                        <button
                                            type="button"
                                            wire:click="removeEditableQuotationItem({{ $index }})"
                                            wire:confirm="¿Eliminar esta partida de la cotización?"
                                            title="Eliminar partida"
                                            class="rounded-lg p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600 transition"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">
                        Motivo del cambio <span class="text-slate-400 font-normal">(opcional; queda registrado en auditoría)</span>
                    </label>
                    <input
                        type="text"
                        wire:model="quotationItemChangeReason"
                        placeholder="Ej: Corrección de número de parte solicitado por el cliente o ajuste de cantidad"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs bg-slate-50 focus:bg-white focus:ring-2 focus:ring-slate-900 transition"
                    >
                </div>
            </div>

            {{-- Footer --}}
            <div class="flex items-center justify-between border-t border-slate-100 bg-slate-50/80 px-6 py-4">
                <button type="button" wire:click="closeManageItemsModal" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-100 transition shadow-xs">
                    Cancelar
                </button>
                <button
                    type="button"
                    wire:click="saveQuotationItems"
                    wire:confirm="¿Guardar los cambios de las partidas?"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-6 py-2.5 text-xs font-black text-white shadow-sm hover:bg-black transition"
                >
                    <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    <span wire:loading.remove wire:target="saveQuotationItems">Guardar productos</span>
                    <span wire:loading wire:target="saveQuotationItems">Guardando...</span>
                </button>
            </div>
        </div>
    </div>
@endif

{{-- Incluir modal de carga masiva --}}
@include('livewire.ventas.cotizaciones.shared.modal-carga-masiva')
