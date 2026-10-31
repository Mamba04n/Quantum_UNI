<div class="p-6 font-sans sm:p-8">
    <div class="space-y-6">
        <section>
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-gray-900">Órdenes por recibir</h1>
                    <p class="mt-0.5 text-sm text-gray-500">Pedidos en tránsito y recepción de mercancía.</p>
                </div>

                <div class="grid gap-3 sm:grid-cols-[minmax(220px,1fr)_180px]">
                    <input wire:model.live.debounce.400ms="search" type="search" aria-label="Buscar pedidos entrantes" class="w-full rounded-xl border-transparent bg-gray-50 px-3 py-2 text-sm focus:bg-white focus:ring-2 focus:ring-slate-200" placeholder="Buscar tracking, orden, cliente, PN...">
                    <select wire:model.live="statusFilter" aria-label="Estado del pedido" class="rounded-xl border-slate-200 text-sm">
                        <option value="">Todos los estados</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </section>

        @if (session('message'))
            <div
                x-data="{ show: true }"
                x-init="setTimeout(() => show = false, 5200)"
                x-show="show"
                x-transition
                class="pointer-events-none fixed right-4 top-20 z-50 w-[calc(100vw-2rem)] max-w-md sm:right-6"
            >
                <div class="pointer-events-auto rounded-lg border border-emerald-200 bg-white px-4 py-3 text-sm font-semibold text-slate-800 shadow-xl ring-1 ring-black/5">
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-700">
                            <span class="text-xs font-black">OK</span>
                        </div>
                        <p class="min-w-0 flex-1 leading-5">{{ session('message') }}</p>
                        <button type="button" x-on:click="show = false" class="-mr-1 rounded-md px-1.5 py-0.5 text-xs font-black text-slate-400 hover:bg-slate-100 hover:text-slate-700">X</button>
                    </div>
                </div>
            </div>
        @endif

        @if ($errors->any())
            <div
                x-data="{ show: true }"
                x-init="setTimeout(() => show = false, 7000)"
                x-show="show"
                x-transition
                class="pointer-events-none fixed right-4 top-20 z-50 w-[calc(100vw-2rem)] max-w-md sm:right-6"
            >
                <div class="pointer-events-auto rounded-lg border border-rose-200 bg-white px-4 py-3 text-sm font-semibold text-slate-800 shadow-xl ring-1 ring-black/5">
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-rose-50 text-rose-700">
                            <span class="text-xs font-black">!</span>
                        </div>
                        <p class="min-w-0 flex-1 leading-5">{{ $errors->first() }}</p>
                        <button type="button" x-on:click="show = false" class="-mr-1 rounded-md px-1.5 py-0.5 text-xs font-black text-slate-400 hover:bg-slate-100 hover:text-slate-700">X</button>
                    </div>
                </div>
            </div>
        @endif

        @if($receivingItem)
            @php
                $pendingReception = $this->pendingReceptionForItem($receivingItem);
            @endphp
            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <header class="border-b border-slate-200 px-5 py-4 sm:px-6">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <p class="text-[10px] font-black uppercase tracking-[0.22em] text-emerald-700">Recepcion de Bodega</p>
                            <span class="inline-flex rounded-full bg-indigo-50 px-2 py-1 text-[10px] font-black uppercase tracking-wide text-indigo-700">{{ $receivingItem->orderStatus?->purchase_order_id ? 'Compra interna' : 'Pedido cliente' }}</span>
                            <h2 class="mt-2 text-2xl font-black leading-tight text-slate-950">{{ $receivingItem->part_name ?: $receivingItem->product?->name ?: 'Producto sin nombre' }}</h2>
                            <p class="mt-1 text-sm font-bold text-slate-500">
                                {{ $receivingItem->part_number ?: 'Sin PN' }}{{ $receivingItem->brand ? ' / '.$receivingItem->brand : '' }}{{ $receivingItem->model ? ' / '.$receivingItem->model : '' }}
                            </p>
                        </div>
                        <button type="button" wire:click="cancelReception" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-black text-slate-700 hover:bg-slate-50">
                            Volver a pedidos
                        </button>
                    </div>
                </header>

                <form wire:submit.prevent="registerReception">
                    <main class="bg-slate-50/60 p-5 sm:p-6">
                        <div class="grid gap-5 lg:grid-cols-[.9fr_1.1fr]">
                            <aside class="space-y-4">
                                <div class="rounded-xl border border-slate-200 bg-white p-4">
                                    <p class="text-xs font-black uppercase text-slate-400">Resumen del pedido</p>
                                    <div class="mt-4 space-y-2 text-sm">
                                        <p><span class="font-black text-slate-950">Tracking:</span> {{ $receivingItem->orderStatus?->tracking_code ?: ($receivingItem->orderStatus?->purchaseOrder?->reference_number ?: 'Compra interna') }}</p>
                                        <p><span class="font-black text-slate-950">Orden:</span> {{ $receivingItem->orderStatus?->order_code ?: '-' }}</p>
                                        <p><span class="font-black text-slate-950">Destino:</span> {{ $receivingItem->orderStatus?->purchase_order_id ? 'Inventario interno' : ($receivingItem->orderStatus?->saleOrder?->client_name ?: $receivingItem->orderStatus?->customer?->name ?: 'Sin cliente') }}</p>
                                        <p><span class="font-black text-slate-950">Proveedor:</span> {{ $receivingItem->supplier?->name ?: 'No asignado' }}</p>
                                    </div>
                                </div>

                            </aside>

                            <section class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <label class="block sm:col-span-2">
                                        <span class="text-[10px] font-black uppercase text-slate-500">Cantidad recibida</span>
                                        <input wire:model="quantity" type="number" min="1" step="1" class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                                        @error('quantity') <p class="mt-1 text-xs font-bold text-red-700">{{ $message }}</p> @enderror
                                        @if($confirmQuantityMismatch)
                                            <p class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-bold text-amber-800">Presiona registrar otra vez para confirmar una recepcion parcial.</p>
                                        @endif
                                    </label>
                                    <label class="block">
                                        <span class="text-[10px] font-black uppercase text-slate-500">Condicion</span>
                                        <select wire:model="warehouseCondition" class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                                            <option value="bueno">Bueno</option>
                                            <option value="danado">Danado</option>
                                            <option value="incompleto">Incompleto</option>
                                            <option value="pendiente_revision">Pendiente revision</option>
                                        </select>
                                    </label>
                                    <label class="block">
                                        <span class="text-[10px] font-black uppercase text-slate-500">Ubicacion</span>
                                        <input wire:model="warehouseLocation" type="text" class="mt-1 w-full rounded-lg border-slate-200 text-sm" placeholder="Rack, estante, zona">
                                    </label>
                                    <label class="block">
                                        <span class="text-[10px] font-black uppercase text-slate-500">Evidencia</span>
                                        <input wire:model="warehouseEvidenceFile" type="file" class="mt-1 w-full rounded-lg border border-slate-200 bg-white p-2 text-sm">
                                        @error('warehouseEvidenceFile') <p class="mt-1 text-xs font-bold text-red-700">{{ $message }}</p> @enderror
                                    </label>
                                    <label class="block">
                                        <span class="text-[10px] font-black uppercase text-slate-500">Foto producto</span>
                                        <input wire:model="warehousePhotoFile" type="file" class="mt-1 w-full rounded-lg border border-slate-200 bg-white p-2 text-sm">
                                        @error('warehousePhotoFile') <p class="mt-1 text-xs font-bold text-red-700">{{ $message }}</p> @enderror
                                    </label>
                                    <label class="block sm:col-span-2">
                                        <span class="text-[10px] font-black uppercase text-slate-500">Observaciones</span>
                                        <textarea wire:model="notes" rows="4" class="mt-1 w-full rounded-lg border-slate-200 text-sm" placeholder="Observaciones de bodega"></textarea>
                                    </label>
                                </div>
                            </section>
                        </div>
                    </main>

                    <footer class="border-t border-slate-200 bg-white px-5 py-4 sm:px-6">
                        <div class="grid gap-2 sm:grid-cols-[1fr_auto]">
                            <button type="submit" wire:loading.attr="disabled" wire:target="registerReception,warehouseEvidenceFile,warehousePhotoFile" class="rounded-xl bg-slate-950 px-4 py-3 text-sm font-black text-white hover:bg-red-800 disabled:opacity-60">
                                <span wire:loading.remove wire:target="registerReception">{{ $confirmQuantityMismatch ? 'Confirmar recepcion parcial' : 'Registrar recepcion y Kardex' }}</span>
                                <span wire:loading wire:target="registerReception">Registrando...</span>
                            </button>
                            <button type="button" wire:click="cancelReception" class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-black text-slate-700 hover:bg-slate-50">
                                Cancelar
                            </button>
                        </div>
                    </footer>
                </form>
            </section>
        @else
        <section class="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
            @forelse ($items as $item)
                @php
                    $pendingReception = $this->pendingReceptionForItem($item);
                    $isInternalPurchase = (bool) $item->orderStatus?->purchase_order_id;
                    $canReceive = $this->canReceiveItem($item) && $pendingReception > 0;
                @endphp
                <article class="rounded-2xl border {{ $canReceive ? 'border-emerald-200' : 'border-slate-200' }} bg-white p-5 shadow-sm transition hover:border-red-200 hover:shadow-md">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-black uppercase tracking-wide text-slate-400">{{ $item->orderStatus?->tracking_code ?: ($item->orderStatus?->purchaseOrder?->reference_number ?: 'Sin tracking') }}</p>
                            @if($isInternalPurchase)<span class="mt-1 inline-flex rounded-full bg-indigo-50 px-2 py-1 text-[10px] font-black uppercase tracking-wide text-indigo-700">Compra interna</span>@endif
                            <h2 class="mt-2 text-lg font-black text-slate-950">{{ $item->part_name ?: $item->product?->name ?: 'Producto sin nombre' }}</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ $item->part_number ?: 'Sin PN' }} {{ $item->brand ? ' / '.$item->brand : '' }}</p>
                        </div>
                        <span class="rounded-full {{ $canReceive ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-700' }} px-3 py-1 text-xs font-black">{{ $statuses[$item->status] ?? str($item->status)->replace('_', ' ')->headline() }}</span>
                    </div>

                    <div class="mt-5 grid grid-cols-3 gap-3 text-sm">
                        <div class="rounded-xl bg-slate-50 p-3">
                            <p class="text-xs font-black uppercase text-slate-400">Pedida</p>
                            <p class="font-black text-slate-900">{{ number_format((float) $item->quantity_ordered, 0) }}</p>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-3">
                            <p class="text-xs font-black uppercase text-slate-400">Recibida</p>
                            <p class="font-black text-slate-900">{{ number_format((float) $item->quantity_received, 0) }}</p>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-3">
                            <p class="text-xs font-black uppercase text-slate-400">Esperado</p>
                            <p class="font-black text-slate-900">{{ optional($item->estimated_arrival_date)->format('d/m/Y') ?: 'S/F' }}</p>
                        </div>
                    </div>

                    <div class="mt-5 space-y-1 text-sm text-slate-600">
                        <p><span class="font-bold text-slate-900">Orden:</span> {{ $item->orderStatus?->order_code }}</p>
                        <p><span class="font-bold text-slate-900">Destino:</span> {{ $isInternalPurchase ? 'Inventario interno' : ($item->orderStatus?->saleOrder?->client_name ?: $item->orderStatus?->customer?->name ?: 'Sin cliente') }}</p>
                        <p><span class="font-bold text-slate-900">Proveedor:</span> {{ $item->supplier?->name ?: 'No asignado' }}</p>
                        <p><span class="font-bold text-slate-900">Modelo:</span> {{ $item->model ?: 'No especificado' }}</p>
                    </div>

                    <div class="mt-5 grid gap-2 sm:grid-cols-2">
                        @if($canReceive)
                            <button type="button" wire:click="startReception({{ $item->id }})" class="inline-flex w-full items-center justify-center rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-black text-white transition hover:bg-emerald-800" wire:loading.attr="disabled" wire:target="startReception({{ $item->id }})">
                                Registrar recepcion
                            </button>
                        @else
                            <span class="inline-flex w-full items-center justify-center rounded-xl bg-slate-100 px-4 py-2.5 text-center text-xs font-black text-slate-500">
                                {{ (float) $item->quantity_received > 0 ? 'Recepcion registrada' : 'Esperando aduana' }}
                            </span>
                        @endif
                        @if(! $isInternalPurchase)
                            <a href="{{ route('orders.status.show', $item->orderStatus?->sale_order_id) }}" class="inline-flex w-full items-center justify-center rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-black text-white transition hover:bg-red-800">
                                Abrir seguimiento
                            </a>
                        @else
                            <span class="inline-flex w-full items-center justify-center rounded-xl bg-slate-100 px-4 py-2.5 text-center text-xs font-black text-slate-600">Entrada para inventario</span>
                        @endif
                    </div>
                </article>
            @empty
                <div class="col-span-full rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center">
                    <p class="text-lg font-black text-slate-900">No hay ordenes pendientes de recepcion.</p>
                    <p class="mt-2 text-sm text-slate-500">Cuando compras confirme productos en transito, apareceran aqui.</p>
                </div>
            @endforelse
        </section>

        <div>
            {{ $items->links() }}
        </div>
        @endif
    </div>
</div>
