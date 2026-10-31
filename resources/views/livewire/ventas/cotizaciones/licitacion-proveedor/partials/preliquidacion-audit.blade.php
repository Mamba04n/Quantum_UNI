@php
    $auditEntries = filled($selectedSupplierId) ? \App\Models\ActivityLog::with('user')->where('action', 'preliquidation.saved')->where('metadata->quote_id', (int)$selectedQuote->id)->where('metadata->supplier_id', (int)$selectedSupplierId)->latest('id')->limit(10)->get() : collect();
    $settingLabels = ['transport' => 'Transporte', 'taxed' => 'Impuestos', 'handling' => 'Manejo USD/lb', 'freight' => 'Freight USD/lb', 'customs' => 'Desaduanaje USD', 'local_transport' => 'Transporte USD', 'margin' => 'Margen %', 'exchange' => 'Cambio C$/USD', 'commission' => 'Comisión %', 'financing' => 'Financiamiento %', 'iva' => 'IVA %', 'dai' => 'DAI %', 'isc' => 'ISC %'];
@endphp
<details class="preliq-disclosure"><summary>Cambios guardados del proveedor</summary>
    <div class="divide-y divide-gray-100 text-sm">
        @forelse($auditEntries as $entry)
            @php
                $before = $entry->metadata['before'] ?? [];
                $after = $entry->metadata['after'] ?? [];
                $oldItems = collect($before['items'] ?? [])->keyBy('id');
                $newItems = collect($after['items'] ?? [])->keyBy('id');
            @endphp
            <details class="py-2"><summary class="cursor-pointer">{{ $entry->created_at->format('d/m/Y H:i') }} · {{ $entry->user?->name ?? 'Usuario' }}</summary>
                <ul class="mt-2 space-y-1 text-gray-600">
                    @foreach($settingLabels as $field => $label)
                        @if(($before['settings']['settings'][$field] ?? null) != ($after['settings']['settings'][$field] ?? null))<li>{{ $label }}: {{ $before['settings']['settings'][$field] ?? '—' }} → {{ $after['settings']['settings'][$field] ?? '—' }}</li>@endif
                    @endforeach
                    @foreach($newItems as $id => $item)
                        @if(!$oldItems->has($id))<li>Agregó {{ $item['quoted_part_number'] }} · {{ $item['quoted_quantity'] }} unidades.</li>
                        @else
                            @foreach(['quoted_part_number' => 'Parte', 'quoted_description' => 'Descripción', 'quoted_quantity' => 'Cantidad', 'unit_cost' => 'Precio USD'] as $field => $label)
                                @if(($oldItems[$id][$field] ?? null) != ($item[$field] ?? null))<li>{{ $item['quoted_part_number'] }} · {{ $label }}: {{ $oldItems[$id][$field] ?? '—' }} → {{ $item[$field] ?? '—' }}</li>@endif
                            @endforeach
                        @endif
                        @if(($before['settings']['weights'][$id] ?? null) != ($after['settings']['weights'][$id] ?? null))<li>{{ $item['quoted_part_number'] }} · Peso lb: {{ $before['settings']['weights'][$id] ?? '—' }} → {{ $after['settings']['weights'][$id] ?? '—' }}</li>@endif
                    @endforeach
                    @foreach($oldItems as $id => $item)@if(!$newItems->has($id))<li>Eliminó {{ $item['quoted_part_number'] }}.</li>@endif @endforeach
                    @if(($before['notes'] ?? '') !== ($after['notes'] ?? ''))<li>Observaciones: {{ $before['notes'] ?? '—' }} → {{ $after['notes'] ?? '—' }}</li>@endif
                </ul>
            </details>
        @empty<p class="py-2 text-gray-500">Sin cambios registrados en esta versión.</p>@endforelse
    </div>
</details>
