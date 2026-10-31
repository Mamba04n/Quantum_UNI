<div class="p-6 font-sans sm:p-8">
    <!-- Encabezado de página -->
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-gray-900">Nueva Orden de Compra</h1>
            <p class="mt-0.5 text-sm text-gray-500">Crea una orden de compra directa y regístrala como cuenta por pagar.</p>
        </div>
        <div>
            <a href="{{ route('workspace', 'compras') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                Volver a Compras
            </a>
        </div>
    </div>

    <form wire:submit.prevent="save">
        @if(session('message'))
            <div class="mb-6 bg-emerald-50 text-emerald-800 p-4 rounded-xl border border-emerald-200 flex items-center gap-3">
                <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span class="font-medium text-sm">{{ session('message') }}</span>
            </div>
        @endif

        @if(session('error'))
            <div class="mb-6 bg-rose-50 text-rose-800 p-4 rounded-xl border border-rose-200 flex items-center gap-3">
                <svg class="w-5 h-5 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span class="font-medium text-sm">{{ session('error') }}</span>
            </div>
        @endif

        <div class="mb-6">
            <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-[11px] font-black uppercase tracking-widest text-gray-400">Información de la Orden</h4>
                    <span class="inline-flex items-center gap-1.5 rounded-lg bg-gray-50 px-3 py-1.5 text-xs font-semibold text-gray-600 ring-1 ring-inset ring-gray-200">
                        {{ $reference_number }}
                    </span>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="md:col-span-2">
                        <div class="mb-1 flex items-center justify-between gap-3">
                            <label class="block text-xs font-bold text-gray-600">Proveedor <span class="text-rose-500">*</span></label>
                            <button type="button" wire:click="openQuickSupplierModal" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 transition-colors hover:text-slate-900">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                Nuevo proveedor
                            </button>
                        </div>
                        <select wire:model="supplier_id" class="w-full bg-gray-50 border-transparent rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all">
                            <option value="">-- Seleccionar Proveedor --</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                            @endforeach
                        </select>
                        @error('supplier_id') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1">Moneda <span class="text-rose-500">*</span></label>
                        <select wire:model.change="currency" class="w-full bg-gray-50 border-transparent rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all">
                            <option value="USD">Dólares (USD)</option>
                            <option value="NIO">Córdobas (NIO)</option>
                        </select>
                        @error('currency') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1">Fecha de Orden</label>
                        <input type="date" wire:model="order_date" class="w-full bg-gray-50 border-transparent rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1">Fecha Esperada</label>
                        <input type="date" wire:model="expected_date" class="w-full bg-gray-50 border-transparent rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all">
                    </div>
                </div>
            </section>
        </div>

        <!-- Tabla de Productos -->
        <div class="mb-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between pt-4">
            <h4 class="text-[12px] font-black uppercase tracking-widest text-slate-500">Productos Solicitados</h4>
            
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" wire:click="downloadTemplate" wire:loading.attr="disabled" wire:target="downloadTemplate" class="inline-flex items-center justify-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50/70 px-3.5 py-2 text-xs font-bold text-emerald-800 shadow-sm transition-colors hover:border-emerald-300 hover:bg-emerald-100 disabled:cursor-wait disabled:opacity-60">
                    <svg class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                    <span wire:loading.remove wire:target="downloadTemplate">Descargar plantilla Excel (.xlsx)</span>
                    <span wire:loading wire:target="downloadTemplate">Preparando plantilla...</span>
                </button>
                <button type="button" wire:click="openBulkModal" class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 transition-colors">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Carga Masiva (Excel / Texto)
                </button>
                <button type="button" wire:click="addItem" class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#0f172a] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    Agregar producto
                </button>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm mb-6">
            <div class="overflow-x-auto">
                <table class="w-full whitespace-nowrap text-left text-sm">
                    <thead class="border-b border-gray-100 bg-gray-50 text-[10px] font-black uppercase tracking-widest text-gray-400">
                        <tr>
                            <th class="px-5 py-3 w-1/4">Catálogo</th>
                            <th class="px-5 py-3 w-1/3">Nombre Libre (Consumibles)</th>
                            <th class="px-5 py-3 w-28 text-right">Cantidad</th>
                            <th class="px-5 py-3 w-36 text-right">C. Unitario</th>
                            <th class="px-5 py-3 w-36 text-right">Subtotal</th>
                            <th class="px-5 py-3 w-12 text-center"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($items as $index => $item)
                            <tr class="transition-colors hover:bg-gray-50/50">
                                <td class="px-3 py-2">
                                    <select wire:model="items.{{$index}}.product_id" class="w-full bg-gray-50 border-transparent rounded-lg py-2 px-3 text-xs focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all">
                                        <option value="">-- Manual --</option>
                                        @foreach($products as $product)
                                            <option value="{{ $product->id }}">{{ $product->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-3 py-2">
                                    <input type="text" wire:model="items.{{$index}}.part_name" placeholder="Ej. Resma de papel" class="w-full bg-gray-50 border-transparent rounded-lg py-2 px-3 text-xs focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all placeholder:text-gray-400">
                                    @error('items.' . $index . '.part_name') <p class="mt-1 text-[10px] text-rose-500">{{ $message }}</p> @enderror
                                </td>
                                <td class="px-3 py-2">
                                    <input type="number" wire:model.live.debounce.300ms="items.{{$index}}.quantity" min="1" class="w-full bg-gray-50 border-transparent rounded-lg py-2 px-3 text-xs text-right focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all">
                                </td>
                                <td class="px-3 py-2 relative">
                                    <div class="pointer-events-none absolute inset-y-0 left-6 flex items-center">
                                        <span class="text-gray-400 text-xs font-semibold">{{ $currency === 'NIO' ? 'C$' : '$' }}</span>
                                    </div>
                                    <input type="number" step="0.01" min="0" wire:model.live.debounce.300ms="items.{{$index}}.unit_price" class="w-full bg-gray-50 border-transparent rounded-lg py-2 pl-9 pr-3 text-xs text-right focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all">
                                </td>
                                <td class="px-5 py-2 text-right font-medium text-gray-900 text-sm">
                                    <span class="text-gray-400 font-normal mr-1 text-xs">{{ $currency === 'NIO' ? 'C$' : '$' }}</span>{{ number_format($item['total_price'], 2) }}
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <button type="button" wire:click="removeItem({{$index}})" class="text-gray-400 hover:text-rose-500 hover:bg-rose-50 p-1.5 rounded-lg transition-colors" title="Eliminar fila">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                        @if(count($items) === 0)
                            <tr>
                                <td colspan="6" class="px-5 py-12 text-center text-gray-400 text-sm">
                                    No hay productos en la orden. Usa el botón "Agregar producto" o realiza una carga masiva.
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex justify-end mb-8">
            <div class="bg-gray-50 px-6 py-4 rounded-xl border border-gray-100 w-full md:w-1/3 shadow-sm">
                <div class="flex items-center justify-between font-bold text-gray-900">
                    <span class="text-xs uppercase tracking-widest text-gray-400">Total ({{ $currency }})</span>
                    <span class="text-xl">{{ $currency === 'NIO' ? 'C$' : '$' }} {{ number_format($total_fob, 2) }}</span>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-slate-900 disabled:opacity-60" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="save">Guardar Orden y Generar PDF</span>
                <span wire:loading wire:target="save">Procesando...</span>
            </button>
        </div>
    </form>

    @if($showQuickSupplierModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" wire:click="closeQuickSupplierModal"></div>
            <section class="relative z-10 w-full max-w-xl overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-xl" role="dialog" aria-modal="true" aria-labelledby="quick-supplier-title">
                <div class="flex items-start justify-between gap-4 border-b border-gray-100 px-5 py-4">
                    <div>
                        <h3 id="quick-supplier-title" class="text-lg font-semibold text-gray-900">Nuevo proveedor</h3>
                        <p class="mt-0.5 text-sm text-gray-500">Registra los datos esenciales para continuar con la orden.</p>
                    </div>
                    <button type="button" wire:click="closeQuickSupplierModal" class="rounded-lg p-1.5 text-gray-400 transition-colors hover:bg-gray-50 hover:text-gray-600" aria-label="Cerrar">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <form wire:submit="createQuickSupplier">
                    <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="quick-supplier-name" class="mb-1 block text-xs font-bold text-gray-600">Nombre comercial <span class="text-rose-500">*</span></label>
                            <input id="quick-supplier-name" type="text" wire:model="newSupplierName" autofocus class="w-full rounded-xl border-transparent bg-gray-50 px-3 py-2.5 text-sm transition-all focus:bg-white focus:ring-2 focus:ring-slate-200" placeholder="Nombre del proveedor">
                            @error('newSupplierName') <span class="mt-1 block text-xs text-rose-500">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label for="quick-supplier-tax-id" class="mb-1 block text-xs font-bold text-gray-600">RUC / identificación fiscal</label>
                            <input id="quick-supplier-tax-id" type="text" wire:model="newSupplierTaxId" class="w-full rounded-xl border-transparent bg-gray-50 px-3 py-2.5 text-sm transition-all focus:bg-white focus:ring-2 focus:ring-slate-200" placeholder="Opcional">
                            @error('newSupplierTaxId') <span class="mt-1 block text-xs text-rose-500">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label for="quick-supplier-contact" class="mb-1 block text-xs font-bold text-gray-600">Contacto</label>
                            <input id="quick-supplier-contact" type="text" wire:model="newSupplierContactName" class="w-full rounded-xl border-transparent bg-gray-50 px-3 py-2.5 text-sm transition-all focus:bg-white focus:ring-2 focus:ring-slate-200" placeholder="Persona de contacto">
                            @error('newSupplierContactName') <span class="mt-1 block text-xs text-rose-500">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label for="quick-supplier-phone" class="mb-1 block text-xs font-bold text-gray-600">Teléfono</label>
                            <input id="quick-supplier-phone" type="text" wire:model="newSupplierPhone" class="w-full rounded-xl border-transparent bg-gray-50 px-3 py-2.5 text-sm transition-all focus:bg-white focus:ring-2 focus:ring-slate-200" placeholder="Opcional">
                            @error('newSupplierPhone') <span class="mt-1 block text-xs text-rose-500">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label for="quick-supplier-email" class="mb-1 block text-xs font-bold text-gray-600">Correo</label>
                            <input id="quick-supplier-email" type="email" wire:model="newSupplierEmail" class="w-full rounded-xl border-transparent bg-gray-50 px-3 py-2.5 text-sm transition-all focus:bg-white focus:ring-2 focus:ring-slate-200" placeholder="correo@proveedor.com">
                            @error('newSupplierEmail') <span class="mt-1 block text-xs text-rose-500">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 border-t border-gray-100 bg-gray-50/50 px-5 py-4">
                        <button type="button" wire:click="closeQuickSupplierModal" class="inline-flex items-center justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 transition-colors hover:bg-gray-50">Cancelar</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="createQuickSupplier" class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-slate-900 disabled:cursor-wait disabled:opacity-60">
                            <span wire:loading.remove wire:target="createQuickSupplier">Crear y seleccionar</span>
                            <span wire:loading wire:target="createQuickSupplier">Creando proveedor...</span>
                        </button>
                    </div>
                </form>
            </section>
        </div>
    @endif

    <!-- Modal Carga Masiva (Diseño idéntico a la captura) -->
    @if($showBulkModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" wire:click="closeBulkModal"></div>
            <div class="relative z-10 bg-white rounded-3xl shadow-2xl w-full max-w-4xl flex flex-col max-h-[90vh] overflow-hidden">
                <!-- Header del Modal -->
                <div class="flex items-start justify-between p-6 pb-4">
                    <div class="flex gap-4">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-[#0f172a] shadow-sm">
                            <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">Carga Masiva de Partidas</h3>
                            <p class="text-sm text-gray-500 mt-1">Importa un archivo Excel (.xlsx, .csv) o pega las columnas directamente para autocompletar la orden.</p>
                        </div>
                    </div>
                    <button wire:click="closeBulkModal" class="text-gray-400 hover:text-gray-600 p-2 rounded-xl hover:bg-gray-50 transition-colors shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <!-- Tabs (Visuales simuladas según captura) -->
                <div class="px-6 border-b border-gray-100 flex items-center justify-between">
                    <div class="flex gap-6">
                        <button class="relative py-4 text-sm font-bold text-emerald-600 border-b-2 border-emerald-600 flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                            Subir Archivo Excel / CSV
                        </button>
                        <button class="relative py-4 text-sm font-medium text-gray-500 hover:text-gray-700 flex items-center gap-2 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"></path></svg>
                            Pegar Texto Copiado
                        </button>
                    </div>
                    <button wire:click="downloadTemplate" type="button" class="inline-flex items-center gap-2 text-sm font-semibold text-emerald-600 bg-emerald-50 px-4 py-2 rounded-xl hover:bg-emerald-100 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                        Descargar Plantilla Excel (.xlsx)
                    </button>
                </div>

                <!-- Contenido Modal: Zona de Carga -->
                <div class="p-8 bg-gray-50/50 overflow-y-auto">
                    @if(count($bulkPreview) === 0)
                        <!-- Dropzone -->
                        <div class="relative border-2 border-dashed border-gray-200 rounded-3xl bg-white p-12 text-center hover:border-emerald-400 hover:bg-emerald-50/30 transition-all">
                            <input type="file" wire:model="bulkFile" id="file_upload" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" accept=".xlsx,.xls,.csv">
                            
                            <div class="flex justify-center mb-4">
                                <div class="w-14 h-14 bg-white border border-gray-100 rounded-2xl flex items-center justify-center shadow-sm">
                                    <svg class="w-7 h-7 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                                </div>
                            </div>
                            
                            <div class="mb-2">
                                <button type="button" class="inline-flex items-center justify-center rounded-xl bg-[#0f172a] px-6 py-2.5 text-sm font-semibold text-white shadow-sm pointer-events-none">
                                    Seleccionar Archivo Excel
                                </button>
                            </div>
                            
                            <p class="text-sm text-gray-500 mb-1">o arrastra y suelta tu archivo aquí</p>
                            <p class="text-[11px] font-mono text-gray-400">Formatos soportados: .xlsx, .xls, .csv (Máx. 10MB)</p>

                            <div wire:loading wire:target="bulkFile" class="mt-4 text-sm font-medium text-emerald-600 animate-pulse">
                                Procesando archivo, por favor espera...
                            </div>
                            
                            @error('bulkFile')
                                <div class="mt-4 text-sm font-medium text-rose-500 bg-rose-50 py-2 px-4 rounded-xl inline-block">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                    @else
                        <!-- Vista Previa de Tabla -->
                        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
                            <div class="px-5 py-4 border-b border-gray-100 bg-emerald-50/50 flex justify-between items-center">
                                <h4 class="font-bold text-emerald-800 text-sm">Vista Previa ({{ count($bulkPreview) }} partidas listas)</h4>
                                <button wire:click="$set('bulkPreview', [])" class="text-sm font-semibold text-slate-500 hover:text-slate-700 underline">Elegir otro archivo</button>
                            </div>
                            <div class="max-h-64 overflow-y-auto">
                                <table class="w-full text-left text-sm whitespace-nowrap">
                                    <thead class="bg-white border-b border-gray-100 text-[10px] font-black uppercase tracking-widest text-gray-400 sticky top-0 shadow-sm">
                                        <tr>
                                            <th class="px-5 py-3">Producto (Nombre)</th>
                                            <th class="px-5 py-3 text-right">Cantidad</th>
                                            <th class="px-5 py-3 text-right">P. Unitario</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 text-xs">
                                        @foreach($bulkPreview as $row)
                                            <tr class="hover:bg-gray-50/50">
                                                <td class="px-5 py-2.5 font-medium text-gray-900">{{ $row['part_name'] }}</td>
                                                <td class="px-5 py-2.5 text-right">{{ $row['quantity'] }}</td>
                                                <td class="px-5 py-2.5 text-right text-gray-500">{{ number_format($row['unit_price'], 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Footer del Modal -->
                <div class="px-8 py-5 border-t border-gray-100 bg-white rounded-b-3xl flex justify-between items-center">
                    <button type="button" wire:click="closeBulkModal" class="inline-flex items-center justify-center rounded-xl bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 transition-colors">
                        Cancelar
                    </button>
                    
                    <button type="button" wire:click="applyBulkImport" @if(count($bulkPreview) === 0) disabled @endif class="inline-flex items-center justify-center gap-2 rounded-xl px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors {{ count($bulkPreview) > 0 ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-gray-400 cursor-not-allowed' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        Insertar Partidas en la Cotización
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
