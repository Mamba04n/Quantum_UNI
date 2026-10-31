        @php
            $activeRfqDocument = $editingRfqDocumentId
                ? $selectedQuote->supplierRfqDocuments->firstWhere('id', $editingRfqDocumentId)
                : null;
            $existingRfqForSupplier = ! $activeRfqDocument
                ? (blank($selectedSupplierId)
                    ? $selectedQuote->supplierRfqDocuments->first(fn ($document) => blank($document->supplier_id))
                    : $selectedQuote->supplierRfqDocuments->firstWhere('supplier_id', (int) $selectedSupplierId))
                : null;
            $selectedRfqDocument = $activeRfqDocument ?: $existingRfqForSupplier;
        @endphp
        <section class="min-w-0 bg-white">
            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 bg-white py-3">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">RFQ Proveedor</h3>

                </div>
                <div class="flex items-center gap-2">
                    @if($activeRfqDocument)
                        <button type="button" wire:click="startNewRfqDocument" class="inline-flex items-center gap-1.5 rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-black">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                            Nuevo RFQ
                        </button>
                    @endif
                </div>
            </div>
            <div class="space-y-3">
                @if($activeRfqDocument)
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50/80 p-4 text-xs">
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-amber-900">Editando Documento RFQ: {{ $activeRfqDocument->effective_rfq_number }}</span>
                            <span class="rounded-full bg-amber-200/80 px-2.5 py-0.5 text-sm font-bold text-amber-900">{{ $activeRfqDocument->status_label }}</span>
                        </div>
                        <button type="button" wire:click="startNewRfqDocument" class="rounded-lg bg-amber-800 px-3 py-1.5 text-sm font-semibold text-white hover:bg-amber-900">
                            Cancelar edición / Crear otro RFQ
                        </button>
                    </div>
                @elseif($existingRfqForSupplier)
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-blue-200 bg-blue-50/70 p-4 text-xs">
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-blue-900">Este proveedor ya tiene la orden {{ $existingRfqForSupplier->effective_rfq_number }}.</span>
                            <span class="text-sm font-medium text-blue-800">Al generar se creará una nueva orden separada para los ítems seleccionados.</span>
                        </div>
                        <button type="button" wire:click="editSupplierRfqDocument({{ $existingRfqForSupplier->id }})" class="rounded-lg border border-blue-300 bg-white px-3 py-1.5 text-sm font-semibold text-blue-900 hover:bg-blue-100/80">
                            Editar orden anterior
                        </button>
                    </div>
                @endif

                <div class="grid grid-cols-1 gap-4 border-b border-gray-100 py-3 lg:grid-cols-[minmax(0,1fr)_auto]">
                    <div>
                        <label for="rfq-supplier" class="mb-1 block text-sm font-semibold text-slate-600">Proveedor</label><div class="grid items-start gap-2 sm:grid-cols-2">
                        <div class="mb-2 flex gap-2 sm:mb-0">
                            <input type="search" aria-label="Buscar proveedor" wire:model.live.debounce.300ms="supplierSearch" wire:input="resetSelectedSupplierFromSearch($event.target.value)" class="w-full rounded-xl border-slate-200 bg-white text-xs font-bold focus:border-slate-800 focus:ring-slate-500" placeholder="Buscar proveedor">
                            @if($supplierSearch !== '')
                                <button type="button" wire:click="clearSupplierSearch" class="rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-600 hover:bg-slate-50">Limpiar</button>
                            @endif
                        </div>
                        @if($supplierSearch !== '' && blank($selectedSupplierId))
                            <div class="mb-2 max-h-52 sm:col-span-2 sm:order-last overflow-y-auto rounded-xl border border-slate-200 bg-white text-xs shadow-sm">
                                @forelse($suppliers as $supplier)
                                    <button type="button" wire:click="selectSupplierOption({{ $supplier->id }})" class="block w-full px-4 py-3 text-left hover:bg-slate-50">
                                        <span class="block font-semibold text-slate-900">{{ $supplier->name }}</span>
                                        <span class="mt-0.5 block text-sm font-bold text-slate-500">{{ $supplier->contact_name ?: 'Sin contacto' }}{{ $supplier->email ? ' - '.$supplier->email : '' }}{{ $supplier->country ? ' - '.$supplier->country : '' }}</span>
                                    </button>
                                @empty
                                    <div class="px-4 py-3 font-bold text-slate-400">No hay proveedores con esa búsqueda.</div>
                                @endforelse
                            </div>
                        @endif
                        <select id="rfq-supplier" wire:model.live="selectedSupplierId" class="w-full rounded-xl border-slate-200 bg-white text-xs font-bold focus:border-slate-800 focus:ring-slate-500">
                            <option value="">Sin proveedor (RFQ abierto)</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}">{{ $supplier->name }}{{ $supplier->contact_name ? ' - '.$supplier->contact_name : '' }}</option>
                            @endforeach
                        </select>
                        </div>

                        @if(blank($selectedSupplierId))
                            <p class="mt-1 text-sm text-slate-500">
                                Se generará un RFQ abierto.
                            </p>
                        @endif
                        @if($selectedSupplier)
                            <div class="mt-3 grid grid-cols-1 gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-xs md:grid-cols-4">
                                <p class="truncate"><span class="font-semibold text-slate-500">Proveedor:</span> {{ $selectedSupplier->name }}</p>
                                <p class="truncate"><span class="font-semibold text-slate-500">Contacto:</span> {{ $selectedSupplier->contact_name ?: 'N/D' }}</p>
                                <p class="truncate"><span class="font-semibold text-slate-500">Email:</span> {{ $selectedSupplier->email ?: 'N/D' }}</p>
                                <p class="truncate"><span class="font-semibold text-slate-500">Teléfono:</span> {{ $selectedSupplier->phone_mobile ?: ($selectedSupplier->phone_home ?: 'N/D') }}</p>
                            </div>
                        @endif
                    </div>
                    @if($selectedRfqDocument)
                        <div class="flex flex-col gap-2">
                            <span class="rounded-full bg-white px-3 py-1 text-center text-sm font-semibold uppercase text-slate-600">{{ $selectedRfqDocument->status_label }}</span>
                        </div>
                    @endif
                </div>

                <div class="border-b border-gray-100 py-3">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h4 class="text-sm font-semibold text-slate-900">Partidas del RFQ</h4>
                            <p class="mt-1 text-xs font-medium text-slate-500">
                                {{ count($selectedRfqItemIds) }} de {{ $selectedQuote->items->count() }} seleccionadas
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" wire:click="toggleAllRfqItems" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-100 transition">
                                {{ count($selectedRfqItemIds) === $selectedQuote->items->count() ? 'Desmarcar todos' : 'Seleccionar todos' }}
                            </button>
                        </div>
                    </div>
                    <div class="overflow-hidden rounded-lg border border-slate-200">
                        <div class="max-h-[360px] divide-y divide-slate-100 overflow-y-auto overscroll-contain">
                        @foreach($selectedQuote->items as $item)
                            <details class="group bg-white">
                                <summary class="rfq-item-summary flex cursor-pointer list-none flex-wrap items-center gap-2 px-3 py-2 transition hover:bg-slate-50">
                                    <input type="checkbox" value="{{ $item->id }}" wire:model="selectedRfqItemIds" @click.stop class="shrink-0 rounded border-slate-300 text-slate-900 focus:ring-slate-600">
                                    <p class="min-w-0 flex-1 basis-2/3 sm:basis-0 text-sm font-semibold leading-tight text-slate-900" title="{{ $item->part_name ?: ($item->description ?: 'Pieza sin nombre') }}">
                                        {{ $item->part_name ?: ($item->description ?: 'Pieza sin nombre') }}
                                        <span class="ml-1 font-bold text-slate-400">· {{ $item->part_number ?: 'S/R' }}</span>
                                    </p>
                                    <div class="ml-auto flex shrink-0 items-center gap-2" @click.stop>
                                        @if($editingRfqItemQuantities[$item->id] ?? false)
                                            <input type="number" min="1" step="1" wire:model.defer="rfqItemQuantities.{{ $item->id }}" class="h-7 w-16 rounded-lg border-slate-200 text-right text-xs font-semibold tabular-nums" aria-label="Nueva cantidad de {{ $item->part_name ?: ($item->description ?: 'pieza') }}" autofocus>
                                            <button type="button" wire:click="saveRfqItemQuantity({{ $item->id }})" class="rounded-lg bg-slate-800 px-2 py-1 text-sm font-semibold text-white transition hover:bg-slate-900">Guardar cantidad</button>
                                        @else
                                            <span class="text-xs font-semibold tabular-nums text-slate-700" title="Cantidad de la solicitud cliente">Cant. {{ number_format((float) ($rfqItemQuantities[$item->id] ?? $item->quantity), 0) }}</span>
                                            <button type="button" wire:click="editRfqItemQuantity({{ $item->id }})" class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">Editar cantidad</button>
                                        @endif
                                        <button type="button" wire:click="$set('selectedRfqItemIds', array_diff($selectedRfqItemIds, [{{ $item->id }}]))" class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-lg text-rose-600 hover:bg-rose-50 transition" @click.stop title="Eliminar item">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                </summary>
                                <div class="border-t border-slate-100 bg-slate-50 px-3 py-2 pl-8 lg:pl-[42px]">
                                    <label class="block text-xs font-semibold  text-slate-400">Comentario / especificaciones para este ítem</label>
                                    <textarea wire:model="rfqItemSpecifications.{{ $item->id }}" rows="2" class="mt-1 min-h-14 w-full resize-y rounded-lg border-slate-200 px-2.5 py-2 text-xs" placeholder="Escribe especificaciones, equivalencias o comentarios solo si hace falta"></textarea>
                                </div>
                            </details>
                        @endforeach
                        </div>
                    </div>
                </div>

                <details class="quotation-disclosure group" open>
                    <summary class="inline-flex cursor-pointer list-none items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 shadow-xs transition hover:bg-slate-50 focus:outline-none">
                        <svg class="h-3.5 w-3.5 text-slate-500 transition-transform duration-200 group-open:rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        <span>Datos y requisitos del PDF</span>
                    </summary>
                    <div class="mt-3 grid gap-3 md:grid-cols-2">
                        <div class="md:col-span-2">
                    <div class="mb-2">
                        <h4 class="text-sm font-semibold text-slate-900">Datos del destinatario</h4>

                    </div>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <label class="lg:col-span-2"><span class="mb-1 block text-sm font-semibold  text-slate-400">Cotizar a nombre de</span><input type="text" wire:model="rfqQuoteToName" class="w-full rounded-lg border-slate-200 text-sm"></label>
                        <label class="lg:col-span-2"><span class="mb-1 block text-sm font-semibold  text-slate-400">Consignatario</span><input type="text" wire:model="rfqConsigneeName" class="w-full rounded-lg border-slate-200 text-sm"></label>
                        <label><span class="mb-1 block text-sm font-semibold  text-slate-400">Nombre</span><input type="text" wire:model="rfqConsigneeContactName" class="w-full rounded-lg border-slate-200 text-sm"></label>
                        <label><span class="mb-1 block text-sm font-semibold  text-slate-400">Número celular</span><input type="text" wire:model="rfqConsigneePhone" class="w-full rounded-lg border-slate-200 text-sm"></label>
                        <label class="lg:col-span-2"><span class="mb-1 block text-sm font-semibold  text-slate-400">Dirección 1</span><input type="text" wire:model="rfqConsigneeAddress1" class="w-full rounded-lg border-slate-200 text-sm"></label>
                        <label class="lg:col-span-2"><span class="mb-1 block text-sm font-semibold  text-slate-400">Dirección 2</span><input type="text" wire:model="rfqConsigneeAddress2" class="w-full rounded-lg border-slate-200 text-sm"></label>
                        <label><span class="mb-1 block text-sm font-semibold  text-slate-400">País</span><input type="text" wire:model="rfqConsigneeCountry" class="w-full rounded-lg border-slate-200 text-sm"></label>
                        <label><span class="mb-1 block text-sm font-semibold  text-slate-400">Ciudad</span><input type="text" wire:model="rfqConsigneeCity" class="w-full rounded-lg border-slate-200 text-sm"></label>
                        <label class="md:col-span-2"><span class="mb-1 block text-sm font-semibold  text-slate-400">Correos</span><input type="text" wire:model="rfqConsigneeEmails" class="w-full rounded-lg border-slate-200 text-sm" placeholder="compras@quantum.com.ni, operaciones@quantum.com.ni"></label>
                        <label class="sm:col-span-2"><span class="mb-1 block text-sm font-semibold  text-slate-400">Comentario consignatario</span><textarea wire:model="rfqConsigneeComment" rows="2" class="w-full rounded-lg border-slate-200 text-sm"></textarea></label>
                        <label class="sm:col-span-2"><span class="mb-1 block text-sm font-semibold  text-slate-400">Comentario urgente</span><textarea wire:model="rfqConsigneeUrgentComment" rows="2" class="w-full rounded-lg border-slate-200 text-sm"></textarea></label>
                    </div>
                </div>

                <div class="border-b border-gray-100 py-3">
                    <div class="mb-2">
                        <h4 class="text-sm font-semibold text-slate-900">Especificaciones generales</h4>

                    </div>
                    <textarea wire:model="rfqSpecifications" rows="4" class="w-full rounded-lg border-slate-200 text-sm leading-6" placeholder="Confirmar compatibilidad, alternativas originales/equivalentes, garantía mínima, medidas, accesorios incluidos..."></textarea>
                </div>

                <div class="border-b border-gray-100 py-3">
                    <div class="mb-2">
                        <h4 class="text-sm font-semibold text-slate-900">Requisitos de la respuesta</h4>

                    </div>
                    <textarea wire:model="rfqResponseRequirements" rows="4" class="w-full rounded-lg border-slate-200 text-sm leading-6"></textarea>
                </div>

                </div></details><div class="border-b border-gray-100 py-3">


                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" wire:click="generateSupplierRFQPdf" wire:loading.attr="disabled" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-black disabled:cursor-not-allowed disabled:opacity-60">
                            {{ $activeRfqDocument ? 'Actualizar PDF RFQ' : 'Generar PDF RFQ' }}
                        </button>
                        @if($activeRfqDocument)
                            <button type="button" wire:click="startNewRfqDocument" class="rounded-xl border border-slate-300 bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-200">
                                Cancelar edición
                            </button>
                        @endif

                        @if($selectedRfqDocument?->status === \App\Models\SupplierRfqDocument::STATUS_GENERATED)
                            <span class="rounded-md bg-amber-50 px-3 py-2 text-xs font-bold text-amber-700">Marca el RFQ como enviado para registrar respuestas.</span>
                            <button type="button" wire:click="markRfqSent" wire:loading.attr="disabled" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Marcar enviado al proveedor</button>
                        @elseif($selectedRfqDocument?->status === \App\Models\SupplierRfqDocument::STATUS_SENT_TO_SUPPLIER)
                            <span class="rounded-md bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-700">RFQ enviada al proveedor. Ya puedes registrar respuesta.</span>
                        @endif
                    </div>
                </div>

                <div class="overflow-visible rounded-xl border border-slate-200 bg-white">
                    <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50 px-5 py-3">
                        <div>
                            <h4 class="text-sm font-semibold text-slate-900">RFQ generados</h4>

                        </div>
                        <button type="button" wire:click="startNewRfqDocument" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                            <svg class="h-3.5 w-3.5 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                            Nuevo RFQ
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50/80 text-sm font-semibold text-slate-500">
                                <tr>
                                    <th class="px-5 py-3.5">Proveedor / RFQ No.</th>
                                    <th class="px-5 py-3.5">Estado / Detalles</th>
                                    <th class="px-5 py-3.5 text-center whitespace-nowrap">Documentos</th>
                                    <th class="px-5 py-3.5 text-right whitespace-nowrap">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                @forelse($selectedQuote->supplierRfqDocuments as $rfqDocument)
                                    @php
                                        $itemCount = count($rfqDocument->selected_item_ids ?: []);
                                        $totalItems = $selectedQuote->items->count();
                                        $isEditing = $editingRfqDocumentId === $rfqDocument->id;
                                    @endphp
                                    <tr class="transition {{ $isEditing ? 'bg-amber-50/70 border-l-4 border-l-amber-500' : 'hover:bg-slate-50/50' }}">
                                        <td class="px-5 py-3 align-middle">
                                            <div class="flex items-center gap-2">
                                                <p class="font-semibold text-slate-900">{{ $rfqDocument->supplier?->name ?? 'RFQ abierto / sin proveedor' }}</p>
                                                @if($isEditing)
                                                    <span class="rounded bg-amber-200 px-1.5 py-0.5 text-xs font-semibold text-amber-800">Editando</span>
                                                @endif
                                            </div>
                                            <p class="mt-0.5 text-sm font-bold text-slate-500">
                                                {{ $rfqDocument->effective_rfq_number }} · <span class="text-slate-600">{{ $itemCount ? "{$itemCount} de {$totalItems} ítems" : "Todos los ítems ({$totalItems})" }}</span>
                                            </p>
                                        </td>
                                        <td class="px-5 py-3 align-middle">
                                            <div class="flex flex-col items-start gap-1">
                                                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wide text-slate-600">{{ $rfqDocument->status_label }}</span>
                                                <span class="text-sm font-medium text-slate-400">{{ optional($rfqDocument->generated_at)->format('d/m/Y H:i') ?: 'Sin fecha' }} · {{ $rfqDocument->generator?->name ?: 'N/D' }}</span>
                                            </div>
                                        </td>
                                        <td class="px-5 py-3 text-center align-middle whitespace-nowrap">
                                            @if($rfqDocument->pdf_path)
                                                <div class="inline-flex items-center justify-center gap-1.5 sm:gap-2 rounded-xl border border-slate-200 bg-white p-1 shadow-sm mx-auto">
                                                    <a href="{{ route('sales.rfq-documents.pdf', ['document' => $rfqDocument, 'lang' => 'es']) }}" target="_blank" class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-sm font-semibold text-slate-700 hover:bg-slate-50" title="Ver PDF Español">
                                                        <svg class="h-3.5 w-3.5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z" /><circle cx="12" cy="12" r="3" /></svg>
                                                        ES
                                                    </a>
                                                    <a href="{{ route('sales.rfq-documents.pdf', ['document' => $rfqDocument, 'lang' => 'en']) }}" target="_blank" class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-sm font-semibold text-slate-700 hover:bg-slate-50" title="Ver PDF Inglés">
                                                        <svg class="h-3.5 w-3.5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7Z" /><circle cx="12" cy="12" r="3" /></svg>
                                                        EN
                                                    </a>
                                                    <div class="h-4 w-px bg-slate-200"></div>
                                                    <a href="{{ route('sales.rfq-documents.pdf', ['document' => $rfqDocument, 'lang' => 'es', 'download' => 1]) }}" target="_blank" class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-sm font-semibold text-slate-700 hover:bg-slate-50" title="Descargar PDF Español">
                                                        <svg class="h-3.5 w-3.5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><polyline points="7 10 12 15 17 10" /><line x1="12" x2="12" y1="15" y2="3" /></svg>
                                                        ES
                                                    </a>
                                                    <a href="{{ route('sales.rfq-documents.pdf', ['document' => $rfqDocument, 'lang' => 'en', 'download' => 1]) }}" target="_blank" class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-sm font-semibold text-slate-700 hover:bg-slate-50" title="Descargar PDF Inglés">
                                                        <svg class="h-3.5 w-3.5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><polyline points="7 10 12 15 17 10" /><line x1="12" x2="12" y1="15" y2="3" /></svg>
                                                        EN
                                                    </a>
                                                </div>
                                            @else
                                                <span class="inline-flex items-center justify-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-wide text-slate-400">No generado</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3 align-middle text-right whitespace-nowrap">
                                            <div class="flex items-center justify-end gap-1.5">
                                                <button type="button" wire:click="editSupplierRfqDocument({{ $rfqDocument->id }})" title="Editar datos" aria-label="Editar datos" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:bg-slate-50 hover:text-slate-900">
                                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9" /><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z" /></svg>
                                                </button>
                                                <button type="button" wire:click="regenerateSupplierRfqDocument({{ $rfqDocument->id }})" wire:loading.attr="disabled" title="Regenerar PDF" aria-label="Regenerar PDF" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:bg-slate-50 hover:text-slate-900 disabled:opacity-50">
                                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 1-15.3 6.4" /><path d="M3 12A9 9 0 0 1 18.3 5.6" /><path d="M18 2v4h-4" /><path d="M6 22v-4h4" /></svg>
                                                </button>
                                                @if($rfqDocument->status === \App\Models\SupplierRfqDocument::STATUS_GENERATED)
                                                    <button type="button" wire:click="markRfqDocumentSent({{ $rfqDocument->id }})" wire:loading.attr="disabled" title="Marcar enviado" aria-label="Marcar enviado" class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-600 text-white shadow-sm transition hover:bg-emerald-700 disabled:opacity-50">
                                                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
                                                    </button>
                                                @endif
                                                <button type="button" wire:click="deleteSupplierRfqDocument({{ $rfqDocument->id }})" wire:confirm="¿Eliminar este RFQ? Esta acción quitará el PDF y permitirá volver a configurarlo o crearlo." wire:loading.attr="disabled" title="Eliminar RFQ" aria-label="Eliminar RFQ" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-rose-200 bg-white text-rose-600 shadow-sm transition hover:bg-rose-50 hover:text-rose-700 disabled:opacity-50">
                                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18" /><path d="M8 6V4h8v2" /><path d="M19 6l-1 14H6L5 6" /><path d="M10 11v5" /><path d="M14 11v5" /></svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-5 py-8 text-center">
                                            <p class="text-xs font-bold text-slate-400">Aún no hay RFQ por proveedor.</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
