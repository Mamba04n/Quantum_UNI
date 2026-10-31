<div class="space-y-6 p-6 font-sans sm:p-8">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div><h1 class="text-2xl font-extrabold tracking-tight text-gray-900">Explorador</h1><p class="mt-0.5 text-sm text-gray-500">Consulta referencias y revisa su contexto operativo sin recorrer cada módulo.</p></div>
        @if($detail)<button type="button" wire:click="clearSelection" class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>Volver a resultados</button>@endif
    </header>

    <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_280px]">
            <label><span class="mb-1.5 block text-xs font-semibold text-slate-600">Buscar en el ERP</span><div class="relative"><svg class="absolute left-3 top-2.5 h-5 w-5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><input type="search" wire:model.live.debounce.350ms="search" class="h-10 w-full rounded-xl border-transparent bg-gray-50 pl-10 pr-4 text-sm text-slate-900 transition-all focus:bg-white focus:ring-2 focus:ring-slate-200" placeholder="Referencia, cliente, proveedor, producto o factura"></div></label>
            <label><span class="mb-1.5 block text-xs font-semibold text-slate-600">Tipo de registro</span><select wire:model.live="type" class="h-10 w-full rounded-xl border-slate-200 bg-white text-sm text-slate-800 focus:border-slate-400 focus:ring-slate-200"><option value="all">Todos los registros</option>@foreach($types as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
        </div>
        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3 text-xs text-slate-500">
            <p>Busca por código, nombre, número de parte, documento o referencia relacionada.</p>
            @if(!$detail && (mb_strlen(trim($search)) >= 2 || $type !== 'all'))<p class="font-semibold text-slate-700">{{ $results->count() }} {{ $results->count() === 1 ? 'resultado' : 'resultados' }}</p>@endif
        </div>
    </section>

    @if($detail)
        <article class="space-y-6">
            @if($detail['case'])
                <section class="flex flex-col gap-4 border border-slate-300 bg-slate-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-xs font-medium text-slate-500">{{ $detail['case']['isCurrent'] ? 'Expediente principal' : 'Este registro pertenece al pedido' }}</p>
                        <p class="mt-1 truncate text-base font-semibold text-slate-950">{{ $detail['case']['reference'] }}</p>
                        <p class="mt-0.5 truncate text-sm text-slate-600">Cliente: {{ $detail['case']['customer'] }}@if($detail['case']['status']) · {{ str($detail['case']['status'])->replace('_', ' ')->headline() }}@endif</p>
                    </div>
                    @if(!$detail['case']['isCurrent'])
                        <button type="button" wire:click="selectResult('tracker', {{ $detail['case']['id'] }})" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-md bg-slate-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800">
                            Abrir pedido completo
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
                        </button>
                    @endif
                </section>
            @endif
            <section class="border border-slate-200 bg-white">
                <div class="flex flex-col gap-5 border-b border-slate-200 px-6 py-6 md:flex-row md:items-start md:justify-between">
                    <div><p class="text-sm font-medium text-slate-500">{{ $detail['category'] }}</p><h2 class="mt-1 text-3xl font-semibold tracking-tight text-slate-950">{{ $detail['reference'] ?: 'Sin referencia' }}</h2><p class="mt-2 text-sm text-slate-600">{{ $detail['title'] ?: 'Sin descripción' }}</p></div>
                    <div class="flex shrink-0 flex-col items-start gap-3 md:items-end md:text-right">
                        <div><p class="text-xs font-medium text-slate-500">Estado actual</p><p class="mt-1 text-base font-semibold text-slate-900">{{ $detail['status'] ?: 'Sin estado' }}</p>@if($detail['date'])<p class="mt-1 text-sm text-slate-500">{{ $detail['date'] }}</p>@endif</div>
                        @if($detail['moduleUrl'])
                            <a href="{{ $detail['moduleUrl'] }}" wire:navigate class="inline-flex items-center gap-2 rounded-md bg-slate-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800">
                                {{ $detail['moduleLabel'] }}
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
                            </a>
                        @endif
                    </div>
                </div>
                <dl class="grid grid-cols-2 divide-x divide-y divide-slate-200 md:grid-cols-4 md:divide-y-0">@foreach($detail['facts'] as $fact)<div class="px-5 py-4"><dt class="text-xs text-slate-500">{{ $fact['label'] }}</dt><dd class="mt-1 text-sm font-semibold text-slate-900">{{ filled($fact['value']) ? $fact['value'] : 'No indicado' }}</dd></div>@endforeach</dl>
            </section>

            @if(count($detail['profile']))
                <section class="border border-slate-200 bg-white">
                    <div class="border-b border-slate-200 px-6 py-4">
                        <h3 class="text-sm font-semibold text-slate-900">Información del cliente</h3>
                        <p class="mt-1 text-xs text-slate-500">Datos de identificación, contacto y situación comercial.</p>
                    </div>
                    <dl class="grid gap-x-8 px-6 py-2 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach($detail['profile'] as $field)
                            <div class="border-b border-slate-100 py-4">
                                <dt class="text-xs text-slate-500">{{ $field['label'] }}</dt>
                                <dd class="mt-1 break-words text-sm font-medium text-slate-900">{{ filled($field['value']) ? $field['value'] : 'No indicado' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            @endif

            @if(count($detail['cases']))
                <section class="border border-slate-200 bg-white">
                    <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900">Expedientes del cliente</h3>
                            <p class="mt-1 text-xs text-slate-500">Pedidos y procesos comerciales reunidos bajo este perfil.</p>
                        </div>
                        <span class="text-sm text-slate-500">{{ count($detail['cases']) }}</span>
                    </div>
                    <div class="divide-y divide-slate-100">
                        @foreach($detail['cases'] as $case)
                            <button type="button" wire:click="selectResult('{{ $case['type'] }}', {{ $case['id'] }})" class="grid w-full gap-2 px-6 py-4 text-left hover:bg-slate-50 sm:grid-cols-[minmax(0,1fr)_180px_120px_24px] sm:items-center">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-slate-900">{{ $case['reference'] }}</p>
                                    <p class="mt-0.5 truncate text-xs text-slate-500">{{ $case['description'] }}</p>
                                </div>
                                <span class="text-sm text-slate-600">{{ $case['status'] ?: 'Sin estado' }}</span>
                                <time class="text-xs text-slate-500">{{ $case['date'] ?: 'Sin fecha' }}</time>
                                <svg class="hidden h-4 w-4 text-slate-400 sm:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
                            </button>
                        @endforeach
                    </div>
                </section>
            @endif

            @if(count($detail['steps']))
                <section class="border border-slate-200 bg-white px-6 py-6"><h3 class="text-sm font-semibold text-slate-900">Avance del proceso</h3><div class="relative mt-6 flex flex-col justify-between gap-5 md:flex-row"><div class="absolute left-5 right-5 top-4 hidden h-px bg-slate-200 md:block"></div>@foreach($detail['steps'] as $step)<div class="relative flex flex-1 items-center gap-3 md:flex-col md:text-center"><span class="relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full border {{ $step['complete'] ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 bg-white text-slate-400' }}">@if($step['complete'])<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 12 4 4 8-8"/></svg>@else<span class="h-1.5 w-1.5 rounded-full bg-current"></span>@endif</span><p class="text-xs font-medium {{ $step['current'] ? 'text-slate-950' : 'text-slate-500' }}">{{ $step['label'] }}</p></div>@endforeach</div></section>
            @endif

            @if(count($detail['items']))
                <section class="overflow-hidden border border-slate-200 bg-white">
                    <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4"><div><h3 class="text-sm font-semibold text-slate-900">Partidas y repuestos</h3><p class="mt-1 text-xs text-slate-500">Detalle comercial y operativo asociado al registro.</p></div><span class="text-sm text-slate-500">{{ count($detail['items']) }} partidas</span></div>
                    <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="border-b border-slate-200 bg-slate-50 text-xs font-medium text-slate-600"><tr><th class="px-5 py-3">Número de parte</th><th class="px-5 py-3">Descripción</th><th class="px-5 py-3 text-right">Cantidad</th><th class="px-5 py-3 text-right">Precio unitario</th><th class="px-5 py-3 text-right">Total</th><th class="px-5 py-3">Estado</th></tr></thead><tbody class="divide-y divide-slate-100 text-slate-700">@foreach($detail['items'] as $item)<tr><td class="whitespace-nowrap px-5 py-3 font-mono text-xs text-slate-900">{{ $item['partNumber'] ?: 'Sin número' }}</td><td class="px-5 py-3">{{ $item['description'] ?: 'Sin descripción' }}</td><td class="px-5 py-3 text-right">{{ $item['quantity'] }}</td><td class="px-5 py-3 text-right">{{ $item['currency'] }} {{ number_format((float)$item['unitPrice'], 2) }}</td><td class="px-5 py-3 text-right font-semibold text-slate-900">{{ $item['currency'] }} {{ number_format((float)$item['total'], 2) }}</td><td class="px-5 py-3 text-xs">{{ $item['status'] ?: 'Sin estado' }}</td></tr>@endforeach</tbody>@if($detail['amount'] !== null)<tfoot class="border-t border-slate-300 bg-slate-900 text-white"><tr><td colspan="4" class="px-5 py-4 text-right text-sm">Monto total</td><td class="px-5 py-4 text-right text-lg font-semibold">{{ $detail['currency'] }} {{ number_format((float)$detail['amount'], 2) }}</td><td></td></tr></tfoot>@endif</table></div>
                </section>
            @endif

            <div class="grid gap-6 lg:grid-cols-12">
                <section class="border border-slate-200 bg-white lg:col-span-7"><div class="border-b border-slate-200 px-5 py-4"><h3 class="text-sm font-semibold text-slate-900">Actividad</h3></div><div class="divide-y divide-slate-100 px-5">@forelse($detail['activities'] as $activity)<div class="relative py-4 pl-5 before:absolute before:left-0 before:top-5 before:h-2 before:w-2 before:rounded-full before:bg-slate-700"><div class="flex items-start justify-between gap-4"><p class="text-sm font-medium text-slate-900">{{ $activity['title'] }}</p><time class="shrink-0 text-xs text-slate-500">{{ $activity['date'] }}</time></div><p class="mt-1 text-sm text-slate-600">{{ $activity['description'] }}</p><p class="mt-1 text-xs text-slate-500">{{ $activity['phase'] }} · {{ $activity['user'] }}</p></div>@empty<p class="py-6 text-sm text-slate-500">Todavía no hay actividades registradas.</p>@endforelse</div></section>
                <div class="space-y-6 lg:col-span-5">
                    <section class="border border-slate-200 bg-white"><div class="border-b border-slate-200 px-5 py-4"><h3 class="text-sm font-semibold text-slate-900">Registros relacionados</h3></div><div class="divide-y divide-slate-100">@forelse($detail['related'] as $related)@if(isset($related['type'], $related['id']))<button type="button" wire:click="selectResult('{{ $related['type'] }}', {{ $related['id'] }})" class="flex w-full items-center justify-between gap-4 px-5 py-3 text-left hover:bg-slate-50">@else<div class="flex items-center justify-between gap-4 px-5 py-3">@endif<div class="min-w-0"><p class="text-xs text-slate-500">{{ $related['label'] }}</p><p class="truncate text-sm font-medium text-slate-900">{{ $related['reference'] ?: 'Sin referencia' }}</p>@if($related['description'])<p class="truncate text-xs text-slate-600">{{ $related['description'] }}</p>@endif</div><div class="flex shrink-0 items-center gap-2">@if($related['status'])<span class="text-xs text-slate-500">{{ $related['status'] }}</span>@endif@if(isset($related['type'], $related['id']))<svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>@endif</div>@if(isset($related['type'], $related['id']))</button>@else</div>@endif@empty<p class="px-5 py-5 text-sm text-slate-500">Sin relaciones visibles para tu perfil.</p>@endforelse</div></section>
                    <section class="border border-slate-200 bg-white">
                        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                            <div><h3 class="text-sm font-semibold text-slate-900">Documentos y adjuntos</h3><p class="mt-1 text-xs text-slate-500">Archivos reunidos de todas las fases del expediente.</p></div>
                            <span class="text-xs text-slate-500">{{ count($detail['documents']) }}</span>
                        </div>
                        <div>
                            @forelse(collect($detail['documents'])->groupBy('phase') as $phase => $phaseDocuments)
                                <div class="border-b border-slate-200 bg-slate-50 px-5 py-2.5">
                                    <p class="text-xs font-semibold text-slate-700">{{ $phase }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500">{{ $phaseDocuments->count() }} {{ $phaseDocuments->count() === 1 ? 'archivo' : 'archivos' }}</p>
                                </div>
                                @foreach($phaseDocuments as $document)
                                <div class="border-b border-slate-100 px-5 py-4 last:border-b-0">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="flex min-w-0 gap-3">
                                            @if($document['isImage'])
                                                <a href="{{ $document['viewUrl'] }}" target="_blank" rel="noopener" class="block h-12 w-12 shrink-0 overflow-hidden rounded-md border border-slate-200 bg-slate-50" aria-label="Visualizar {{ $document['name'] }}">
                                                    <img src="{{ $document['viewUrl'] }}" alt="" class="h-full w-full object-cover">
                                                </a>
                                            @else
                                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md border border-slate-200 bg-slate-50 text-slate-500"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7 3h7l4 4v14H7z"/><path d="M14 3v5h5"/></svg></span>
                                            @endif
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-medium text-slate-900">{{ $document['name'] }}</p>
                                                <p class="mt-0.5 text-xs text-slate-500">{{ $document['type'] }}</p>
                                                @if($document['notes'])<p class="mt-1 line-clamp-2 text-xs text-slate-600">{{ $document['notes'] }}</p>@endif
                                                <p class="mt-1 text-xs text-slate-400">{{ $document['date'] }}@if($document['user']) · {{ $document['user'] }}@endif</p>
                                            </div>
                                        </div>
                                        <div class="flex shrink-0 items-center gap-2">
                                            <a href="{{ $document['viewUrl'] }}" target="_blank" rel="noopener" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Visualizar</a>
                                            <a href="{{ $document['downloadUrl'] }}" class="rounded-md px-2 py-1.5 text-xs font-medium text-slate-500 hover:bg-slate-50 hover:text-slate-800">Descargar</a>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            @empty
                                <p class="px-5 py-5 text-sm text-slate-500">No hay documentos o adjuntos asociados.</p>
                            @endforelse
                        </div>
                    </section>
                    <section class="border border-slate-200 bg-white">
                        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4"><h3 class="text-sm font-semibold text-slate-900">Comentarios internos</h3><span class="text-xs text-slate-500">{{ count($detail['comments']) }}</span></div>
                        <div class="divide-y divide-slate-100 px-5">
                            @forelse($detail['comments'] as $comment)
                                <div class="py-4"><div class="flex items-start justify-between gap-3"><p class="text-xs font-medium text-slate-600">{{ $comment['context'] }}</p><time class="shrink-0 text-xs text-slate-400">{{ $comment['date'] }}</time></div><p class="mt-1 whitespace-pre-wrap text-sm leading-5 text-slate-800">{{ $comment['text'] }}</p><p class="mt-1 text-xs text-slate-500">{{ $comment['author'] }}</p></div>
                            @empty
                                <p class="py-5 text-sm text-slate-500">No hay comentarios internos asociados.</p>
                            @endforelse
                        </div>
                    </section>
                </div>
            </div>
            @if($detail['notes'])<section class="border border-slate-200 bg-slate-50 px-5 py-4"><h3 class="text-sm font-semibold text-slate-900">Notas operativas</h3><p class="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-700">{{ $detail['notes'] }}</p></section>@endif
        </article>
    @elseif(mb_strlen(trim($search)) < 2 && $type === 'all')
        <div class="border border-slate-200 bg-slate-50 px-5 py-8 text-center"><p class="text-sm font-medium text-slate-700">Escribe al menos dos caracteres para iniciar la búsqueda.</p><p class="mt-1 text-sm text-slate-500">Puedes usar el número de orden, solicitud, parte, factura o el nombre del cliente o proveedor.</p></div>
    @else
        <section class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
            <div class="flex flex-col gap-2 border-b border-gray-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="text-sm font-semibold text-gray-900">Resultados</h2><p class="mt-0.5 text-xs text-gray-500">Selecciona un registro para consultar su expediente, documentos y relaciones.</p></div><span class="text-xs font-semibold text-gray-500">Mostrando {{ $results->count() }}</span></div>
            <div class="overflow-x-auto">
                <div class="hidden grid-cols-[150px_220px_minmax(280px,1fr)_170px_130px_44px] border-b border-gray-100 bg-gray-50 px-5 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 md:grid">
                    <span>Tipo</span><span>Referencia</span><span>Registro</span><span>Estado</span><span>Actualizado</span><span class="sr-only">Abrir</span>
                </div>
                <div class="divide-y divide-gray-50">
                    @forelse($results as $result)
                        @php
                            $statusText = strtolower((string) ($result['status'] ?? ''));
                            $statusClasses = str_contains($statusText, 'activ') || str_contains($statusText, 'complet') || str_contains($statusText, 'aprobad') || str_contains($statusText, 'pagad')
                                ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                : (str_contains($statusText, 'cancel') || str_contains($statusText, 'rechaz') || str_contains($statusText, 'inactiv')
                                    ? 'border-rose-200 bg-rose-50 text-rose-700'
                                    : (str_contains($statusText, 'pend') || str_contains($statusText, 'revisi')
                                        ? 'border-amber-200 bg-amber-50 text-amber-800'
                                        : 'border-slate-200 bg-slate-50 text-slate-600'));
                        @endphp
                        <button type="button" wire:click="selectResult('{{ $result['type'] }}', {{ $result['id'] }})" class="group grid w-full gap-2 px-5 py-3 text-left transition-colors hover:bg-gray-50/50 md:grid-cols-[150px_220px_minmax(280px,1fr)_170px_130px_44px] md:items-center">
                            <span class="text-xs font-semibold text-slate-500">{{ $result['category'] }}</span>
                            <span class="truncate font-mono text-sm font-semibold text-slate-900" title="{{ $result['reference'] ?: 'Sin referencia' }}">{{ $result['reference'] ?: 'Sin referencia' }}</span>
                            <span class="min-w-0"><span class="block truncate text-sm font-medium text-slate-800">{{ $result['title'] ?: 'Sin descripción' }}</span><span class="mt-0.5 block text-xs text-slate-400 md:hidden">Actualizado {{ $result['dateLabel'] ?: 'sin fecha' }}</span></span>
                            <span><span class="inline-flex rounded-md border px-2 py-1 text-xs font-semibold {{ $statusClasses }}">{{ $result['status'] ?: 'Sin estado' }}</span></span>
                            <time class="hidden text-xs text-slate-500 md:block">{{ $result['dateLabel'] ?: 'Sin fecha' }}</time>
                            <span class="hidden h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition group-hover:bg-white group-hover:text-slate-700 md:inline-flex"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg></span>
                        </button>
                    @empty
                        <div class="px-5 py-10 text-center"><p class="text-sm font-medium text-slate-700">No se encontraron coincidencias.</p><p class="mt-1 text-sm text-slate-500">Prueba con otra referencia o cambia el tipo de registro.</p></div>
                    @endforelse
                </div>
            </div>
        </section>
    @endif
</div>
