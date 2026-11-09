@php
    $startItems = array_intersect_key($allItems, array_flip(['sales.customer-request.create', 'sales.direct-orders.create', 'purchases.create']));
    $pending = array_values(array_filter($metrics, fn ($metric) => $metric['pending'] && $metric['value'] > 0));
@endphp

<div class="space-y-8" data-testid="home-workbench">
    {{-- SECCIÓN: ÁREAS DISPONIBLES COMO TARJETAS CON ICONO (SIN SUBMÓDULOS) --}}
    <section aria-labelledby="areas-heading">
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 id="areas-heading" class="text-base font-bold text-[#1e1e1e]">Áreas operativas</h2>
                <p class="mt-0.5 text-xs text-gray-500 font-medium">Selecciona un área de trabajo para ver sus procesos y herramientas.</p>
            </div>
            @if($startItems)
                <div class="flex flex-wrap items-center gap-2" aria-label="Iniciar una operación">
                    @foreach($startItems as $destination => [$label, $description])
                        <a href="{{ route($destination) }}" class="inline-flex items-center justify-center gap-1.5 rounded-xl px-3.5 py-2 text-xs font-semibold transition-colors {{ $loop->first ? 'bg-[#b50e0e] text-white shadow-sm hover:bg-[#9a0101]' : 'border border-[#e8e8e9] bg-white text-gray-700 hover:bg-gray-50' }}">
                            <span aria-hidden="true">+</span>{{ $label }}
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Cuadrícula limpia de Áreas compactas --}}
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($areas as $key => $workspace)
                @php
                    $icon = config('workspace-presentation.icons.'.$key, 'grid');
                    $moduleCount = count($workspace['items']);
                @endphp
                <a href="{{ route('workspace', $key) }}"
                   class="group flex items-center justify-between p-3.5 rounded-xl border border-[#e8e8e9] bg-white transition-all duration-150 hover:border-[#b50e0e]/50 hover:shadow-xs shadow-2xs">
                    <div class="flex items-center gap-3.5 min-w-0">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-50 text-[#b50e0e] group-hover:bg-[#b50e0e] group-hover:text-white transition-colors duration-150 shadow-2xs">
                            <x-portal.icon :name="$icon" class="w-5 h-5" />
                        </div>
                        <div class="min-w-0">
                            <h3 class="font-bold text-[#1e1e1e] text-sm group-hover:text-[#b50e0e] transition-colors truncate">
                                {{ $workspace['title'] }}
                            </h3>
                            <p class="text-xs text-gray-500 truncate mt-0.5 font-medium">
                                {{ $workspace['description'] }}
                            </p>
                        </div>
                    </div>
                    <div class="ml-3 shrink-0 flex items-center gap-2">
                        <span class="hidden sm:inline-flex items-center rounded-md bg-gray-50 px-2 py-0.5 text-[10px] font-semibold text-gray-400 group-hover:bg-rose-50 group-hover:text-[#b50e0e] transition-colors">
                            {{ $moduleCount }}
                        </span>
                        <x-portal.icon name="arrow" class="w-3.5 h-3.5 text-gray-300 group-hover:text-[#b50e0e] group-hover:translate-x-0.5 transition-all" />
                    </div>
                </a>
            @endforeach
        </div>
    </section>

    {{-- SECCIÓN: Continuar trabajando y Requiere atención --}}
    <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <section aria-labelledby="resume-heading" class="min-w-0">
            <div class="mb-3 flex items-center justify-between">
                <div>
                    <h2 id="resume-heading" class="text-base font-bold text-[#1e1e1e]">Continuar trabajando</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Cotizaciones y solicitudes en curso asignadas a tu usuario.</p>
                </div>
            </div>
            <div class="overflow-hidden rounded-2xl border border-[#e8e8e9] bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full whitespace-nowrap text-left text-sm" data-testid="home-current-work">
                        <thead class="border-b border-[#e8e8e9] bg-gray-50/80 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                            <tr>
                                <th scope="col" class="px-5 py-3.5 font-semibold">Expediente</th>
                                <th scope="col" class="px-5 py-3.5 font-semibold">Cliente</th>
                                <th scope="col" class="px-5 py-3.5 font-semibold">Fase actual</th>
                                <th scope="col" class="px-5 py-3.5 font-semibold text-right">Siguiente acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#e8e8e9]/60">
                            @forelse($recentQuotes as $recent)
                                <tr class="transition-colors hover:bg-gray-50/60">
                                    <td class="px-5 py-3.5">
                                        <a href="{{ $recent['url'] }}" class="font-bold text-gray-900 hover:text-[#b50e0e] transition-colors">
                                            {{ $recent['reference'] }}
                                        </a>
                                    </td>
                                    <td class="px-5 py-3.5 text-xs text-gray-600 font-medium">{{ $recent['client'] }}</td>
                                    <td class="px-5 py-3.5">
                                        <span class="inline-flex items-center rounded-lg bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-700">
                                            {{ $recent['stage'] }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5 text-right">
                                        <a href="{{ $recent['url'] }}" class="inline-flex items-center gap-1.5 font-semibold text-xs text-[#b50e0e] hover:text-[#9a0101] transition-colors">
                                            <span>{{ $recent['action'] }}</span>
                                            <x-portal.icon name="arrow" class="w-3.5 h-3.5"/>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-5 py-8 text-center text-xs text-gray-400 italic">
                                        No tienes cotizaciones en curso activas. Inicia una nueva operación desde los accesos rápidos.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        @if($pending)
            <section aria-labelledby="home-pending-heading" class="min-w-0">
                <div class="mb-3 flex items-center justify-between">
                    <div>
                        <h2 id="home-pending-heading" class="text-base font-bold text-[#1e1e1e]">Requiere atención</h2>
                        <p class="text-xs text-gray-500 mt-0.5">Indicadores con tareas pendientes.</p>
                    </div>
                    <span class="text-[11px] font-bold text-[#b50e0e] bg-rose-50 px-2 py-0.5 rounded-full">Urgente</span>
                </div>
                <ul class="divide-y divide-[#e8e8e9]/60 rounded-2xl border border-[#e8e8e9] bg-white shadow-sm overflow-hidden">
                    @foreach($pending as $metric)
                        <li>
                            <a href="{{ route($metric['route']) }}" class="flex items-center gap-3.5 px-5 py-3.5 text-sm transition-colors hover:bg-rose-50/40 group">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-rose-50 text-xs font-bold tabular-nums text-[#b50e0e] group-hover:bg-[#b50e0e] group-hover:text-white transition-colors">
                                    {{ number_format($metric['value']) }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-semibold text-gray-800 truncate group-hover:text-[#b50e0e] transition-colors">{{ $metric['label'] }}</p>
                                    @if(isset($metric['context']))
                                        <p class="text-[10px] text-gray-400 truncate">{{ $metric['context'] }}</p>
                                    @endif
                                </div>
                                <x-portal.icon name="arrow" class="w-3.5 h-3.5 text-gray-300 group-hover:text-[#b50e0e] group-hover:translate-x-0.5 transition-all"/>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</div>
