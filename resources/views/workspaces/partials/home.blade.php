@php
    $startItems = array_intersect_key($allItems, array_flip(['sales.customer-request.create', 'sales.direct-orders.create', 'purchases.create']));
    $pending = array_values(array_filter($metrics, fn ($metric) => $metric['pending'] && $metric['value'] > 0));
    $webPendingQuotesCount = app(\App\Support\WorkspaceNavigation::class)->allows(auth()->user(), 'sales.quotations.licitacion') ? \App\Models\QuotationRequest::where('source_channel', 'web')
        ->whereIn('status', [\App\Models\QuotationRequest::STATUS_RECEIVED, \App\Models\QuotationRequest::STATUS_IN_REVIEW])
        ->whereNull('sale_order_id')
        ->count() : 0;
@endphp

<div class="space-y-6" data-testid="home-workbench">
    @if($webPendingQuotesCount > 0)
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 rounded-2xl border border-emerald-200 bg-emerald-50/80 p-4 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-600 text-white shadow-sm">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0a3 3 0 01-6 0"/>
                    </svg>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <p class="text-sm font-bold text-emerald-950">
                            {{ $webPendingQuotesCount === 1 ? '1 nueva solicitud de cotización recibida desde la web' : "{$webPendingQuotesCount} nuevas solicitudes de cotización recibidas desde la web" }}
                        </p>
                        <span class="inline-flex items-center rounded-md bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">Pendiente</span>
                    </div>
                    <p class="text-xs text-emerald-800">
                        Los clientes han solicitado cotización desde la tienda online. Requieren licitación a proveedores.
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <a href="{{ route('sales.quotations.licitacion') }}" class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-slate-800 px-4 py-2.5 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-slate-900">
                    Atender en Licitación
                    <x-workspace-icon name="arrow" class="h-3.5 w-3.5"/>
                </a>
            </div>
        </div>
    @endif

    {{-- Áreas de trabajo --}}
    <section aria-labelledby="areas-heading">
        <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
            <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-gray-100 pb-4">
                <div>
                    <h2 id="areas-heading" class="text-base font-semibold text-gray-900">Áreas de trabajo</h2>
                    <p class="mt-0.5 text-xs text-gray-500">Módulos y procesos organizados por área operativa del negocio.</p>
                </div>
                @if($startItems)
                    <div class="flex flex-wrap items-center gap-2" aria-label="Iniciar una operación">
                        @foreach($startItems as $destination => [$label, $description])
                            <a href="{{ route($destination) }}" class="inline-flex items-center justify-center gap-1.5 rounded-xl px-3.5 py-2 text-xs font-semibold transition-colors {{ $loop->first ? 'bg-slate-800 text-white shadow-sm hover:bg-slate-900' : 'border border-gray-200 bg-white text-slate-700 hover:bg-gray-50' }}">
                                <span aria-hidden="true">+</span>{{ $label }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="grid gap-x-6 gap-y-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach($areas as $key => $workspace)
                    @php
                        $itemLabels = implode(' ', array_map(fn($it) => $it[0].' '.$it[1], $workspace['items']));
                    @endphp
                    <div x-show="!search.trim() || matches(@js($workspace['title'].' '.$itemLabels))" class="flex flex-col">
                        <a href="{{ route('workspace', $key) }}" class="group mb-2 flex items-center gap-2 rounded-lg px-2.5 py-2 text-sm font-semibold bg-slate-50 text-[#6f1220] transition-colors hover:bg-rose-50 hover:text-[#560e19]">
                            <x-workspace-icon :name="config('workspace-presentation.icons.'.$key, 'grid')" class="h-4 w-4 shrink-0 text-[#6f1220]"/>
                            <span class="flex-1 truncate">{{ $workspace['title'] }}</span>
                            <x-workspace-icon name="arrow" class="h-3.5 w-3.5 text-slate-400 opacity-60 transition-transform group-hover:translate-x-0.5"/>
                        </a>
                        <p class="mb-2 px-2 text-xs text-gray-500 leading-relaxed">{{ $workspace['description'] }}</p>
                        <ul class="space-y-1">
                            @foreach($workspace['items'] as $destination => [$label, $desc])
                                <li x-show="!search.trim() || matches(@js($workspace['title'].' '.$label.' '.$desc))">
                                    <a href="{{ route($destination) }}" class="block rounded-lg px-2 py-1.5 text-sm text-slate-600 transition-colors hover:bg-slate-50 hover:text-slate-900">
                                        {{ $label }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Continuar trabajando y Requiere atención --}}
    <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <section aria-labelledby="resume-heading" class="min-w-0">
            <div class="mb-3 flex items-center justify-between">
                <h2 id="resume-heading" class="text-base font-semibold text-gray-900">Continuar trabajando</h2>
                <span class="text-xs text-gray-500">Cotizaciones recientes</span>
            </div>
            <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full whitespace-nowrap text-left text-sm" data-testid="home-current-work">
                        <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                            <tr>@foreach(['Expediente', 'Cliente', 'Fase actual', 'Siguiente acción'] as $label)<th scope="col" class="px-5 py-3 font-semibold">{{ $label }}</th>@endforeach</tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($recentQuotes as $recent)
                                <tr class="transition-colors hover:bg-gray-50/50">
                                    <td class="px-5 py-3"><a href="{{ $recent['url'] }}" class="font-semibold text-gray-900 underline-offset-4 hover:underline">{{ $recent['reference'] }}</a></td>
                                    <td class="px-5 py-3 text-gray-600">{{ $recent['client'] }}</td>
                                    <td class="px-5 py-3 text-gray-600">{{ $recent['stage'] }}</td>
                                    <td class="px-5 py-3"><a href="{{ $recent['url'] }}" class="inline-flex items-center gap-2 font-semibold text-[#6f1220] underline-offset-4 hover:underline">{{ $recent['action'] }}<x-workspace-icon name="arrow" class="h-4 w-4"/></a></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-5 py-4 text-sm text-gray-500">No tienes cotizaciones en curso. Inicia una operación o entra a un área.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        @if($pending)
            <section aria-labelledby="home-pending-heading" class="min-w-0">
                <div class="mb-3 flex items-center justify-between">
                    <h2 id="home-pending-heading" class="text-base font-semibold text-gray-900">Requiere atención</h2>
                    <span class="text-xs text-amber-600 font-medium">Pendientes</span>
                </div>
                <ul class="divide-y divide-gray-100 rounded-2xl border border-gray-100 bg-white shadow-sm">
                    @foreach($pending as $metric)
                        <li><a href="{{ route($metric['route']) }}" class="flex items-center gap-3 px-5 py-3 text-sm transition-colors hover:bg-gray-50/50"><span class="font-semibold tabular-nums text-amber-700">{{ number_format($metric['value']) }}</span><span class="min-w-0 flex-1 text-gray-700">{{ $metric['label'] }}</span><x-workspace-icon name="arrow" class="h-4 w-4 text-slate-400"/></a></li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</div>
