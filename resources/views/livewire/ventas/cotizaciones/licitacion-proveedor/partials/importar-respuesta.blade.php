        @if($showSupplierBidBulkModal)
            <div
                class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 backdrop-blur-sm"
                style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 99999; background-color: rgba(15, 23, 42, 0.75); display: flex; align-items: center; justify-content: center; overflow-y: auto;"
                x-data
                x-on:keydown.escape.window="$wire.closeSupplierBidBulkImport()"
            >
                <div
                    class="relative w-full max-w-5xl rounded-2xl border border-slate-200 bg-white shadow-2xl overflow-hidden"
                    style="max-height: 88vh; height: 88vh; display: flex; flex-direction: column; width: 100%; max-width: 64rem; margin: auto;"
                >
                    {{-- Header Fijo --}}
                    <div
                        class="flex shrink-0 items-center justify-between border-b border-slate-100 bg-slate-50/90 px-6 py-4"
                        style="flex-shrink: 0;"
                    >
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-900 text-white shadow-sm">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-sm font-black text-slate-900">Vista previa de respuesta masiva (Excel)</h3>
                                <p class="text-xs font-medium text-slate-500">
                                    Proveedor: <span class="font-bold text-slate-700">{{ $selectedRfqDocument?->supplier?->name ?: 'Proveedor seleccionado' }}</span> &middot; {{ count($supplierBidBulkPreview) }} partida(s) detectadas
                                </p>
                            </div>
                        </div>
                        <button type="button" wire:click="closeSupplierBidBulkImport" class="rounded-xl p-2 text-slate-400 hover:bg-slate-200/70 hover:text-slate-700 transition" aria-label="Cerrar">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    {{-- Body con Scroll Interno --}}
                    <div
                        class="flex-1 overflow-y-auto p-6 space-y-4 text-xs"
                        style="flex: 1 1 auto; overflow-y: auto; min-height: 0;"
                    >
                        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead class="sticky top-0 z-10 border-b border-slate-200 bg-slate-100 text-[10px] font-black uppercase tracking-wider text-slate-600">
                                    <tr>
                                        <th class="px-3.5 py-2.5 text-center w-12 bg-slate-100">#</th>
                                        <th class="px-3.5 py-2.5 w-24 bg-slate-100">Estado</th>
                                        <th class="px-3.5 py-2.5 w-36 bg-slate-100">Código cotizado</th>
                                        <th class="px-3.5 py-2.5 bg-slate-100">Descripción</th>
                                        <th class="px-3.5 py-2.5 text-right w-20 bg-slate-100">Cant.</th>
                                        <th class="px-3.5 py-2.5 text-right w-28 bg-slate-100">Precio Unit.</th>
                                        <th class="px-3.5 py-2.5 w-28 bg-slate-100">Entrega</th>
                                        <th class="px-3.5 py-2.5 w-28 bg-slate-100">Garantía</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white text-slate-700">
                                    @foreach($supplierBidBulkPreview as $row)
                                        <tr class="hover:bg-slate-50/80 transition-colors">
                                            <td class="px-3.5 py-2 text-center font-mono text-slate-400 font-bold">{{ $loop->iteration }}</td>
                                            <td class="px-3.5 py-2">
                                                @if(($row['item_status'] ?? '') === 'quoted')
                                                    <span class="inline-flex rounded-md bg-emerald-50 px-2 py-0.5 text-[10px] font-black text-emerald-700 border border-emerald-200">COTIZADO</span>
                                                @elseif(($row['item_status'] ?? '') === 'alternative')
                                                    <span class="inline-flex rounded-md bg-blue-50 px-2 py-0.5 text-[10px] font-black text-blue-700 border border-blue-200">ALTERNATIVA</span>
                                                @else
                                                    <span class="inline-flex rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-black text-slate-600 border border-slate-200">NO COTIZADO</span>
                                                @endif
                                            </td>
                                            <td class="px-3.5 py-2 font-mono font-bold text-slate-900">{{ $row['quoted_part_number'] ?: '—' }}</td>
                                            <td class="px-3.5 py-2 font-medium text-slate-700">{{ $row['quoted_description'] ?: '—' }}</td>
                                            <td class="px-3.5 py-2 text-right font-bold text-slate-900">{{ $row['quoted_quantity'] ?: '—' }}</td>
                                            <td class="px-3.5 py-2 text-right font-mono font-black text-slate-900">
                                                {{ $row['unit_cost'] !== '' ? number_format((float) $row['unit_cost'], 2) : '—' }}
                                            </td>
                                            <td class="px-3.5 py-2 text-slate-600">{{ $row['delivery_time'] ?: ($row['estimated_arrival_date'] ?: '—') }}</td>
                                            <td class="px-3.5 py-2 text-slate-600">{{ $row['warranty'] ?: '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if(count($supplierBidBulkMissingItems))
                            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <h4 class="text-xs font-black text-amber-900 flex items-center gap-1.5">
                                            <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                            {{ count($supplierBidBulkMissingItems) }} partida(s) no aparecen en el archivo Excel
                                        </h4>
                                        <p class="mt-1 text-[11px] font-medium text-amber-800">Puedes conservarlas sin cambios o marcarlas automáticamente como no disponibles para este proveedor.</p>
                                    </div>
                                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-amber-300 bg-white px-3 py-2 text-[11px] font-black text-amber-900 shadow-xs hover:bg-amber-50/50 transition shrink-0">
                                        <input type="checkbox" wire:model="supplierBidMarkMissingUnavailable" class="rounded border-amber-300 text-amber-700 focus:ring-amber-600">
                                        Marcar ausentes como no disponibles
                                    </label>
                                </div>
                                <div class="mt-3 flex max-h-24 flex-wrap gap-1.5 overflow-y-auto">
                                    @foreach($supplierBidBulkMissingItems as $missing)
                                        <span class="rounded-md border border-amber-200 bg-white px-2 py-1 text-[10px] font-bold text-amber-900 shadow-2xs" title="{{ $missing['description'] }}">{{ $missing['part_number'] }}</span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Footer Fijo --}}
                    <div
                        class="flex shrink-0 flex-col sm:flex-row items-center justify-between gap-3 border-t border-slate-100 bg-slate-50/90 px-6 py-4"
                        style="flex-shrink: 0;"
                    >
                        <div class="flex items-center gap-2 text-xs font-bold text-slate-500">
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-slate-200 text-slate-700 text-[10px] font-black">
                                {{ count($supplierBidBulkPreview) }}
                            </span>
                            partida(s) listas para importar
                        </div>
                        <div class="flex w-full sm:w-auto items-center justify-end gap-2">
                            <button type="button" wire:click="closeSupplierBidBulkImport" class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 text-xs font-bold text-slate-700 hover:bg-slate-100 transition shadow-xs">
                                Cancelar
                            </button>
                            <button type="button" wire:click="applySupplierBidBulkImport" wire:loading.attr="disabled" class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 text-xs font-bold text-slate-700 hover:bg-slate-100 transition shadow-xs">
                                Solo cargar a borrador
                            </button>
                            <button type="button" wire:click="saveSupplierBidBulkImport" wire:loading.attr="disabled" wire:target="saveSupplierBidBulkImport" class="inline-flex h-10 items-center gap-2 justify-center rounded-xl bg-slate-900 px-5 text-xs font-black text-white hover:bg-black transition shadow-sm disabled:opacity-50">
                                <span wire:loading.remove wire:target="saveSupplierBidBulkImport" class="flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    Cargar y Guardar respuesta
                                </span>
                                <span wire:loading wire:target="saveSupplierBidBulkImport" class="flex items-center gap-1.5">
                                    <svg class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                    Guardando...
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
