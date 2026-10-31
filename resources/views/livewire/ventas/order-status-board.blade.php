<div class="min-h-screen bg-slate-50 px-4 py-6 sm:px-6 lg:px-8">
    <div class="mx-auto w-full max-w-[1600px] space-y-6">

        @php
            $progressSteps = ['OC Proveedor', 'Confirmación', 'Tránsito / Aduana', 'Bodega', 'Facturación', 'Entrega'];
            $currentOrder = match ($filterStatus) {
                'oc_generada', 'esperando_confirmacion_proveedor' => 1,
                'confirmado_por_proveedor' => 2,
                'en_transito', 'en_aduana' => 3,
                'recibido_parcial', 'recibido_total' => 4,
                'entrega_parcial' => 5,
                'entregado_total' => 6,
                default => null,
            };
        @endphp

        {{-- Page Header con progreso integrado como en fase de cotización --}}
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="shrink-0">
                <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Operaciones · Order Status</p>
                <h1 class="mt-1 text-2xl font-black text-slate-950">Estado del pedido</h1>
            </div>

            <div class="ml-auto w-full min-w-0 max-w-3xl">
                <div class="lg:hidden" aria-label="Ruta operativa del pedido">
                    <div class="mb-1 flex items-center justify-between gap-3 text-sm">
                        <span class="font-semibold text-slate-800">{{ $currentOrder ? $progressSteps[$currentOrder - 1] : 'Ruta operativa' }}</span>
                        @if($currentOrder)<span class="shrink-0 text-gray-500">{{ $currentOrder }} de {{ count($progressSteps) }}</span>@endif
                    </div>
                    <div class="flex gap-1" aria-hidden="true">
                        @foreach($progressSteps as $step)
                            <span class="h-1 flex-1 rounded {{ $currentOrder && $loop->iteration < $currentOrder ? 'bg-emerald-500' : ($currentOrder && $loop->iteration === $currentOrder ? 'bg-slate-800' : 'bg-gray-200') }}"></span>
                        @endforeach
                    </div>
                </div>
                <div class="hidden lg:block">
                    <x-process-steps :steps="$progressSteps" :current="$currentOrder" :compact="true" :single-row="true" :wrap-labels="true" label="Ruta operativa del pedido" />
                </div>
            </div>
        </div>

        {{-- Search & Filter --}}
        <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                    </svg>
                    <input type="text" wire:model.live.debounce.500ms="search"
                        placeholder="Buscar pedido, seguimiento, cliente, parte o producto"
                        class="w-full rounded-lg border border-slate-200 pl-9 pr-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 transition-colors">
                </div>
                <select wire:model.live="filterStatus"
                    class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 transition-colors sm:w-48">
                    <option value="">Todos los estados</option>
                    @foreach($stages as $status => $stage)
                        <option value="{{ $status }}">{{ $stage['label'] }}</option>
                    @endforeach
                </select>
            </div>
        </section>

        {{-- Orders table (desktop) / cards (mobile) --}}
        <section>
            {{-- Mobile: card list --}}
            <div class="block sm:hidden space-y-3">
                @forelse($orders as $order)
                    @php
                        $stage = $stages[$order->current_status] ?? ['label' => str($order->current_status)->replace('_', ' ')->headline(), 'color' => 'bg-slate-100 text-slate-700 border-slate-200'];
                        $hasTransit = $order->current_status === 'en_transito';
                        $purchaseDocument = $order->purchaseOrderDocuments->first();
                        $statusPurchaseDocument = $order->documents->firstWhere('document_type', 'purchase_order_provider_pdf') ?: $order->documents->firstWhere('document_type', 'purchase_order_pdf');
                        $pendingSend = $purchaseDocument?->pdf_path && ! $purchaseDocument?->sent_to_provider_at;
                        $nextStep = $this->nextStep($order);
                        $detailUrl = $order->purchase_order_id ? route('warehouse.incoming-orders') : route('orders.status.show', $order->sale_order_id);
                    @endphp
                    <div onclick="window.location.href='{{ $detailUrl }}'"
                         class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 cursor-pointer active:bg-slate-50 transition-colors">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-black text-slate-900 text-sm truncate">{{ $order->order_code }}</p>
                                <p class="text-[10px] text-slate-400 font-bold mt-0.5">{{ $order->tracking_code ?: 'Sin seguimiento' }}</p>
                                <p class="text-xs font-bold text-slate-700 mt-1 truncate">{{ $order->saleOrder?->client_name ?: $order->customer?->name ?: ($order->purchase_order_id ? 'Compra interna' : 'Sin cliente') }}</p>
                            </div>
                            <span class="inline-flex shrink-0 items-center rounded-full border px-2.5 py-1 text-[9px] font-black uppercase tracking-widest {{ $stage['color'] }}">
                                {{ $stage['label'] }}
                            </span>
                        </div>
                        <div class="mt-3 flex items-center gap-4">
                            <div class="text-center">
                                <p class="text-[9px] font-black uppercase text-slate-400">Partidas</p>
                                <p class="text-sm font-black text-slate-900">{{ $order->items_count }}</p>
                            </div>
                            <div class="flex-1">
                                <div class="flex justify-between text-[9px] font-black uppercase text-slate-500 mb-1">
                                    <span>Avance</span>
                                    <span class="{{ $hasTransit ? 'text-blue-600' : 'text-slate-900' }}">{{ $order->progress_percentage }}%</span>
                                </div>
                                <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full {{ $hasTransit ? 'bg-blue-600' : 'bg-slate-900' }}" style="width: {{ min(100, max(0, $order->progress_percentage)) }}%"></div>
                                </div>
                            </div>
                            <div>
                                @if($purchaseDocument?->sent_to_provider_at)
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[9px] font-black text-emerald-800">Enviada</span>
                                @elseif($pendingSend)
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[9px] font-black text-amber-800">PDF listo</span>
                                @else
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-black text-slate-600">Pendiente</span>
                                @endif
                            </div>
                        </div>
                        <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                            <p class="text-[10px] font-black text-slate-900">Ahora: {{ $nextStep['title'] }}</p>
                            <p class="mt-0.5 text-[10px] leading-4 text-slate-600">{{ $nextStep['detail'] }}</p>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border-2 border-dashed border-slate-200 py-16 text-center">
                        <p class="text-sm font-bold text-slate-500">No hay órdenes en estado del pedido.</p>
                    </div>
                @endforelse
            </div>

            {{-- Desktop: table --}}
            <div class="hidden sm:block bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs whitespace-nowrap">
                        <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-widest text-slate-400 border-b border-slate-100">
                            <tr>
                                <th class="px-4 py-3">Pedido</th>
                                <th class="px-4 py-3">Cliente</th>
                                <th class="px-4 py-3 min-w-[220px]">Siguiente acción</th>
                                <th class="px-4 py-3">Estado</th>
                                <th class="px-4 py-3">OC Proveedor</th>
                                <th class="px-4 py-3 text-center">Partidas</th>
                                <th class="px-4 py-3 min-w-[140px]">Progreso</th>
                                <th class="px-4 py-3 min-w-[200px]">Productos</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($orders as $order)
                                @php
                                    $stage = $stages[$order->current_status] ?? ['label' => str($order->current_status)->replace('_', ' ')->headline(), 'color' => 'bg-slate-100 text-slate-700 border-slate-200'];
                                    $purchaseDocument = $order->purchaseOrderDocuments->first();
                                    $statusPurchaseDocument = $order->documents->firstWhere('document_type', 'purchase_order_provider_pdf') ?: $order->documents->firstWhere('document_type', 'purchase_order_pdf');
                                    $pendingSend = $purchaseDocument?->pdf_path && ! $purchaseDocument?->sent_to_provider_at;
                                    $hasTransit = $order->current_status === 'en_transito';
                                    $shipment = $order->shipmentBatches->sortByDesc('created_at')->first();
                                    $previewItems = $order->items->take(2);
                                    $receivedItems = $order->items->filter(fn ($item) => (float) $item->quantity_received > 0)->count();
                                    $readyItems = $order->items->filter(fn ($item) => (float) $item->quantity_kardex_entry > (float) $item->quantity_kardex_output)->count();
                                    $nextStep = $this->nextStep($order);
                                    $detailUrl = $order->purchase_order_id ? route('warehouse.incoming-orders') : route('orders.status.show', $order->sale_order_id);
                                @endphp
                                <tr onclick="window.location.href='{{ $detailUrl }}'"
                                    class="align-middle transition-colors hover:bg-blue-50/50 cursor-pointer group">
                                    <td class="px-4 py-3 relative">
                                        <div class="absolute left-0 top-0 bottom-0 w-1 {{ $hasTransit ? 'bg-blue-600' : 'bg-transparent group-hover:bg-blue-300' }}"></div>
                                        <p class="text-sm font-black text-slate-900 group-hover:text-blue-700 transition-colors">{{ $order->order_code }}</p>
                                        <p class="mt-0.5 text-[10px] font-bold text-slate-400">{{ $order->tracking_code ?: 'Sin seguimiento' }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="max-w-[140px] truncate text-xs font-black text-slate-800">{{ $order->saleOrder?->client_name ?: $order->customer?->name ?: ($order->purchase_order_id ? 'Compra interna' : 'Sin cliente') }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-2">
                                            <p class="text-[10px] font-black text-slate-900">{{ $nextStep['title'] }}</p>
                                            <p class="mt-0.5 whitespace-normal text-[9px] leading-4 font-medium text-slate-600">{{ $nextStep['detail'] }}</p>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full border px-2.5 py-1 text-[9px] font-black uppercase tracking-widest {{ $stage['color'] }}">
                                            @if($hasTransit)
                                                <span class="h-1.5 w-1.5 rounded-full bg-blue-600 shadow-[0_0_0_2px_rgba(37,99,235,0.12)]"></span>
                                            @endif
                                            {{ $stage['label'] }}
                                        </span>
                                        @if($hasTransit && $shipment)
                                            <p class="mt-1 text-[9px] font-bold text-slate-500 max-w-[120px] truncate">
                                                {{ $shipment->carrier_name ?: 'Embarque activo' }}
                                                @if($shipment->tracking_number)
                                                    <span class="text-slate-400">/</span> {{ $shipment->tracking_number }}
                                                @endif
                                            </p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($purchaseDocument?->sent_to_provider_at)
                                            <span class="inline-flex whitespace-nowrap rounded-full bg-emerald-100 px-2.5 py-1 text-[9px] font-black uppercase text-emerald-800 tracking-wider">Enviada</span>
                                        @elseif($pendingSend)
                                            <span class="inline-flex whitespace-nowrap rounded-full bg-amber-100 px-2.5 py-1 text-[9px] font-black uppercase text-amber-800 tracking-wider">PDF listo</span>
                                        @elseif($purchaseDocument || $statusPurchaseDocument || $hasTransit)
                                            <span class="inline-flex whitespace-nowrap rounded-full bg-indigo-100 px-2.5 py-1 text-[9px] font-black uppercase text-indigo-800 tracking-wider">Completada</span>
                                        @else
                                            <span class="inline-flex whitespace-nowrap rounded-full bg-slate-100 px-2.5 py-1 text-[9px] font-black uppercase text-slate-500 tracking-wider">Pendiente</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex h-7 min-w-7 items-center justify-center rounded-md bg-slate-50 border border-slate-100 px-2 text-[11px] font-black text-slate-800">{{ $order->items_count }}</span>
                                    </td>
                                    <td class="px-4 py-3 w-full max-w-[160px]">
                                        <div class="flex items-center justify-between text-[9px] font-black uppercase text-slate-500 mb-1">
                                            <span>Avance</span>
                                            <span class="{{ $hasTransit ? 'text-blue-600' : 'text-slate-900' }}">{{ $order->progress_percentage }}%</span>
                                        </div>
                                        <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                                            <div class="h-full rounded-full {{ $hasTransit ? 'bg-blue-600' : 'bg-slate-900' }} transition-all duration-1000" style="width: {{ min(100, max(0, $order->progress_percentage)) }}%"></div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-col gap-1.5">
                                            <div class="flex flex-wrap gap-1 text-[9px] font-black">
                                                <span class="rounded bg-slate-100 px-1.5 py-0.5 text-slate-600">{{ $receivedItems }} recibidos</span>
                                                <span class="rounded bg-emerald-50 text-emerald-700 border border-emerald-100 px-1.5 py-0.5">{{ $readyItems }} listos salida</span>
                                            </div>
                                            <div class="space-y-1">
                                                @foreach($previewItems as $item)
                                                    <div class="rounded border border-slate-100 bg-white px-2 py-1 flex items-center justify-between gap-2 shadow-[0_1px_2px_rgba(0,0,0,0.02)]">
                                                        <div class="min-w-0 flex-1">
                                                            <p class="truncate text-[10px] font-black text-slate-800 leading-tight">{{ $item->part_name ?: $item->product?->name ?: 'Producto' }}</p>
                                                            <p class="truncate text-[9px] font-bold text-slate-400 leading-tight">{{ $item->part_number ?: 'Sin PN' }}</p>
                                                        </div>
                                                        <span class="shrink-0 text-[10px] font-black text-slate-600">{{ number_format((float) $item->quantity_received, 0) }}/{{ number_format((float) $item->quantity_ordered, 0) }}</span>
                                                    </div>
                                                @endforeach
                                                @if($order->items->count() > 2)
                                                    <p class="text-[9px] font-bold text-slate-400 pl-1">+{{ $order->items->count() - 2 }} productos más</p>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-6 py-14 text-center text-sm font-bold text-slate-500">
                                        <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 mb-3">
                                            <svg class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                            </svg>
                                        </div>
                                        No hay órdenes en estado del pedido.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>
