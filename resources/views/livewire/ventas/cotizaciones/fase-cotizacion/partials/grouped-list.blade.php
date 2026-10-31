@foreach($flowStages as $stageKey => $stage)
    @php
        $rows = $kanbanColumns[$stageKey];
        $tone = match ($stageKey) {
            'supplier_bid' => 'bg-sky-50 text-sky-800',
            'supplier_response' => 'bg-amber-50 text-amber-800',
            'client_offer' => 'bg-violet-50 text-violet-800',
            'customer_approval' => 'bg-rose-50 text-rose-800',
            'purchase_preparation' => 'bg-teal-50 text-teal-800',
            default => 'bg-slate-100 text-slate-700',
        };
    @endphp
    <details open wire:key="list-stage-{{ $stageKey }}" class="group min-w-0" data-testid="list-stage-{{ $stageKey }}">
        <summary class="mb-2 w-fit cursor-pointer rounded-lg px-2 py-2 text-sm text-slate-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-slate-500">
            <h2 class="inline rounded-md px-2 py-1 text-sm font-semibold {{ $tone }}">{{ $stage['label'] }}</h2>
            <span class="ml-2 text-sm text-slate-500">{{ $rows->count() }}<span class="sr-only"> solicitudes</span></span>
        </summary>
        <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
            <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Solicitudes de {{ $stage['label'] }}">
                <table class="w-full whitespace-nowrap text-left text-sm">
                    <caption class="sr-only">{{ $stage['label'] }}</caption>
                    <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                        <tr>
                            @foreach(['Código', 'Solicitud / Cliente', 'Responsable', 'Fecha límite', 'Prioridad', 'Partidas'] as $label)
                                <th scope="col" class="px-5 py-3 font-semibold">{{ $label }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($rows as $quote)
                            @php
                                $item = $quote->items->first();
                                $title = $item?->part_name ?: ($item?->description ?: ($quote->machinery ?: 'Solicitud de cotización'));
                                $owner = $quote->user?->name ?: 'Sin asignar';
                                $priority = mb_strtolower($quote->request_urgency ?? '');
                                $priorityLabel = match($priority) { 'alta', 'urgente' => 'Alta', 'critica', 'crítica' => 'Crítica', 'media', 'normal' => 'Media', 'baja' => 'Baja', default => 'Sin definir' };
                                $priorityTone = match($priorityLabel) { 'Alta', 'Crítica' => 'text-rose-700 bg-rose-50', 'Media' => 'text-amber-800 bg-amber-50', default => 'text-slate-600 bg-slate-100' };
                            @endphp
                            <tr wire:key="list-quote-{{ $quote->id }}" class="transition-colors hover:bg-gray-50/50" data-testid="list-quote-{{ $quote->id }}">
                                <td class="px-5 py-3">
                                    <a href="{{ $this->getQuoteUrl($quote) }}" aria-label="Abrir cotización {{ $quote->reference }}" class="rounded font-semibold text-slate-800 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-slate-500">{{ $quote->reference }}</a>
                                </td>
                                <td class="w-full min-w-64 px-5 py-3">
                                    <a href="{{ $this->getQuoteUrl($quote) }}" class="block rounded font-semibold text-gray-900 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-slate-500">{{ \Illuminate\Support\Str::limit($title, 90) }}</a>
                                    <p class="mt-0.5 text-sm text-gray-500">{{ $quote->quote_to_name ?: 'Sin cliente' }}</p>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="flex items-center gap-2">
                                        <span aria-hidden="true" class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600">{{ mb_strtoupper(mb_substr($owner, 0, 2)) }}</span>
                                        <span class="text-gray-600">{{ $owner }}</span>
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-gray-600">
                                    @if($quote->delivery_date)
                                        <time datetime="{{ $quote->delivery_date->toDateString() }}">{{ $quote->delivery_date->format('d/m/Y') }}</time>
                                    @else
                                        <span class="text-gray-500">Sin fecha</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3"><span class="inline-flex rounded px-2 py-1 text-xs font-semibold {{ $priorityTone }}">{{ $priorityLabel }}</span></td>
                                <td class="px-5 py-3 tabular-nums text-gray-600">{{ $quote->items->count() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-5 text-sm text-gray-500">Sin solicitudes en esta etapa.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </details>
@endforeach

