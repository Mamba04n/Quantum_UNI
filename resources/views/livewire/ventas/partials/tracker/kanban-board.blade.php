<div class="flex overflow-x-auto gap-4 pb-8 items-start scrollbar-hide">
    @foreach($stages as $stageKey => $stageInfo)
        <div class="flex-shrink-0 w-72 bg-gray-200/40 rounded-2xl border border-gray-200 shadow-sm overflow-hidden flex flex-col max-h-[85vh]">
            {{-- Encabezado de columna --}}
            <div class="p-3 {{ $stageInfo['header'] }} border-b border-gray-200 flex items-center justify-between sticky top-0 z-10">
                <h3 class="text-[10px] font-black text-gray-700 uppercase tracking-widest">{{ $stageInfo['label'] }}</h3>
                <span class="bg-white/50 text-gray-700 text-[10px] font-bold px-2 py-0.5 rounded-full border border-gray-300">
                    {{ count($board[$stageKey]) }}
                </span>
            </div>

            {{-- Contenedor de tarjetas --}}
            <div class="p-2 space-y-3 overflow-y-auto flex-1 min-h-[150px]">
                @foreach($board[$stageKey] as $item)
                    @php
                        $data = $item['data'];
                        $type = $item['type'];
                        $trackedProduct = $item['order_status_item'] ?? null;
                        $progress = $item['progress'] ?? 0;
                    @endphp
                    <div wire:key="kanban-{{ $type }}-{{ $item['id'] }}-{{ $trackedProduct?->id ?? 0 }}"
                         wire:click="$dispatch('open-tracker-details', { id: {{ $item['id'] }}, type: '{{ $type }}', orderStatusItemId: {{ $trackedProduct?->id ?? 'null' }} })"
                         class="bg-white p-3 rounded-xl border border-gray-100 shadow-sm hover:shadow-md hover:border-indigo-300 transition-all cursor-pointer group relative">

                        {{-- Badge de Tipo --}}
                        <div class="absolute -top-1 -right-1">
                            <span class="text-[8px] font-black px-1.5 py-0.5 rounded-full border bg-white text-slate-600 border-slate-200">
                                {{ $type === 'quote' ? 'OFERTA' : 'PRODUCTO' }}
                            </span>
                        </div>

                        <div class="flex justify-between items-start mb-2">
                            <span class="text-[9px] font-black text-slate-800 tracking-tighter">
                                #{{ $data->reference }}
                            </span>
                            <span class="text-[9px] font-bold text-gray-400 italic">
                                {{ $data->updated_at->diffForHumans(null, true) }}
                            </span>
                        </div>

                        <h4 class="text-[11px] font-bold text-gray-800 line-clamp-2 leading-tight mb-2">
                            {{ $trackedProduct ? ($trackedProduct->part_name ?: $trackedProduct->product?->name ?: 'Producto') : ($data->quote_to_name ?? $data->client_name) }}
                        </h4>

                        <div class="text-[10px] text-gray-500 line-clamp-1 border-t border-gray-50 pt-2">
                            @if($trackedProduct)
                                {{ $trackedProduct->part_number ?: 'Sin PN' }} · {{ $data->client_name }}
                            @else
                                {{ $type === 'quote' ? ($data->items->first()->description ?? '...') : ($data->items->first()->description ?? '...') }}
                            @endif
                        </div>

                        @if($trackedProduct)
                            <div class="mt-3 space-y-1">
                                <div class="flex items-center justify-between text-[9px] font-black uppercase text-slate-400">
                                    <span>{{ app(\App\Services\OrderStatusService::class)->humanStatus($trackedProduct->status) }}</span>
                                    <span>{{ $progress }}%</span>
                                </div>
                                <div class="h-1.5 overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full bg-slate-900" style="width: {{ min(100, max(0, $progress)) }}%"></div>
                                </div>
                                <div class="grid grid-cols-3 gap-1 text-center text-[9px] font-bold text-slate-500">
                                    <span>Ord {{ number_format((float) $trackedProduct->quantity_ordered, 0) }}</span>
                                    <span>Rec {{ number_format((float) $trackedProduct->quantity_received, 0) }}</span>
                                    <span>Ent {{ number_format((float) $trackedProduct->quantity_delivered, 0) }}</span>
                                </div>
                            </div>
                        @endif

                        <div class="mt-2 flex items-center justify-between">
                            <div class="flex -space-x-1">
                                <div class="w-5 h-5 rounded-full bg-slate-100 border border-white text-[8px] flex items-center justify-center font-black text-slate-500 uppercase">
                                {{ mb_strtoupper(mb_substr(trim($data->quote_to_name ?? $data->client_name), 0, 1, 'UTF-8'), 'UTF-8') }}
                            </div>
                        </div>
                        <div class="text-[10px] font-black text-slate-900">
                                {{ $trackedProduct ? ($trackedProduct->supplier?->name ?: 'Sin proveedor') : '$'.number_format($data->total_amount, 2) }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
