<div class="p-4 sm:p-6 lg:p-8 space-y-6 font-sans"
    x-data="{
        stage: 'prospectos',
        drag: null,
        showFilters: false,
        showCampaigns: false
    }">

    @if($creating || $selected)
        {{-- VISTA COMPLETA DEDICADA DEL PROSPECTO (PÁGINA COMPLETA, SIN MODALES FLOTANTES) --}}
        @include('livewire.crm.acquisition-detail')
    @else
        {{-- VISTA TABLERO KANBAN --}}

        {{-- Breadcrumb Navigation --}}
        <nav class="flex items-center gap-2 text-xs font-medium text-gray-400" aria-label="Ubicación">
            <a href="{{ route('workspace') }}" class="hover:text-gray-700 transition-colors">Inicio</a>
            <span>/</span>
            <a href="{{ route('workspace', 'ventas') }}" class="hover:text-gray-700 transition-colors">Ventas</a>
            <span>/</span>
            <span class="text-[#b50e0e] font-semibold" aria-current="page">Captación</span>
        </nav>

        {{-- Encabezado de Página --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex items-center gap-2.5">
                    <h1 class="text-2xl font-extrabold tracking-tight text-gray-900">Captación de clientes</h1>
                    <span class="inline-flex items-center rounded-md bg-rose-50 px-2 py-0.5 text-xs font-bold uppercase tracking-wider text-[#b50e0e]">CRM Comercial</span>
                </div>
                <p class="mt-1 text-sm text-gray-500">Organiza empresas y contactos potenciales antes de convertirlos en oportunidades comerciales.</p>
            </div>

            @if(auth()->user()->hasPermissionTo('prospects.create'))
                <button
                    type="button"
                    data-create-prospect
                    wire:click="create"
                    wire:loading.attr="disabled"
                    class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-[#b50e0e] hover:bg-[#9a0101] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors disabled:opacity-50"
                >
                    <x-workspace-icon name="plus" class="h-4 w-4" />
                    <span>Nuevo prospecto</span>
                </button>
            @endif
        </div>

        {{-- Mensajes de estado y errores --}}
        @if($notice)
            <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 flex items-center gap-2">
                <x-workspace-icon name="check" class="h-4 w-4 text-emerald-600" />
                <span>{{ $notice }}</span>
            </div>
        @endif

        @if($errors->any())
            <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 space-y-1">
                @foreach($errors->all() as $error)
                    <p>&bull; {{ $error }}</p>
                @endforeach
                <p class="text-xs font-semibold text-rose-700 mt-2">
                    💡 Tip: Haz clic en el prospecto para abrir su ficha completa y registrar el contacto o requerimientos necesarios.
                </p>
            </div>
        @endif

        {{-- Tarjetas KPI de Resumen Rápido del Pipeline --}}
        @php
            $totalProspects = collect($counts)->sum();
            $initialProspects = $counts['prospectos'] ?? 0;
            $activeFollowUp = ($counts['contactados'] ?? 0) + ($counts['interesados'] ?? 0);
            $qualifiedProspects = $counts['calificados'] ?? 0;
        @endphp

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
            <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-2xs">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Total en Pipeline</p>
                <p class="mt-1 text-2xl font-extrabold text-gray-900 tabular-nums">{{ number_format($totalProspects) }}</p>
                <p class="mt-1 text-xs text-gray-400">Prospectos registrados</p>
            </div>

            <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-2xs">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Nuevos contactos</p>
                <p class="mt-1 text-2xl font-extrabold text-slate-800 tabular-nums">{{ number_format($initialProspects) }}</p>
                <p class="mt-1 text-xs text-slate-400">Por iniciar acercamiento</p>
            </div>

            <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-2xs">
                <p class="text-xs font-semibold uppercase tracking-wider text-amber-600">En seguimiento</p>
                <p class="mt-1 text-2xl font-extrabold text-amber-700 tabular-nums">{{ number_format($activeFollowUp) }}</p>
                <p class="mt-1 text-xs text-amber-500">Contactados e interesados</p>
            </div>

            <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-2xs">
                <p class="text-xs font-semibold uppercase tracking-wider text-[#b50e0e]">Calificados</p>
                <p class="mt-1 text-2xl font-extrabold text-[#b50e0e] tabular-nums">{{ number_format($qualifiedProspects) }}</p>
                <p class="mt-1 text-xs text-rose-400">Listos para oportunidad</p>
            </div>
        </div>

        {{-- BARRA DE FILTROS Y BÚSQUEDA INTEGRADA --}}
        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-xs space-y-3">
            <div class="flex flex-col lg:flex-row lg:items-center gap-3">
                {{-- Buscador Principal --}}
                <div class="relative flex-1 min-w-0">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                        <x-workspace-icon name="search" class="h-4 w-4" />
                    </div>
                    <input
                        wire:model.live.debounce.400ms="search"
                        type="search"
                        placeholder="Buscar empresa, contacto, RUC o maquinaria..."
                        class="w-full rounded-xl border border-gray-200 bg-gray-50/60 pl-9 pr-3.5 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:bg-white focus:border-[#b50e0e] focus:ring-2 focus:ring-[#b50e0e]/20 transition-all"
                    />
                </div>

                {{-- Filtros Directos --}}
                <div class="flex flex-wrap items-center gap-2">
                    @if(auth()->user()->hasPermissionTo('prospects.view_all'))
                        <select wire:model.live="responsible" class="rounded-xl border border-gray-200 bg-gray-50/60 py-2 px-3 text-xs font-semibold text-gray-700 hover:border-gray-300 focus:bg-white focus:border-[#b50e0e] focus:ring-2 focus:ring-[#b50e0e]/20">
                            <option value="">Responsable: Todos</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                            @endforeach
                        </select>
                    @endif

                    <select wire:model.live="source" class="rounded-xl border border-gray-200 bg-gray-50/60 py-2 px-3 text-xs font-semibold text-gray-700 hover:border-gray-300 focus:bg-white focus:border-[#b50e0e] focus:ring-2 focus:ring-[#b50e0e]/20">
                        <option value="">Fuente: Todas</option>
                        @foreach(config('acquisition.sources') as $value)
                            <option value="{{ $value }}">{{ $value }}</option>
                        @endforeach
                    </select>

                    <select wire:model.live="classification" class="rounded-xl border border-gray-200 bg-gray-50/60 py-2 px-3 text-xs font-semibold text-gray-700 hover:border-gray-300 focus:bg-white focus:border-[#b50e0e] focus:ring-2 focus:ring-[#b50e0e]/20">
                        <option value="">Clasificación: Todas</option>
                        @foreach(config('acquisition.classes') as $key => $value)
                            <option value="{{ $key }}">{{ $key }} — {{ $value }}</option>
                        @endforeach
                    </select>

                    <select wire:model.live="campaign" class="max-w-44 rounded-xl border border-gray-200 bg-gray-50/60 py-2 px-3 text-xs font-semibold text-gray-700 hover:border-gray-300 focus:bg-white focus:border-[#b50e0e] focus:ring-2 focus:ring-[#b50e0e]/20 truncate">
                        <option value="">Campaña: Todas</option>
                        @foreach($campaigns as $entry)
                            <option value="{{ $entry->id }}">{{ $entry->name }}</option>
                        @endforeach
                    </select>

                    {{-- Botón Toggle de Más Filtros --}}
                    <button
                        type="button"
                        @click="showFilters = !showFilters"
                        :class="showFilters ? 'bg-slate-100 border-slate-300 text-slate-800' : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50'"
                        class="inline-flex items-center gap-1.5 rounded-xl border px-3 py-2 text-xs font-semibold transition-colors"
                    >
                        <x-workspace-icon name="settings" class="h-3.5 w-3.5" />
                        <span>Filtros</span>
                        @if($city || $sector || $dateFrom || $dateTo || $discarded !== 'active' || $overdue)
                            <span class="h-1.5 w-1.5 rounded-full bg-[#b50e0e]"></span>
                        @endif
                    </button>

                    @if(auth()->user()->hasPermissionTo('prospects.create'))
                        <button
                            type="button"
                            @click="showCampaigns = !showCampaigns"
                            :class="showCampaigns ? 'bg-slate-100 border-slate-300 text-slate-800' : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50'"
                            class="inline-flex items-center gap-1.5 rounded-xl border px-3 py-2 text-xs font-semibold transition-colors"
                        >
                            <x-workspace-icon name="target" class="h-3.5 w-3.5" />
                            <span>Campañas</span>
                        </button>
                    @endif

                    {{-- Limpiar filtros --}}
                    <button
                        type="button"
                        wire:click="clearFilters"
                        class="text-xs font-semibold text-gray-400 hover:text-[#b50e0e] transition-colors px-2 py-1"
                    >
                        Limpiar
                    </button>
                </div>
            </div>

            {{-- Panel Colapsable de Más Filtros --}}
            <div x-show="showFilters" x-collapse x-cloak class="pt-3 border-t border-gray-100">
                <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6 items-end">
                    <x-acquisition-field label="Ubicación / Ciudad" model="city" wire:model.live.debounce.400ms="city" placeholder="Ej: Managua" />
                    <x-acquisition-field label="Sector económico" model="sector" wire:model.live.debounce.400ms="sector" placeholder="Ej: Minería" />
                    <x-acquisition-field label="Creado desde" model="dateFrom" type="date" wire:model.live="dateFrom" />
                    <x-acquisition-field label="Creado hasta" model="dateTo" type="date" wire:model.live="dateTo" />

                    <label class="block text-xs font-medium text-gray-700">
                        <span>Estado del prospecto</span>
                        <select wire:model.live="discarded" class="mt-1 block w-full rounded-xl border-gray-200 bg-gray-50/60 text-xs py-2">
                            <option value="active">Solo activos</option>
                            <option value="discarded">Solo descartados</option>
                            <option value="all">Todos los estados</option>
                        </select>
                    </label>

                    <label class="flex items-center gap-2 p-2 rounded-xl bg-gray-50/80 border border-gray-200 cursor-pointer">
                        <input type="checkbox" wire:model.live="overdue" class="rounded border-gray-300 text-[#b50e0e] focus:ring-[#b50e0e]/20" />
                        <span class="text-xs font-semibold text-gray-700">Seguimiento vencido</span>
                    </label>
                </div>
            </div>

            {{-- Panel Colapsable de Gestión de Campañas --}}
            @if(auth()->user()->hasPermissionTo('prospects.create'))
                <div x-show="showCampaigns" x-collapse x-cloak class="pt-3 border-t border-gray-100">
                    <form wire:submit="createCampaign" class="grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                        <x-acquisition-field label="Nombre de campaña *" model="campaignName" required maxlength="180" placeholder="Ej: Agroindustria Q4" />
                        <x-acquisition-field label="Descripción de campaña" model="campaignDescription" maxlength="3000" placeholder="Objetivo de prospección comercial..." />
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            class="rounded-xl bg-slate-800 hover:bg-slate-900 px-4 py-2 text-xs font-semibold text-white transition-colors disabled:opacity-50"
                        >
                            Crear campaña
                        </button>
                        <p class="text-xs text-gray-400 sm:col-span-3">Agrupa empresas objetivo asignando la campaña al crear o editar cada prospecto.</p>
                    </form>
                </div>
            @endif
        </div>

        {{-- Resumen de Campaña si está seleccionada --}}
        @if($campaignSummary)
            <section aria-label="Resumen de campaña" class="rounded-2xl border border-gray-200/80 bg-rose-50/40 p-4 text-sm">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="font-bold text-gray-900 text-sm flex items-center gap-2">
                        <x-workspace-icon name="target" class="h-4 w-4 text-[#b50e0e]" />
                        <span>Campaña: {{ $campaigns->firstWhere('id', $campaign)?->name }}</span>
                    </h2>
                    <div class="flex items-center gap-1 text-xs font-bold text-[#b50e0e]">
                        <span>Total empresas:</span>
                        <span class="bg-white px-2 py-0.5 rounded-full border border-rose-200 tabular-nums">{{ $campaignSummary['total'] }}</span>
                    </div>
                </div>
                <dl class="mt-2.5 flex flex-wrap gap-x-5 gap-y-2 text-xs text-gray-600">
                    <div class="flex items-center gap-1.5 bg-white px-2.5 py-1 rounded-lg border border-gray-100">
                        <dt class="font-medium text-gray-500">Total empresas:</dt>
                        <dd class="font-bold tabular-nums text-gray-900">{{ $campaignSummary['total'] }}</dd>
                    </div>
                    @foreach(config('acquisition.stages') as $stageKey => $stageName)
                        <div class="flex items-center gap-1.5 bg-white px-2.5 py-1 rounded-lg border border-gray-100">
                            <dt class="font-medium text-gray-500">{{ $stageKey === 'prospectos' ? 'Pendientes' : $stageName }}:</dt>
                            <dd class="font-bold tabular-nums text-gray-900">{{ $campaignSummary['stages'][$stageKey] ?? 0 }}</dd>
                        </div>
                    @endforeach
                    @if($campaignSummary['discarded'])
                        <div class="flex items-center gap-1.5 bg-white px-2.5 py-1 rounded-lg border border-gray-100 text-rose-700">
                            <dt class="font-medium">Descartadas:</dt>
                            <dd class="font-bold tabular-nums">{{ $campaignSummary['discarded'] }}</dd>
                        </div>
                    @endif
                </dl>
                <p class="mt-2 text-[11px] text-gray-400">Empresas visibles según tus permisos; el resumen no cambia con los demás filtros del tablero.</p>
            </section>
        @endif

        {{-- Selector móvil de etapa para pantallas pequeñas --}}
        <div class="block md:hidden">
            <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-1">Ver etapa</label>
            <select x-model="stage" class="w-full rounded-xl border border-gray-200 bg-white py-2 px-3 text-sm font-semibold text-gray-800">
                @foreach(config('acquisition.stages') as $key => $label)
                    <option value="{{ $key }}">{{ $label }} ({{ $counts[$key] ?? 0 }})</option>
                @endforeach
            </select>
        </div>

        {{-- TABLERO KANBAN DE 4 COLUMNAS --}}
        <div class="flex min-w-0 w-full items-stretch gap-4 overflow-x-auto pb-4 [contain:inline-size_layout_paint] lg:grid lg:grid-cols-4" data-testid="acquisition-board">
            @foreach(config('acquisition.stages') as $key => $label)
                @php
                    [$iconTone, $stageIcon, $badgeColor, $help] = match ($key) {
                        'contactados' => ['bg-amber-50 text-amber-700', 'phone', 'bg-amber-100 text-amber-800', 'Primer acercamiento realizado'],
                        'interesados' => ['bg-blue-50 text-blue-700', 'target', 'bg-blue-100 text-blue-800', 'Existe una necesidad real'],
                        'calificados' => ['bg-rose-50 text-[#b50e0e]', 'check', 'bg-rose-100 text-rose-800', 'Listos para una oportunidad'],
                        default => ['bg-slate-100 text-slate-700', 'users', 'bg-slate-200 text-slate-700', 'Nuevos contactos por trabajar'],
                    };
                @endphp

                <section
                    :class="stage === @js($key) ? 'flex' : 'hidden md:flex'"
                    class="min-w-0 w-full shrink-0 flex-col rounded-2xl border border-gray-200/80 bg-gray-50/70 p-3 md:w-72 lg:w-auto shadow-2xs"
                    aria-labelledby="acquisition-{{ $key }}"
                    x-on:dragover.prevent
                    x-on:drop.prevent="if (drag) { $wire.move(drag.id, drag.version, @js($key)); drag = null }"
                >
                    {{-- Encabezado de la Columna --}}
                    <header class="flex items-center justify-between gap-2 rounded-xl bg-white p-3 border border-gray-100 shadow-2xs mb-3">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $iconTone }}">
                                <x-workspace-icon :name="$stageIcon" class="h-4 w-4" />
                            </span>
                            <div class="min-w-0">
                                <h2 id="acquisition-{{ $key }}" class="text-sm font-bold text-gray-900 truncate">{{ $label }}</h2>
                                <p class="text-[11px] text-gray-400 font-medium truncate">{{ $help }}</p>
                            </div>
                        </div>

                        <span class="inline-flex items-center justify-center rounded-full px-2 py-0.5 text-xs font-bold tabular-nums {{ $badgeColor }}">
                            {{ $counts[$key] ?? 0 }}
                        </span>
                    </header>

                    {{-- Contenedor de Tarjetas con Scroll Interno --}}
                    <div class="space-y-3 px-0.5 pb-2 md:max-h-[calc(100vh-22rem)] md:overflow-y-auto" tabindex="0" role="region" aria-label="Lista de {{ $label }}">
                        @forelse($columns[$key] as $p)
                            @include('livewire.crm.acquisition-card')
                        @empty
                            <div class="flex flex-col items-center justify-center p-6 text-center rounded-xl border border-dashed border-gray-200 bg-white/60 my-2">
                                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 text-gray-400 mb-1.5">
                                    <x-workspace-icon :name="$stageIcon" class="h-4 w-4" />
                                </span>
                                <p class="text-xs font-semibold text-gray-500">Sin prospectos</p>
                                <p class="text-[11px] text-gray-400 mt-0.5">Arrastra aquí para mover a esta etapa</p>
                            </div>
                        @endforelse

                        @if(($counts[$key] ?? 0) > $columns[$key]->count())
                            <button
                                type="button"
                                wire:click="more"
                                wire:loading.attr="disabled"
                                class="w-full py-2 text-center text-xs font-semibold text-[#b50e0e] hover:text-[#9a0101] bg-white rounded-xl border border-gray-200 hover:border-gray-300 transition-colors shadow-2xs"
                            >
                                Mostrar más ({{ $counts[$key] - $columns[$key]->count() }} restantes)
                            </button>
                        @endif
                    </div>
                </section>
            @endforeach
        </div>
    @endif
</div>
