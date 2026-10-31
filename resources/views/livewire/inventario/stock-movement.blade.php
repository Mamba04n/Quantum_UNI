<div class="p-6 font-sans sm:p-8" x-data="{ showForm: {{ $errors->any() ? 'true' : 'false' }}, createProductInline: {{ ($errors->has('new_product_name') || $errors->has('new_product_type')) ? 'true' : 'false' }} }">
    {{-- Encabezado principal --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-gray-900">Kardex y movimientos de inventario</h1>
            <p class="mt-0.5 text-sm text-gray-500">Historial de trazabilidad, ajustes de inventario y registro de movimientos manuales.</p>
        </div>

        <div class="flex items-center gap-3">
            <button
                type="button"
                x-on:click="showForm = !showForm"
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-slate-900"
            >
                <svg x-show="!showForm" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <svg x-cloak x-show="showForm" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
                <span x-text="showForm ? 'Cerrar registro' : 'Registrar movimiento / ajuste'">Registrar movimiento / ajuste</span>
            </button>
        </div>
    </div>

    {{-- Notificaciones flash --}}
    @if (session()->has('message'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 shadow-xs">
            {{ session('message') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800 shadow-xs">
            {{ session('error') }}
        </div>
    @endif


    {{-- Panel de registro de movimiento / ajuste desplegable --}}
    <div x-cloak x-show="showForm" x-transition.duration.200ms class="mb-6 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
        <div class="border-b border-gray-100 bg-gray-50/60 px-6 py-4 flex items-center justify-between">
            <div>
                <h2 class="text-base font-bold text-gray-900">Registrar ajuste o movimiento manual</h2>
                <p class="text-xs text-gray-500">Seleccione el producto y complete los datos del movimiento. Las entradas y salidas requieren comprobante/foto.</p>
            </div>
            <button type="button" x-on:click="showForm = false" class="rounded-xl border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 transition-colors hover:bg-gray-50">
                Cancelar
            </button>
        </div>

        <form wire:submit.prevent="processMovement" class="p-6 space-y-6">
            {{-- Sección 1: Selección del producto --}}
            <div class="space-y-3">
                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500">1. Identificación del producto</h3>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700">Producto existente en catálogo</label>
                        <select wire:model.live="product_id" class="mt-1 block w-full rounded-xl border-gray-200 text-sm focus:border-slate-800 focus:ring-slate-800">
                            <option value="">Seleccione un producto o busque por SKU...</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}">{{ $product->name }} | Stock: {{ $product->stock_on_hand }} | Costo: ${{ number_format((float) $product->cost, 2) }}</option>
                            @endforeach
                        </select>
                        @error('product_id') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700">Buscar por SKU interno</label>
                        <input type="text" wire:model.blur="product_sku" class="mt-1 block w-full rounded-xl border-gray-200 text-sm font-mono uppercase focus:border-slate-800 focus:ring-slate-800" placeholder="Ej: QS-VOL-ELE-0001">
                        @error('product_sku') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Tarjeta de producto seleccionado --}}
                @if($selectedProduct)
                    <div class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="min-w-0">
                                <span class="inline-flex rounded-md bg-emerald-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-emerald-800">Producto seleccionado</span>
                                <h4 class="mt-1 text-sm font-bold text-gray-900">{{ $selectedProduct->name }}</h4>
                                <p class="text-xs font-mono text-gray-500">SKU: {{ $selectedProduct->sku }} | Costo promedio: ${{ number_format((float) $selectedProduct->cost, 2) }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Stock actual</p>
                                <p class="text-lg font-black text-emerald-700 font-mono">{{ $selectedProduct->stock_on_hand }} unid.</p>
                            </div>
                        </div>
                    </div>
                @elseif($type === 'in')
                    {{-- Toggle para alta de producto nuevo si no existe --}}
                    <div class="rounded-xl border border-dashed border-gray-200 bg-gray-50/60 p-4">
                        <label class="inline-flex items-center gap-2 cursor-pointer text-xs font-semibold text-slate-800">
                            <input type="checkbox" x-model="createProductInline" class="rounded border-gray-300 text-slate-800 focus:ring-slate-800">
                            <span>¿El producto no está en el catálogo? <strong>Dar de alta nuevo producto desde esta entrada</strong></span>
                        </label>

                        <div x-cloak x-show="createProductInline" x-transition.duration.200ms class="mt-4 space-y-4 border-t border-gray-200/60 pt-4">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                <div class="sm:col-span-2 lg:col-span-2">
                                    <label class="block text-xs font-semibold text-gray-700">Nombre del producto nuevo</label>
                                    <input type="text" wire:model="new_product_name" class="mt-1 block w-full rounded-xl border-gray-200 text-sm focus:border-slate-800 focus:ring-slate-800" placeholder="Ej: Bomba hidráulica principal">
                                    @error('new_product_name') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-gray-700">Marca</label>
                                    <input type="text" wire:model="new_product_brand" class="mt-1 block w-full rounded-xl border-gray-200 text-sm uppercase focus:border-slate-800 focus:ring-slate-800" placeholder="Ej: VOLVO">
                                    @error('new_product_brand') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-gray-700">Número de parte</label>
                                    <input type="text" wire:model="new_product_part_number" class="mt-1 block w-full rounded-xl border-gray-200 text-sm font-mono focus:border-slate-800 focus:ring-slate-800" placeholder="PN fabricante">
                                    @error('new_product_part_number') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-gray-700">Reemplazo 1</label>
                                    <input type="text" wire:model="new_product_part_number_alt_1" class="mt-1 block w-full rounded-xl border-gray-200 text-sm font-mono focus:border-slate-800 focus:ring-slate-800" placeholder="PN alternativo">
                                    @error('new_product_part_number_alt_1') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-gray-700">Reemplazo 2</label>
                                    <input type="text" wire:model="new_product_part_number_alt_2" class="mt-1 block w-full rounded-xl border-gray-200 text-sm font-mono focus:border-slate-800 focus:ring-slate-800" placeholder="PN alternativo">
                                    @error('new_product_part_number_alt_2') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-gray-700">Tipo de producto</label>
                                    <select wire:model.live="new_product_type" class="mt-1 block w-full rounded-xl border-gray-200 text-sm focus:border-slate-800 focus:ring-slate-800">
                                        @foreach($productTypes as $productType)
                                            <option value="{{ $productType->value }}">{{ $productType->label() }}</option>
                                        @endforeach
                                    </select>
                                    @error('new_product_type') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-gray-700">Precio venta sugerido</label>
                                    <input type="number" step="0.01" min="0" wire:model="new_product_sales_price" class="mt-1 block w-full rounded-xl border-gray-200 text-sm focus:border-slate-800 focus:ring-slate-800" placeholder="0.00">
                                    @error('new_product_sales_price') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                                </div>

                                @if($new_product_type === 'pieza')
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700">Rotación</label>
                                        <select wire:model="new_product_piece_rotation_type" class="mt-1 block w-full rounded-xl border-gray-200 text-sm focus:border-slate-800 focus:ring-slate-800">
                                            @foreach($pieceRotationTypes as $rotationType)
                                                <option value="{{ $rotationType->value }}">{{ $rotationType->label() }}</option>
                                            @endforeach
                                        </select>
                                        @error('new_product_piece_rotation_type') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                                    </div>

                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700">Categoría técnica</label>
                                        <select wire:model="new_product_technical_category" class="mt-1 block w-full rounded-xl border-gray-200 text-sm focus:border-slate-800 focus:ring-slate-800">
                                            @foreach($technicalCategories as $technicalCategory)
                                                <option value="{{ $technicalCategory->value }}">{{ $technicalCategory->label() }}</option>
                                            @endforeach
                                        </select>
                                        @error('new_product_technical_category') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                                    </div>
                                @endif

                                <div class="sm:col-span-2 lg:col-span-3">
                                    <label class="block text-xs font-semibold text-gray-700">Descripción / especificaciones</label>
                                    <textarea wire:model="new_product_description" rows="2" class="mt-1 block w-full rounded-xl border-gray-200 text-sm focus:border-slate-800 focus:ring-slate-800" placeholder="Compatibilidad de equipo, especificaciones técnicas..."></textarea>
                                    @error('new_product_description') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="rounded-xl border border-amber-100 bg-amber-50/60 p-3 text-xs text-amber-800 font-medium">
                        Para registrar una salida, debe seleccionar un producto existente en catálogo o buscar por su SKU interno.
                    </div>
                @endif
            </div>

            {{-- Sección 2: Parámetros del movimiento --}}
            <div class="space-y-3 border-t border-gray-100 pt-4">
                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500">2. Datos de la operación</h3>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700">Tipo de movimiento</label>
                        <select wire:model.live="type" class="mt-1 block w-full rounded-xl border-gray-200 text-sm focus:border-slate-800 focus:ring-slate-800">
                            @foreach($movementTypes as $movementType)
                                <option value="{{ $movementType->value }}">{{ $movementType->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700">Fecha del movimiento</label>
                        <input type="date" wire:model="movement_date" class="mt-1 block w-full rounded-xl border-gray-200 text-sm focus:border-slate-800 focus:ring-slate-800">
                        @error('movement_date') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700">Cantidad</label>
                        <input type="number" step="1" min="1" wire:model="quantity" inputmode="numeric" class="mt-1 block w-full rounded-xl border-gray-200 text-sm font-mono focus:border-slate-800 focus:ring-slate-800">
                        @error('quantity') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700">{{ $type === 'in' ? 'Costo unitario entrada' : 'Costo unitario salida' }}</label>
                        @if($type === 'in')
                            <input type="number" step="0.01" min="0" wire:model="cost_in" class="mt-1 block w-full rounded-xl border-gray-200 text-sm font-mono focus:border-slate-800 focus:ring-slate-800">
                            @error('cost_in') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        @else
                            <input type="number" step="0.01" min="0" wire:model="cost_out" class="mt-1 block w-full rounded-xl border-gray-200 text-sm font-mono focus:border-slate-800 focus:ring-slate-800" placeholder="Usa promedio si queda vacío">
                            @error('cost_out') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        @endif
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700">Referencia interna</label>
                        <input type="text" wire:model="reference" class="mt-1 block w-full rounded-xl border-gray-200 text-sm focus:border-slate-800 focus:ring-slate-800" placeholder="Ej: Conteo físico 2026">
                        @error('reference') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            {{-- Sección 3: Evidencia fotográfica / archivos --}}
            <div class="space-y-3 border-t border-gray-100 pt-4">
                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500">3. Evidencia y comprobantes</h3>
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700">Archivos y fotografías del movimiento</label>
                        <input
                            type="file"
                            wire:model="attachments"
                            multiple
                            accept="image/*,.pdf,.xlsx,.xls,.csv,.doc,.docx"
                            class="mt-1 block w-full text-xs text-gray-600 file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-slate-100 file:text-slate-800 hover:file:bg-slate-200"
                        >
                        <div wire:loading wire:target="attachments" class="mt-2 text-xs font-semibold text-slate-600">Subiendo archivos...</div>
                        <p class="mt-1 text-[11px] text-gray-500">Obligatorio adjuntar al menos una foto o comprobante del estado físico de las piezas.</p>
                        @error('attachments') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        @error('attachments.*') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>

                    @if(count($attachments))
                        <div class="flex flex-wrap gap-2 rounded-xl border border-gray-100 bg-gray-50 p-3 items-center">
                            @foreach($attachments as $attachment)
                                <div class="flex items-center gap-2 rounded-lg bg-white px-2.5 py-1.5 shadow-2xs border border-gray-100">
                                    @if(str_starts_with((string) $attachment->getMimeType(), 'image/'))
                                        <img src="{{ $attachment->temporaryUrl() }}" alt="Preview" class="h-8 w-8 rounded-lg object-cover border border-gray-100">
                                    @else
                                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-[10px] font-bold text-slate-600">{{ strtoupper($attachment->getClientOriginalExtension() ?: 'FILE') }}</span>
                                    @endif
                                    <span class="max-w-[140px] truncate text-xs font-medium text-gray-700">{{ $attachment->getClientOriginalName() }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- Sección 4: Botones de acción --}}
            <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-4">
                <button
                    type="button"
                    x-on:click="showForm = false"
                    class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-50"
                >
                    Cancelar
                </button>

                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="attachments,processMovement"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-800 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-slate-900 disabled:opacity-50"
                >
                    <span wire:loading.remove wire:target="processMovement">Procesar Movimiento</span>
                    <span wire:loading wire:target="processMovement">Procesando...</span>
                </button>
            </div>
        </form>
    </div>

    {{-- Tabla canónica de Historial Kardex --}}
    <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
        {{-- Barra de herramientas de la tabla --}}
        <div class="flex flex-col gap-3 border-b border-gray-100 bg-white px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-2.5">
                <h2 class="text-base font-bold text-gray-900">Historial Kardex</h2>
                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600">
                    {{ $recentMoves->count() }} movimientos
                </span>
            </div>

            <div class="flex items-center gap-3">
                <label for="filter_product_id" class="sr-only">Filtrar por producto</label>
                <select id="filter_product_id" wire:model.live="filter_product_id" class="rounded-xl border-gray-200 text-sm focus:border-slate-800 focus:ring-slate-800">
                    <option value="">Todos los productos</option>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}">{{ $product->name }} | {{ $product->sku }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Tabla con scroll horizontal controlado --}}
        <div class="overflow-x-auto">
            <table class="w-full whitespace-nowrap text-left text-sm">
                <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Fecha</th>
                        <th class="px-5 py-3 font-semibold">Producto</th>
                        <th class="px-5 py-3 font-semibold">Tipo</th>
                        <th class="px-5 py-3 text-right font-semibold">Cantidad</th>
                        <th class="px-5 py-3 text-right font-semibold">Saldo</th>
                        <th class="px-5 py-3 text-right font-semibold">Costo Unit.</th>
                        <th class="px-5 py-3 text-right font-semibold">Total</th>
                        <th class="px-5 py-3 font-semibold">Referencia</th>
                        <th class="px-5 py-3 text-center font-semibold">Evidencia</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 text-gray-700">
                    @forelse($recentMoves as $move)
                        <tr class="transition-colors hover:bg-gray-50/50">
                            <td class="px-5 py-3 font-medium text-gray-900">
                                {{ optional($move->movement_date ?? $move->created_at)->format('d/m/Y') }}
                            </td>
                            <td class="px-5 py-3">
                                <div class="font-semibold text-gray-900">{{ $move->product->name ?? 'Producto no disponible' }}</div>
                                @if($move->product?->sku)
                                    <div class="text-xs font-mono text-gray-400">{{ $move->product->sku }}</div>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                @php
                                    $isEntry = (($move->movement_type?->value ?? ($move->quantity > 0 ? 'in' : 'out')) === 'in');
                                @endphp
                                @if($isEntry)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 border border-emerald-100">
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                                        </svg>
                                        {{ $move->movement_type?->label() ?? 'Entrada' }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-semibold text-rose-700 border border-rose-100">
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
                                        </svg>
                                        {{ $move->movement_type?->label() ?? 'Salida' }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right font-mono font-semibold {{ $isEntry ? 'text-emerald-700' : 'text-rose-700' }}">
                                {{ $isEntry ? '+' : '-' }}{{ abs($move->quantity) }}
                            </td>
                            <td class="px-5 py-3 text-right font-mono font-bold text-gray-900">
                                {{ $move->balance_after }}
                            </td>
                            <td class="px-5 py-3 text-right font-mono text-gray-600">
                                ${{ number_format((float) $move->unit_cost, 2) }}
                            </td>
                            <td class="px-5 py-3 text-right font-mono font-semibold text-gray-900">
                                ${{ number_format((float) $move->total_cost_after, 2) }}
                            </td>
                            <td class="px-5 py-3 text-xs text-gray-500 max-w-[200px] truncate" title="{{ $move->reference }}">
                                {{ $move->reference ?: '—' }}
                            </td>
                            <td class="px-5 py-3 text-center">
                                @if($move->attachments->isNotEmpty())
                                    <div class="flex flex-wrap justify-center gap-1">
                                        @foreach($move->attachments as $attachment)
                                            @php($isImage = str_starts_with((string) $attachment->mime_type, 'image/'))
                                            <a href="{{ route('private.stock-move-attachment-file', $attachment) }}" target="_blank" title="{{ $attachment->file_name }}" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 transition-colors hover:border-slate-300 hover:bg-slate-100">
                                                @if($isImage)
                                                    <img src="{{ route('private.stock-move-attachment-file', $attachment) }}" alt="Evidencia" class="h-7 w-7 rounded object-cover">
                                                @else
                                                    <span class="text-[9px] font-bold text-slate-700">{{ strtoupper(pathinfo($attachment->file_name, PATHINFO_EXTENSION) ?: 'DOC') }}</span>
                                                @endif
                                            </a>
                                        @endforeach
                                    </div>
                                @elseif($move->attachment_path)
                                    <a href="{{ route('private.stock-move-attachment', $move) }}" target="_blank" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-700 hover:text-slate-900">
                                        <img src="{{ route('private.stock-move-attachment', $move) }}" alt="Evidencia" class="h-7 w-7 rounded-lg object-cover border border-slate-200">
                                        <span>Ver</span>
                                    </a>
                                @else
                                    <span class="text-xs text-gray-300">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center text-sm text-gray-500">
                                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-gray-50 text-gray-400 mb-3">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                    </svg>
                                </div>
                                <p class="font-semibold text-gray-800">No hay movimientos registrados</p>
                                <p class="mt-1 text-xs text-gray-400">Los movimientos de recepción y despacho aparecerán aquí automáticamente al completarse.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
