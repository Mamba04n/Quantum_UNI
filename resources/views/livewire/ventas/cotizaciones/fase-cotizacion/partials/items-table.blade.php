@php
    $showCostColumn = ($currentStage ?? null) !== 'supplier_response';
@endphp

<section class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
    <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
        <div>
            <h3 class="text-sm font-semibold text-slate-900">Productos solicitados</h3>
            <p class="text-[11px] text-slate-500">Listado de productos incluidos en esta cotización.</p>
        </div>
    </div>
    <div class="max-h-96 overflow-auto">
        <table class="{{ $showCostColumn ? 'min-w-[900px]' : 'min-w-[780px]' }} w-full text-left text-sm">
            <thead class="border-b border-slate-200 bg-white text-[10px] font-black uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Pieza</th>
                    <th class="px-4 py-3">Referencia</th>
                    <th class="px-4 py-3">Marca / modelo</th>
                    <th class="px-4 py-3 text-right">Cant.</th>
                    <th class="px-4 py-3">Proveedor oferta</th>
                    @if($showCostColumn)
                        <th class="px-4 py-3 text-right">Costo</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($detailItems as $item)
                    @php
                        $selection = $item->supplierBidSelection;
                        $rfqDocuments = $selectedQuote->supplierRfqDocuments ?? collect();
                        $itemRfqDocument = $rfqDocuments
                            ->filter(function ($document) use ($item, $selectedQuote) {
                                $selectedItemIds = collect($document->selected_item_ids ?: [])
                                    ->map(fn ($id) => (int) $id)
                                    ->filter()
                                    ->values();

                                return $selectedItemIds->isNotEmpty()
                                    ? $selectedItemIds->contains((int) $item->id)
                                    : $selectedQuote->items->contains('id', $item->id);
                            })
                            ->sortByDesc(function ($document) {
                                return [
                                    \App\Models\SupplierRfqDocument::STATUS_SUPPLIER_RESPONSE_RECEIVED => 5,
                                    \App\Models\SupplierRfqDocument::STATUS_SENT_TO_SUPPLIER => 4,
                                    \App\Models\SupplierRfqDocument::STATUS_GENERATED => 3,
                                    \App\Models\SupplierRfqDocument::STATUS_ADMIN_APPROVED => 2,
                                    \App\Models\SupplierRfqDocument::STATUS_PENDING_ADMIN_REVIEW => 1,
                                    \App\Models\SupplierRfqDocument::STATUS_DRAFT => 0,
                                ][$document->status] ?? 0;
                            })
                            ->first();
                    @endphp
                    <tr>
                        <td class="px-4 py-3 font-black text-slate-900">{{ $item->part_name ?: $item->description ?: 'Item' }}</td>
                        <td class="px-4 py-3 font-bold text-slate-600">{{ $item->part_number ?: 'N/D' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $item->brand ?: '-' }} {{ $item->model ? '/ '.$item->model : '' }}</td>
                        <td class="px-4 py-3 text-right font-black">{{ number_format((float) $item->quantity, 0) }}</td>
                        <td class="px-4 py-3">
                            @if($selection)
                                <div class="font-bold text-slate-700">{{ $selection->supplier?->name ?: 'Proveedor seleccionado' }}</div>
                                <span class="mt-1 inline-flex rounded-full bg-emerald-50 px-2 py-1 text-[9px] font-black uppercase text-emerald-700">Seleccionado</span>
                            @elseif($itemRfqDocument)
                                <div class="font-bold text-slate-700">{{ $itemRfqDocument->supplier?->name ?: 'RFQ abierto' }}</div>
                                <span class="mt-1 inline-flex rounded-full px-2 py-1 text-[9px] font-black uppercase {{ match ($itemRfqDocument->status) {
                                    \App\Models\SupplierRfqDocument::STATUS_SUPPLIER_RESPONSE_RECEIVED,
                                    \App\Models\SupplierRfqDocument::STATUS_SENT_TO_SUPPLIER => 'bg-emerald-50 text-emerald-700',
                                    \App\Models\SupplierRfqDocument::STATUS_GENERATED => 'bg-amber-50 text-amber-700',
                                    default => 'bg-slate-100 text-slate-600',
                                } }}">{{ $itemRfqDocument->status_label }}</span>
                            @else
                                <span class="font-bold text-slate-400">Pendiente</span>
                            @endif
                        </td>
                        @if($showCostColumn)
                            <td class="px-4 py-3 text-right font-black text-slate-900">{{ $selection?->currency ?: 'USD' }} {{ number_format((float) ($selection?->selected_unit_cost ?? $item->unit_cost), 0) }}</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
