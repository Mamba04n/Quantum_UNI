<section class="min-w-0 rounded-2xl border border-gray-100 bg-white shadow-sm" aria-labelledby="request-products-heading">
    <div class="flex flex-col gap-3 border-b border-gray-100 p-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
        <div class="flex flex-wrap items-center gap-2">
            <h3 id="request-products-heading" class="text-sm font-semibold text-gray-900">Productos solicitados</h3>
            <span class="text-sm text-gray-500">{{ count($items) }} {{ count($items) === 1 ? 'producto' : 'productos' }}</span>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" wire:click="openBulkModal" class="inline-flex items-center gap-2 justify-center px-4 py-2.5 rounded-xl bg-white border border-slate-300 text-slate-700 text-sm font-semibold hover:bg-slate-50 hover:text-slate-900 transition shadow-xs">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Carga Masiva (Excel / Texto)
            </button>
            <button type="button" wire:click="addItem" class="inline-flex items-center gap-1.5 justify-center px-4 py-2.5 rounded-xl bg-slate-800 text-white text-sm font-semibold hover:bg-slate-900 transition shadow-xs">
                <svg class="w-4 h-4 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
                Agregar producto
            </button>
        </div>
    </div>

    @include('livewire.ventas.cotizaciones.shared.modal-carga-masiva')

    @error('items') <p role="alert" class="mb-2 text-xs text-red-500">{{ $message }}</p> @enderror
    <div class="overflow-hidden rounded-b-2xl">
        <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Productos solicitados: tabla editable">
            <table class="w-full text-left text-sm">
                <thead class="sticky top-0 z-10 border-b border-gray-100 bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                    <tr>
                        <th scope="col" class="whitespace-nowrap px-3 py-3 font-semibold">Producto</th>
                        <th scope="col" class="whitespace-nowrap px-3 py-3 font-semibold">Parte</th>
                        <th scope="col" class="whitespace-nowrap px-3 py-3 font-semibold">Nombre / descripción</th>
                        <th scope="col" class="whitespace-nowrap px-3 py-3 font-semibold">Marca</th>
                        <th scope="col" class="whitespace-nowrap px-3 py-3 font-semibold">Modelo</th>
                        <th scope="col" class="whitespace-nowrap px-3 py-3 font-semibold">Serie</th>
                        <th scope="col" class="whitespace-nowrap px-3 py-3 font-semibold">Cant.</th>
                        <th scope="col" class="whitespace-nowrap px-3 py-3 font-semibold">Unidad</th>
                        <th scope="col" class="px-3 py-3 font-semibold"><span class="sr-only">Acciones</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($items as $index => $item)
                        <tr wire:key="customer-request-item-{{ $index }}" class="align-top transition-colors hover:bg-gray-50/50">
                            <td class="px-2 py-2">
                                <select aria-label="Producto / origen, producto {{ $index + 1 }}" aria-describedby="request-item-{{ $index }}-product_id-error" @error("items.$index.product_id") aria-invalid="true" @enderror wire:model.live="items.{{ $index }}.product_id" class="w-40 border-gray-200 bg-white rounded-lg py-2 px-3 text-sm focus:border-slate-300 focus:ring-2 focus:ring-slate-200">
                            <option value="">Captura manual</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}">{{ $product->name }}{{ $product->part_number ? ' · '.$product->part_number : '' }}{{ $product->sku ? ' · SKU '.$product->sku : '' }}</option>
                            @endforeach
                        </select>
                                @error("items.$index.product_id") <span id="request-item-{{ $index }}-product_id-error" role="alert" class="mt-1 block w-40 whitespace-normal text-xs text-red-500">{{ $message }}</span> @enderror
                            </td>
                            <td class="px-2 py-2">
                                <input aria-label="Referencia / parte, producto {{ $index + 1 }}" aria-describedby="request-item-{{ $index }}-part_code-error" @error("items.$index.part_code") aria-invalid="true" @enderror wire:model="items.{{ $index }}.part_code" type="text" class="w-28 border-gray-200 bg-white rounded-lg py-2 px-3 text-sm focus:border-slate-300 focus:ring-2 focus:ring-slate-200">
                                @error("items.$index.part_code") <span id="request-item-{{ $index }}-part_code-error" role="alert" class="mt-1 block w-28 whitespace-normal text-xs text-red-500">{{ $message }}</span> @enderror
                            </td>
                            <td class="px-2 py-2">
                                <input aria-label="Nombre de pieza, producto {{ $index + 1 }}" aria-describedby="request-item-{{ $index }}-part_name-error" @error("items.$index.part_name") aria-invalid="true" @enderror wire:model="items.{{ $index }}.part_name" type="text" class="w-48 border-gray-200 bg-white rounded-lg py-2 px-3 text-sm focus:border-slate-300 focus:ring-2 focus:ring-slate-200">
                                @error("items.$index.part_name") <span id="request-item-{{ $index }}-part_name-error" role="alert" class="mt-1 block w-48 whitespace-normal text-xs text-red-500">{{ $message }}</span> @enderror
                            </td>
                            <td class="px-2 py-2">
                                <input aria-label="Marca, producto {{ $index + 1 }}" aria-describedby="request-item-{{ $index }}-part_brand-error" @error("items.$index.part_brand") aria-invalid="true" @enderror wire:model="items.{{ $index }}.part_brand" type="text" class="w-20 border-gray-200 bg-white rounded-lg py-2 px-3 text-sm focus:border-slate-300 focus:ring-2 focus:ring-slate-200">
                                @error("items.$index.part_brand") <span id="request-item-{{ $index }}-part_brand-error" role="alert" class="mt-1 block w-20 whitespace-normal text-xs text-red-500">{{ $message }}</span> @enderror
                            </td>
                            <td class="px-2 py-2">
                                <input aria-label="Modelo, producto {{ $index + 1 }}" aria-describedby="request-item-{{ $index }}-part_model-error" @error("items.$index.part_model") aria-invalid="true" @enderror wire:model="items.{{ $index }}.part_model" type="text" class="w-20 border-gray-200 bg-white rounded-lg py-2 px-3 text-sm focus:border-slate-300 focus:ring-2 focus:ring-slate-200">
                                @error("items.$index.part_model") <span id="request-item-{{ $index }}-part_model-error" role="alert" class="mt-1 block w-20 whitespace-normal text-xs text-red-500">{{ $message }}</span> @enderror
                            </td>
                            <td class="px-2 py-2">
                                <input aria-label="Serie, producto {{ $index + 1 }}" aria-describedby="request-item-{{ $index }}-part_serial_number-error" @error("items.$index.part_serial_number") aria-invalid="true" @enderror wire:model="items.{{ $index }}.part_serial_number" type="text" class="w-24 border-gray-200 bg-white rounded-lg py-2 px-3 text-sm focus:border-slate-300 focus:ring-2 focus:ring-slate-200">
                                @error("items.$index.part_serial_number") <span id="request-item-{{ $index }}-part_serial_number-error" role="alert" class="mt-1 block w-24 whitespace-normal text-xs text-red-500">{{ $message }}</span> @enderror
                            </td>
                            <td class="px-2 py-2">
                                <input aria-label="Cantidad, producto {{ $index + 1 }}" aria-describedby="request-item-{{ $index }}-quantity-error" @error("items.$index.quantity") aria-invalid="true" @enderror wire:model="items.{{ $index }}.quantity" type="number" min="1" step="1" class="w-20 border-gray-200 bg-white rounded-lg py-2 px-3 text-sm focus:border-slate-300 focus:ring-2 focus:ring-slate-200">
                                @error("items.$index.quantity") <span id="request-item-{{ $index }}-quantity-error" role="alert" class="mt-1 block w-20 whitespace-normal text-xs text-red-500">{{ $message }}</span> @enderror
                            </td>
                            <td class="px-2 py-2">
                                <input aria-label="Unidad, producto {{ $index + 1 }}" aria-describedby="request-item-{{ $index }}-unit-error" @error("items.$index.unit") aria-invalid="true" @enderror wire:model="items.{{ $index }}.unit" type="text" class="w-20 border-gray-200 bg-white rounded-lg py-2 px-3 text-sm focus:border-slate-300 focus:ring-2 focus:ring-slate-200">
                                @error("items.$index.unit") <span id="request-item-{{ $index }}-unit-error" role="alert" class="mt-1 block w-20 whitespace-normal text-xs text-red-500">{{ $message }}</span> @enderror
                            </td>
                            <td class="px-2 py-2">
                                @if(count($items) > 1)
                                    <button type="button" wire:click="removeItem({{ $index }})" wire:confirm="¿Estás seguro de que deseas eliminar este producto de la solicitud?" wire:loading.attr="disabled" wire:target="removeItem" aria-label="Eliminar producto {{ $index + 1 }}" title="Eliminar producto" class="rounded-lg p-2.5 text-red-600 hover:bg-red-50 focus-visible:ring-2 focus-visible:ring-red-300 disabled:opacity-50">
                                        <svg class="h-5 w-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>
