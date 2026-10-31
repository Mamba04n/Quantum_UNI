<div x-data="{ open: true }" x-show="open" x-on:keydown.escape.window="open = false; $wire.closeModal()" class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" x-on:click="open = false" wire:click="closeModal"></div>

    <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-6xl max-h-[90vh] overflow-hidden flex flex-col animate-in fade-in zoom-in duration-200">
        {{-- Encabezado del modal --}}
        <div class="p-6 border-b border-gray-100 flex items-center justify-between bg-gray-50">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <h3 class="text-xl font-black text-slate-800">{{ $itemType === 'quote' ? 'Oferta' : 'Pedido' }} #{{ $selectedItem->reference }}</h3>
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[10px] font-bold rounded uppercase tracking-wider border border-slate-200">
                        {{ $selectedItem->status }}
                    </span>
                </div>
                <p class="text-xs text-gray-500 font-medium">Cliente: <span class="text-slate-700 font-bold">{{ $selectedItem->quote_to_name ?? $selectedItem->client_name }}</span></p>
                @if($selectedTracker)
                    <p class="text-[10px] text-slate-500 font-black uppercase tracking-widest mt-1">Seguimiento: {{ $selectedTracker->tracking_code }}</p>
                @endif
            </div>
            <div class="flex items-center gap-2">
                <button type="button" x-on:click="open = false" wire:click="closeModal" class="p-2 hover:bg-gray-200 rounded-xl transition-colors">
                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        {{-- Contenido del modal --}}
        <div class="p-6 overflow-y-auto flex-1">
            @if($errorMessage)
                <div class="mb-4 p-3 bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold rounded-xl flex items-center gap-2">
                    {{ $errorMessage }}
                </div>
            @endif

            {{-- Resumen --}}
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="p-3 bg-slate-50 rounded-2xl border border-slate-100 text-center">
                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest block mb-1">Total</span>
                    <p class="text-sm font-black text-slate-900">${{ number_format($selectedItem->total_amount, 2) }}</p>
                </div>
                <div class="p-3 bg-slate-50 rounded-2xl border border-slate-100 text-center">
                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest block mb-1">Fecha</span>
                    <p class="text-sm font-bold text-slate-700">{{ \Carbon\Carbon::parse($selectedItem->date)->format('d/m/Y') }}</p>
                </div>
                <div class="p-3 bg-slate-50 rounded-2xl border border-slate-100 text-center">
                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest block mb-1">Ítems</span>
                    <p class="text-sm font-bold text-slate-700">{{ count($selectedItem->items) }}</p>
                </div>
                <div class="p-3 bg-slate-50 rounded-2xl border border-slate-100 text-center">
                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest block mb-1">Días Transcurridos</span>
                    <p class="text-sm font-bold text-slate-700">{{ (int) $selectedItem->created_at->diffInDays() }} d</p>
                </div>
            </div>

            {{-- Tabla de Ítems --}}
            @if($itemType === 'order')
                @php
                    $statusItems = $selectedItem->orderStatus?->items ?? collect();
                    $selectedStatusItem = $selectedOrderStatusItemId ? $statusItems->firstWhere('id', $selectedOrderStatusItemId) : null;
                    $received = $statusItems->filter(fn ($line) => (float) $line->quantity_received > 0)->count();
                    $ready = $statusItems->filter(fn ($line) => (float) $line->quantity_kardex_entry > (float) $line->quantity_kardex_output)->count();
                    $delivered = $statusItems->filter(fn ($line) => (float) $line->quantity_delivered > 0)->count();
                @endphp

                <div class="mb-4 flex flex-col gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Resumen operativo por producto</p>
                        <p class="mt-1 text-sm font-bold text-slate-700">{{ $received }} recibidos · {{ $ready }} listos para salida · {{ $delivered }} con entrega registrada</p>
                    </div>
                    <a href="{{ route('orders.status.show', $selectedItem) }}" data-testid="tracker-order-status-link" class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2 text-[11px] font-black text-white shadow-sm hover:bg-black">
                        Ver orden completa en Order Status
                    </a>
                </div>

                @if($selectedStatusItem)
                    <div class="mb-4 rounded-2xl border border-indigo-200 bg-indigo-50 p-4">
                        <p class="text-[10px] font-black uppercase tracking-widest text-indigo-500">Producto seleccionado</p>
                        <h4 class="mt-1 text-base font-black text-indigo-950">{{ $selectedStatusItem->part_name ?: $selectedStatusItem->product?->name }}</h4>
                        <div class="mt-3 grid gap-2 text-[11px] font-bold text-indigo-900 sm:grid-cols-4">
                            <span>PN: {{ $selectedStatusItem->part_number ?: '-' }}</span>
                            <span>Ordenado: {{ number_format((float) $selectedStatusItem->quantity_ordered, 0) }}</span>
                            <span>Recibido: {{ number_format((float) $selectedStatusItem->quantity_received, 0) }}</span>
                            <span>Entregado: {{ number_format((float) $selectedStatusItem->quantity_delivered, 0) }}</span>
                        </div>
                    </div>
                @endif

                <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
                    <table class="w-full text-left text-[11px]">
                        <thead class="border-b border-gray-100 bg-gray-50 text-[9px] font-black uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Producto</th>
                                <th class="px-4 py-3">Proveedor</th>
                                <th class="px-4 py-3 text-center">Cantidades</th>
                                <th class="px-4 py-3">Estado</th>
                                <th class="px-4 py-3">Avance</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @forelse($statusItems as $line)
                                @php
                                    $isSelectedLine = $selectedOrderStatusItemId === $line->id;
                                    $lineProgress = match (true) {
                                        (float) $line->quantity_delivered > 0 => 95,
                                        (float) $line->quantity_kardex_output > 0 => 86,
                                        (float) $line->quantity_kardex_entry > 0 => 74,
                                        (float) $line->quantity_received > 0 => 68,
                                        in_array($line->status, [\App\Services\OrderStatusService::STATUS_IN_TRANSIT, \App\Services\OrderStatusService::STATUS_IN_CUSTOMS], true) => 45,
                                        default => 20,
                                    };
                                @endphp
                                <tr class="{{ $isSelectedLine ? 'bg-indigo-50/70' : 'hover:bg-gray-50/60' }}">
                                    <td class="px-4 py-3">
                                        <p class="font-black text-slate-900">{{ $line->part_name ?: $line->product?->name ?: 'Producto' }}</p>
                                        <p class="mt-1 text-[10px] font-bold text-slate-400">{{ $line->part_number ?: 'Sin PN' }}{{ $line->brand ? ' · '.$line->brand : '' }}</p>
                                    </td>
                                    <td class="px-4 py-3 font-bold text-slate-600">{{ $line->supplier?->name ?: 'Sin proveedor' }}</td>
                                    <td class="px-4 py-3 text-center font-bold text-slate-700">Ord {{ number_format((float) $line->quantity_ordered, 0) }} / Rec {{ number_format((float) $line->quantity_received, 0) }} / Ent {{ number_format((float) $line->quantity_delivered, 0) }}</td>
                                    <td class="px-4 py-3"><span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[10px] font-black text-slate-700">{{ app(\App\Services\OrderStatusService::class)->humanStatus($line->status) }}</span></td>
                                    <td class="px-4 py-3">
                                        <div class="w-32">
                                            <div class="flex justify-between text-[9px] font-black text-slate-400"><span>Proceso</span><span>{{ $lineProgress }}%</span></div>
                                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-slate-900" style="width: {{ $lineProgress }}%"></div></div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-4 py-8 text-center text-xs font-bold text-slate-400">Esta orden aun no tiene partidas operativas en Order Status.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @else
            <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
                <table class="w-full text-left text-[11px]">
                    <thead class="bg-gray-50 text-[9px] font-black text-gray-500 uppercase tracking-wider border-b border-gray-100">
                        <tr>
                            @if($itemType === 'quote')
                                <th class="px-4 py-3 w-8 text-center">OK?</th>
                            @endif
                            <th class="px-4 py-3">PN</th>
                            <th class="px-4 py-3">Descripción</th>
                            <th class="px-4 py-3 text-center">Cant.</th>
                            @if($itemType === 'quote')
                                <th class="px-4 py-3 text-right">Costo Prov.</th>
                                <th class="px-4 py-3 text-right">Precio Venta</th>
                                <th class="px-4 py-3 text-center">Margen</th>
                            @else
                                <th class="px-4 py-3 text-right">Unitario</th>
                            @endif
                            <th class="px-4 py-3 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($selectedItem->items as $item)
                            @php
                                $cost = (float)($item->unit_cost ?? 0);
                                $price = (float)($item->unit_price ?? 0);
                                $profit = $price - $cost;
                                $margin = $price > 0 ? ($profit / $price) * 100 : 0;
                            @endphp
                            <tr class="hover:bg-gray-50/50 {{ $itemType === 'quote' && !$item->is_approved ? 'opacity-40' : '' }}">
                                @if($itemType === 'quote')
                                    <td class="px-4 py-3 text-center">
                                        @if($item->is_approved)
                                            <svg class="w-4 h-4 mx-auto text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        @else
                                            <span class="text-gray-300">-</span>
                                        @endif
                                    </td>
                                @endif
                                <td class="px-4 py-3 font-bold text-slate-800">{{ $item->part_number }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $item->description }}</td>
                                <td class="px-4 py-3 text-center font-bold">
                                    {{ $item->quantity }}
                                </td>

                                @if($itemType === 'quote')
                                    <td class="px-4 py-3 text-right text-[11px] text-amber-700">
                                        ${{ number_format($item->unit_cost, 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-[11px] font-black text-slate-900">
                                        ${{ number_format($item->unit_price, 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="text-[9px] font-black {{ $margin > 20 ? 'text-emerald-600' : 'text-amber-600' }}">
                                            {{ number_format($margin, 1) }}%
                                        </span>
                                    </td>
                                @else
                                    <td class="px-4 py-3 text-right font-bold text-slate-700">${{ number_format($item->unit_price, 2) }}</td>
                                @endif

                                <td class="px-4 py-3 text-right font-black text-slate-900">${{ number_format($item->line_total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            {{-- Notas / Incidencias --}}
            @if($itemNotes)
                <div class="mt-6">
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest">Incidencias / Notas {{ $itemType === 'quote' ? 'de la Oferta' : 'del Pedido' }}</label>
                        @if($this->itemNotesAuthor)
                            <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Última ed.: {{ $this->itemNotesAuthor->name }} · {{ $this->itemNotesAuthor->date }}</span>
                        @endif
                    </div>
                    <div class="w-full p-4 border border-gray-200 rounded-xl bg-gray-50/50 text-xs text-slate-700 whitespace-pre-wrap shadow-sm">
                        {{ $itemNotes }}
                    </div>
                </div>
            @endif

            @include('livewire.ventas.partials.order-tracker-timeline')
        </div>

        {{-- Modal Footer Maestro --}}
        <div class="p-6 border-t border-gray-100 bg-gray-50 flex items-center justify-between">
            <div class="flex items-center gap-3">
                @if($itemType === 'quote' && ($selectedItem->client_offer_pdf_path || $selectedItem->approvalRequest?->status === \App\Models\ApprovalRequest::STATUS_APPROVED))
                    <a href="{{ route('sales.quotations.pdf', $selectedItem->id) }}" target="_blank"
                       class="bg-slate-800 hover:bg-slate-900 text-white font-black px-4 py-2 rounded-xl shadow-lg flex items-center gap-2 transition-all text-[11px]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        Descargar PDF Oferta
                    </a>
                @endif
            </div>
        </div>
    </div>
</div>
