@if($reference || $customer)
    <section {{ $attributes->class(['border border-slate-300 bg-white shadow-sm', 'px-4 py-3' => $compact, 'px-5 py-4' => !$compact]) }}>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <dl class="grid min-w-0 gap-x-8 gap-y-2 sm:grid-cols-2">
                <div class="min-w-0">
                    <dt class="text-xs font-medium text-slate-500">{{ $isProvisional ? 'Expediente provisional' : 'Número de pedido / expediente' }}</dt>
                    <dd class="mt-0.5 truncate text-sm font-semibold text-slate-950">{{ $reference ?: 'Pendiente de asignación' }}</dd>
                </div>
                <div class="min-w-0">
                    <dt class="text-xs font-medium text-slate-500">Cliente del expediente</dt>
                    <dd class="mt-0.5 truncate text-sm font-semibold text-slate-950">{{ $customer ?: 'No indicado' }}</dd>
                </div>
            </dl>
            @if($url)
                <a href="{{ $url }}" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50">
                    Ver expediente completo
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
                </a>
            @endif
        </div>
    </section>
@endif
