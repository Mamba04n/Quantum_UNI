<div class="p-6 font-sans sm:p-8">
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-2xl font-extrabold tracking-tight text-gray-900">
                {{ $viewMode === 'form' ? ($product_id ? 'Editar Producto' : 'Nuevo Producto') : 'Inventario de Productos' }}
            </h2>
            <p class="mt-0.5 text-sm text-gray-500">Catálogo, identificación técnica, costos y existencia disponible.</p>
        </div>

        <div class="flex items-center gap-3">
            @if($viewMode === 'form')
                <button wire:click="showList" class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition-colors hover:bg-gray-50">Descartar</button>
                @if($product_id)
                    <button
                        wire:click="delete({{ $product_id }})"
                        wire:confirm="¿Retirar el producto? Si tiene historial, se desactiva; el historial se conserva."
                        class="rounded-xl border border-rose-200 bg-white px-4 py-2.5 text-sm font-semibold text-rose-700 shadow-sm transition-colors hover:bg-rose-50"
                    >
                        Eliminar o desactivar
                    </button>
                @endif
                <button wire:click="save" wire:loading.attr="disabled" wire:target="save" class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-slate-900 disabled:cursor-wait disabled:opacity-60">
                    <span wire:loading.remove wire:target="save">Guardar producto</span>
                    <span wire:loading wire:target="save">Guardando...</span>
                </button>
            @else
                <input wire:model.live.debounce.500ms="search" type="text" placeholder="Buscar producto..." class="px-4 py-2 w-64 bg-white border border-gray-200 rounded-xl text-sm shadow-sm">
                <div class="flex bg-white border border-gray-200 rounded-xl p-1 shadow-sm">
                    <button wire:click="setView('kanban')" class="px-3 py-1.5 rounded-lg text-xs font-bold {{ $viewMode === 'kanban' ? 'bg-indigo-50 text-indigo-700' : 'text-gray-500' }}">Kanban</button>
                    <button wire:click="setView('list')" class="px-3 py-1.5 rounded-lg text-xs font-bold {{ $viewMode === 'list' ? 'bg-indigo-50 text-indigo-700' : 'text-gray-500' }}">Tabla</button>
                </div>
                <button wire:click="create" class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-slate-900">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Nuevo producto
                </button>
            @endif
        </div>
    </div>

    @if(session()->has('message'))
        <div class="mb-6 p-4 text-sm text-emerald-800 rounded-xl bg-emerald-50 border border-emerald-100 shadow-sm">
            <span class="font-semibold">{{ session('message') }}</span>
        </div>
    @endif

    @if($viewMode === 'form')
        <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-5 py-4">
                <h3 class="text-base font-semibold text-gray-900">Datos del producto</h3>
                <p class="mt-0.5 text-sm text-gray-500">Complete la identificación comercial y técnica. El SKU se asigna automáticamente.</p>
            </div>

            <div class="grid lg:grid-cols-12">
                <div class="p-5 lg:col-span-8">
                    <div class="grid gap-5 md:grid-cols-12">
                        <div class="md:col-span-3">
                            <div class="flex aspect-square w-full max-w-36 items-center justify-center overflow-hidden rounded-xl border border-dashed border-gray-300 bg-gray-50 text-gray-400">
                        @php
                            $selectedProduct = $product_id ? $products->firstWhere('id', $product_id) : null;
                            $storageImageExists = $selectedProduct?->image_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($selectedProduct->image_path);
                            $imageSrc = $storageImageExists ? asset('storage/'.$selectedProduct->image_path) : null;
                        @endphp
                        @if($photo)
                            <img src="{{ $photo->temporaryUrl() }}" alt="Vista previa" class="w-full h-full object-cover">
                        @elseif($image_url)
                            <img src="{{ $image_url }}" alt="Vista previa URL" class="w-full h-full object-cover">
                        @elseif($imageSrc)
                            <img src="{{ $imageSrc }}" alt="Foto producto" class="w-full h-full object-cover">
                        @else
                            <span class="text-xs font-semibold">Sin imagen</span>
                        @endif
                    </div>

                            <div class="mt-3 space-y-2">
                        <input id="product-photo" type="file" wire:model="photo" accept="image/*" class="sr-only">
                        <label for="product-photo" class="block w-full max-w-36 cursor-pointer rounded-lg border border-gray-200 bg-white px-3 py-2 text-center text-xs font-semibold text-gray-700 transition-colors hover:bg-gray-50">Seleccionar imagen</label>
                        @error('photo') <span class="text-xs text-red-500 block">{{ $message }}</span> @enderror

                        <input type="text" wire:model.blur="image_url" class="w-full rounded-lg border-transparent bg-gray-50 px-2.5 py-2 text-xs focus:bg-white focus:ring-2 focus:ring-slate-200" placeholder="URL de imagen">
                        @error('image_url') <span class="text-xs text-red-500 block">{{ $message }}</span> @enderror

                        @if($product_id && $imageSrc)
                            <button type="button" wire:click="deleteImage" class="w-full rounded-lg px-3 py-1.5 text-xs font-semibold text-rose-700 transition-colors hover:bg-rose-50">Eliminar imagen</button>
                        @endif
                            </div>
                        </div>

                        <div class="min-w-0 md:col-span-9">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="sm:col-span-2">
                                    <label class="mb-1.5 block text-sm font-semibold text-gray-700">Nombre del producto</label>
                                    <input wire:model="name" type="text" class="w-full rounded-xl border-transparent bg-gray-50 px-3 py-2.5 text-sm transition-all focus:bg-white focus:ring-2 focus:ring-slate-200" placeholder="Ej. Filtro hidráulico">
                                    @error('name') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-semibold text-gray-700">Tipo de producto</label>
                                    <select wire:model.live="type" class="w-full rounded-xl border-transparent bg-gray-50 px-3 py-2.5 text-sm focus:bg-white focus:ring-2 focus:ring-slate-200">
                                        @foreach($productTypes as $productType)
                                            <option value="{{ $productType->value }}">{{ $productType->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="mb-1.5 block text-sm font-semibold text-gray-700">Marca</label>
                                    <input wire:model="brand" type="text" class="w-full rounded-xl border-transparent bg-gray-50 px-3 py-2.5 text-sm uppercase focus:bg-white focus:ring-2 focus:ring-slate-200" placeholder="Fabricante o marca">
                                </div>

                                @if($type === 'pieza')
                                        <div>
                                            <label class="mb-1.5 block text-sm font-semibold text-gray-700">Rotación</label>
                                            <select wire:model="piece_rotation_type" class="w-full rounded-xl border-transparent bg-gray-50 px-3 py-2.5 text-sm focus:bg-white focus:ring-2 focus:ring-slate-200">
                                                @foreach($pieceRotationTypes as $rotationType)
                                                    <option value="{{ $rotationType->value }}">{{ $rotationType->label() }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="mb-1.5 block text-sm font-semibold text-gray-700">Categoría técnica</label>
                                            <select wire:model="technical_category" class="w-full rounded-xl border-transparent bg-gray-50 px-3 py-2.5 text-sm focus:bg-white focus:ring-2 focus:ring-slate-200">
                                                @foreach($technicalCategories as $technicalCategory)
                                                    <option value="{{ $technicalCategory->value }}">{{ $technicalCategory->label() }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="my-5 border-t border-gray-100"></div>

                    <div>
                        <h4 class="text-sm font-semibold text-gray-900">Referencias y compatibilidad</h4>
                        <p class="mt-0.5 text-xs text-gray-500">Datos utilizados para localizar y relacionar la pieza en compras e inventario.</p>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-gray-700">SKU interno</label>
                                <input wire:model="sku" type="text" readonly class="w-full cursor-not-allowed rounded-xl border-transparent bg-gray-100 px-3 py-2.5 font-mono text-sm text-gray-500" placeholder="Se genera al guardar">
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-gray-700">Número de parte</label>
                                <input wire:model="part_number" type="text" class="w-full rounded-xl border-transparent bg-gray-50 px-3 py-2.5 font-mono text-sm focus:bg-white focus:ring-2 focus:ring-slate-200" placeholder="Referencia del fabricante">
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-gray-700">Reemplazo 1</label>
                                <input wire:model="part_number_alt_1" type="text" class="w-full rounded-xl border-transparent bg-gray-50 px-3 py-2.5 font-mono text-sm focus:bg-white focus:ring-2 focus:ring-slate-200" placeholder="Referencia equivalente">
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-gray-700">Reemplazo 2</label>
                                <input wire:model="part_number_alt_2" type="text" class="w-full rounded-xl border-transparent bg-gray-50 px-3 py-2.5 font-mono text-sm focus:bg-white focus:ring-2 focus:ring-slate-200" placeholder="Referencia equivalente">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="mb-1.5 block text-sm font-semibold text-gray-700">Descripción técnica</label>
                                <textarea wire:model="description" rows="3" class="w-full rounded-xl border-transparent bg-gray-50 px-3 py-2.5 text-sm focus:bg-white focus:ring-2 focus:ring-slate-200" placeholder="Especificaciones, compatibilidad y observaciones"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <aside class="border-t border-gray-100 bg-gray-50/50 p-5 lg:col-span-4 lg:border-l lg:border-t-0">
                    <h3 class="text-sm font-semibold text-gray-900">Información comercial</h3>
                    <p class="mt-0.5 text-xs text-gray-500">Valores base y posición actual de inventario.</p>
                    <div class="mt-5 space-y-5 text-sm">
                                <div>
                            <label class="mb-1.5 block text-sm font-semibold text-gray-700">Precio de venta</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-gray-400">$</span>
                                <input wire:model="sales_price" type="number" step="0.01" class="w-full rounded-xl border border-gray-200 bg-white py-2.5 pl-7 pr-3 text-right text-sm tabular-nums focus:ring-2 focus:ring-slate-200">
                            </div>
                                </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-gray-700">Costo promedio</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-gray-400">$</span>
                                <input wire:model.live="cost" type="number" step="0.01" class="w-full rounded-xl border border-gray-200 bg-white py-2.5 pl-7 pr-3 text-right text-sm tabular-nums focus:ring-2 focus:ring-slate-200">
                            </div>
                        </div>

                        <div class="border-t border-gray-200 pt-5">
                            <div class="flex items-start justify-between gap-4">
                                        <div>
                                    <p class="font-semibold text-gray-700">Existencia actual</p>
                                    <p class="mt-1 text-xs leading-5 text-gray-500">Se modifica únicamente mediante movimientos de Kardex.</p>
                                        </div>
                                <strong class="text-xl font-bold tabular-nums text-gray-900">{{ (int) $stock_on_hand }}</strong>
                                    </div>
                            <a href="{{ route('inventory.moves') }}" class="mt-3 inline-flex text-xs font-semibold text-slate-700 underline-offset-4 hover:text-slate-950 hover:underline">Registrar movimiento en Kardex</a>
                        </div>

                        <div class="border-t border-gray-200 pt-5">
                            <div class="flex items-center justify-between gap-4">
                                <span class="text-sm text-gray-600">Costo total actual</span>
                                <strong class="text-lg font-bold tabular-nums text-gray-900">${{ number_format((int) $stock_on_hand * (float) $cost, 2) }}</strong>
                            </div>
                            <p class="mt-2 text-xs leading-5 text-gray-500">El Kardex recalcula este valor por promedio ponderado después de cada entrada.</p>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    @endif

    @if($viewMode === 'kanban')
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            @forelse($products as $product)
                <div wire:click="edit({{ $product->id }})" class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm hover:shadow-md cursor-pointer transition-all">
                    <div class="flex gap-4">
                        <div class="w-16 h-16 bg-gray-50 border border-gray-100 rounded-xl overflow-hidden shrink-0 flex items-center justify-center">
                            @php
                                $storageThumbExists = $product->image_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($product->image_path);
                                $thumb = $storageThumbExists ? asset('storage/'.$product->image_path) : null;
                            @endphp
                            @if($thumb)
                                <img src="{{ $thumb }}" alt="Foto" class="w-full h-full object-cover">
                            @else
                                <span class="text-[10px] font-bold text-gray-400">IMG</span>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-sm font-bold text-gray-900 truncate">{{ $product->name }}</h3>
                            <p class="text-xs text-gray-500 truncate font-mono">{{ $product->sku ?? 'S/N' }}</p>
                            <p class="text-[10px] text-gray-400 truncate mt-1">{{ $product->piece_rotation_type?->label() ?? 'Sin clasificar' }} · {{ $product->technical_category?->label() ?? 'Sin categoria' }}</p>
                        </div>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-2 items-center">
                        <div>
                            <p class="text-[10px] uppercase font-bold text-gray-400">Precio venta</p>
                            <span class="text-sm font-bold text-gray-900">${{ number_format((float) $product->sales_price, 2) }}</span>
                        </div>
                        <div class="text-right">
                            <p class="text-[10px] uppercase font-bold text-gray-400">Costo prom.</p>
                            <span class="text-xs font-semibold text-gray-500">${{ number_format((float) $product->cost, 2) }}</span>
                        </div>
                        <div class="col-span-2 flex items-center justify-between pt-2 border-t border-gray-50">
                            <span class="text-[11px] font-semibold text-gray-500">{{ $product->brand ?: 'Sin marca' }}</span>
                            <span class="bg-indigo-50 text-indigo-700 px-2 py-1 rounded-lg text-xs font-bold border border-indigo-100">{{ $product->stock_on_hand }} und.</span>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full py-20 text-center text-gray-500">No hay productos registrados.</div>
            @endforelse
        </div>
    @endif

    @if($viewMode === 'kanban')
        <div class="mt-6">
            {{ $products->links() }}
        </div>
    @endif

    @if($viewMode === 'list')
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm whitespace-nowrap">
                    <thead class="bg-gray-50 text-gray-500">
                        <tr>
                            <th class="px-6 py-4 font-semibold">Producto</th>
                            <th class="px-6 py-4 font-semibold">SKU</th>
                            <th class="px-6 py-4 font-semibold">Clasificacion</th>
                            <th class="px-6 py-4 font-semibold">Reemplazos</th>
                            <th class="px-6 py-4 font-semibold text-right">Precio</th>
                            <th class="px-6 py-4 font-semibold text-right">Costo Prom.</th>
                            <th class="px-6 py-4 font-semibold text-right">Costo Total</th>
                            <th class="px-6 py-4 font-semibold text-center">Stock</th>
                            <th class="px-6 py-4 font-semibold text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse($products as $product)
                            <tr wire:click="edit({{ $product->id }})" class="hover:bg-gray-50/50 cursor-pointer">
                                <td class="px-6 py-4 font-bold text-gray-900">{{ $product->name }}</td>
                                <td class="px-6 py-4 font-mono text-xs text-gray-500">{{ $product->sku ?? '-' }}</td>
                                <td class="px-6 py-4 text-xs text-gray-500">
                                    <div class="font-semibold text-gray-700">{{ $product->piece_rotation_type?->label() ?? '-' }}</div>
                                    <div>{{ $product->technical_category?->label() ?? '-' }}</div>
                                </td>
                                <td class="px-6 py-4 font-mono text-xs text-gray-500">{{ collect([$product->part_number_alt_1, $product->part_number_alt_2])->filter()->implode(' / ') ?: '-' }}</td>
                                <td class="px-6 py-4 text-right font-medium text-gray-900">${{ number_format((float) $product->sales_price, 2) }}</td>
                                <td class="px-6 py-4 text-right text-gray-500">${{ number_format((float) $product->cost, 2) }}</td>
                                <td class="px-6 py-4 text-right text-gray-500">${{ number_format((float) $product->total_cost, 2) }}</td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold {{ $product->stock_on_hand > 0 ? 'bg-indigo-50 text-indigo-700 border border-indigo-100' : 'bg-red-50 text-red-700 border border-red-100' }}">{{ $product->stock_on_hand }}</span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <button
                                        wire:click.stop="delete({{ $product->id }})"
                                        wire:confirm="¿Retirar el producto? Si tiene historial, se desactiva; el historial se conserva."
                                        class="px-3 py-1.5 rounded-lg bg-rose-50 text-rose-700 text-xs font-bold hover:bg-rose-100"
                                    >
                                        Eliminar/desactivar
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-6 py-16 text-center text-gray-500">No hay productos registrados en el sistema.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-6">
            {{ $products->links() }}
        </div>
    @endif
</div>
