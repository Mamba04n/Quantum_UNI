@php
    $flow = config('workspace-flows.'.$area, []);
    $orderedItems = array_replace(array_intersect_key($flow, $items), $items);
@endphp

<div class="w-full">
    {{-- Breadcrumb con rojo del portal --}}
    <nav aria-label="Ubicación del espacio de trabajo" class="mb-2 flex items-center gap-2 text-xs font-semibold">
        <a href="{{ route('workspace') }}" class="text-gray-500 hover:text-[#b50e0e] transition-colors">Inicio</a>
        <span class="text-gray-300" aria-hidden="true">/</span>
        <span class="text-[#b50e0e]" aria-current="page">{{ $areas[$area]['title'] }}</span>
    </nav>

    {{-- Encabezado compacto y equilibrado --}}
    <div class="mb-5 flex flex-col gap-1.5 sm:flex-row sm:items-end sm:justify-between border-b border-[#e8e8e9] pb-3.5">
        <div>
            <h1 class="text-xl sm:text-2xl font-extrabold tracking-tight text-[#1e1e1e]">{{ $areas[$area]['title'] }}</h1>
            <p class="mt-0.5 text-xs text-gray-500 font-medium">{{ $areas[$area]['description'] }}</p>
        </div>
        <span class="text-[11px] font-semibold text-gray-400 shrink-0">
            {{ count($orderedItems) }} {{ count($orderedItems) === 1 ? 'módulo disponible' : 'módulos disponibles' }}
        </span>
    </div>

    {{-- Cuadrícula de tarjetas con la escala compacta y refinada exacta --}}
    <section aria-label="Módulos de {{ $areas[$area]['title'] }}" data-testid="area-processes">
        <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($orderedItems as $routeName => [$label, $description])
                @php
                    [$stepTitle, $stepHelp] = $flow[$routeName] ?? [$label, $description];
                    $icon = config('workspace-presentation.process-icons', [])[$routeName] ?? 'document';
                @endphp
                <li class="min-w-0">
                    <a href="{{ route($routeName) }}"
                       class="group flex items-center justify-between p-3.5 rounded-xl border border-[#e8e8e9] bg-white transition-all duration-150 hover:border-[#b50e0e]/50 hover:shadow-xs shadow-2xs">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-rose-50 text-[#b50e0e] group-hover:bg-[#b50e0e] group-hover:text-white transition-colors duration-150">
                                <x-portal.icon :name="$icon" class="w-4 h-4" />
                            </div>
                            <div class="min-w-0">
                                <h2 class="text-xs sm:text-sm font-bold text-[#1e1e1e] group-hover:text-[#b50e0e] transition-colors truncate">
                                    {{ $label }}
                                </h2>
                                <p class="text-[11px] sm:text-xs text-gray-500 truncate mt-0.5 font-medium leading-normal">
                                    {{ $stepHelp }}
                                </p>
                            </div>
                        </div>
                        <div class="ml-3 shrink-0 text-gray-300 group-hover:text-[#b50e0e] group-hover:translate-x-0.5 transition-all">
                            <x-portal.icon name="arrow" class="w-3.5 h-3.5" />
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>
        <p x-cloak x-show="!@js(array_map(fn ($routeName) => implode(' ', array_merge($items[$routeName], $flow[$routeName] ?? [])), array_keys($items))).some(value => matches(value))" role="status" class="py-6 text-center text-xs text-gray-400 italic">No se encontraron módulos en {{ $areas[$area]['title'] }}.</p>
    </section>
</div>
