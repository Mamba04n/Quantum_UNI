<div class="p-6 font-sans sm:p-8" x-data="{ stage: 'prospectos', drag: null, opener: null, openerProspect: null, restoreFocus() { const target = this.opener?.isConnected ? this.opener : [...this.$el.querySelectorAll('[data-prospect-open]')].find(el => el.dataset.prospectOpen === String(this.openerProspect)) || this.$el.querySelector('[data-create-prospect]'); target?.focus() } }"
    x-on:acquisition-open.window="if ($event.detail.stage) stage = $event.detail.stage; if (!$refs.detail.open) { opener = document.activeElement; openerProspect = opener?.dataset?.prospectOpen ?? null; $refs.detail.showModal() }"
    x-on:acquisition-close.window="$refs.detail.close(); $nextTick(() => restoreFocus())">
    <nav class="mb-4 flex gap-2 text-xs text-gray-500" aria-label="Ubicación"><a href="{{ route('workspace') }}">Inicio</a><span>/</span><a href="{{ route('workspace', 'ventas') }}">Ventas</a><span>/</span><span aria-current="page">Captación</span></nav>
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div><h1 class="text-2xl font-extrabold tracking-tight text-gray-900">Captación de clientes</h1><p class="mt-0.5 text-sm text-gray-500">Organiza empresas y contactos potenciales antes de convertirlos en oportunidades comerciales.</p></div>
        @if(auth()->user()->hasPermissionTo('prospects.create'))<button type="button" data-create-prospect wire:click="create" wire:loading.attr="disabled" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-900 disabled:opacity-50"><x-workspace-icon name="plus" class="h-4 w-4"/>Nuevo prospecto</button>@endif
    </div>
    @if($notice)<p role="status" class="mb-4 text-sm text-emerald-700">{{ $notice }}</p>@endif
    @if($errors->any())<div role="alert" class="mb-4 text-sm text-red-800">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <div class="mb-5 grid grid-cols-2 items-end gap-3 md:flex md:flex-wrap">
        <label class="col-span-2 min-w-0 md:min-w-64 md:flex-1 text-sm text-gray-600">Buscar<input wire:model.live.debounce.400ms="search" type="search" placeholder="Empresa, contacto, RUC o maquinaria..." class="mt-1 w-full rounded-xl border-transparent bg-gray-50 px-3 py-2 text-sm focus:bg-white focus:ring-2 focus:ring-slate-200"></label>
        @if(auth()->user()->hasPermissionTo('prospects.view_all'))<label class="text-sm text-gray-600">Responsable<select wire:model.live="responsible" class="mt-1 block w-full rounded-xl border-gray-200 text-sm"><option value="">Todos</option>@foreach($employees as $employee)<option value="{{ $employee->id }}">{{ $employee->name }}</option>@endforeach</select></label>@endif
        <label class="text-sm text-gray-600">Fuente<select wire:model.live="source" class="mt-1 block w-full rounded-xl border-gray-200 text-sm"><option value="">Todas</option>@foreach(config('acquisition.sources') as $value)<option>{{ $value }}</option>@endforeach</select></label>
        <label class="text-sm text-gray-600">Clasificación<select wire:model.live="classification" class="mt-1 block w-full rounded-xl border-gray-200 text-sm"><option value="">Todas</option>@foreach(config('acquisition.classes') as $key => $value)<option value="{{ $key }}">{{ $key }} — {{ $value }}</option>@endforeach</select></label>
        <label class="min-w-0 text-sm text-gray-600">Campaña<select wire:model.live="campaign" class="mt-1 block w-full max-w-64 rounded-xl border-gray-200 text-sm"><option value="">Todas</option>@foreach($campaigns as $entry)<option value="{{ $entry->id }}">{{ $entry->name }}</option>@endforeach</select></label>
        <button type="button" wire:click="clearFilters" class="py-2 text-sm text-gray-600 underline">Limpiar</button>
    </div>
    @if($campaignSummary)
        <section aria-label="Resumen de campaña" class="mb-4 border-y border-gray-100 py-3 text-sm">
            <h2 class="font-semibold text-gray-900">{{ $campaigns->firstWhere('id', $campaign)?->name }}</h2>
            <dl class="mt-2 flex flex-wrap gap-x-5 gap-y-2 text-gray-600"><div class="flex gap-1"><dt>Total empresas:</dt><dd class="font-semibold tabular-nums text-gray-900">{{ $campaignSummary['total'] }}</dd></div>@foreach(config('acquisition.stages') as $stageKey => $stageName)<div class="flex gap-1"><dt>{{ $stageKey === 'prospectos' ? 'Pendientes' : $stageName }}:</dt><dd class="font-semibold tabular-nums text-gray-900">{{ $campaignSummary['stages'][$stageKey] ?? 0 }}</dd></div>@endforeach @if($campaignSummary['discarded'])<div class="flex gap-1"><dt>Descartadas:</dt><dd>{{ $campaignSummary['discarded'] }}</dd></div>@endif</dl>
            <p class="mt-1 text-xs text-gray-500">Empresas visibles según tus permisos; el resumen no cambia con los demás filtros del tablero.</p>
        </section>
    @endif
    @if(auth()->user()->hasPermissionTo('prospects.create'))
        <details class="mb-4 text-sm text-gray-600"><summary class="cursor-pointer font-medium">Organizar campañas</summary><form wire:submit="createCampaign" class="mt-3 grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end"><x-acquisition-field label="Nombre de campaña *" model="campaignName" required maxlength="180"/><x-acquisition-field label="Descripción" model="campaignDescription" maxlength="3000"/><button type="submit" wire:loading.attr="disabled" class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 disabled:opacity-50">Crear campaña</button><p class="text-xs text-gray-500 sm:col-span-3">Agrupa empresas objetivo asignando la campaña al crear o editar cada prospecto. No agrega etapas.</p></form></details>
    @endif
    <details class="mb-5 text-sm text-gray-600"><summary class="cursor-pointer font-medium">Más filtros</summary><div class="mt-3 grid gap-3 sm:grid-cols-3 xl:grid-cols-6">
        <x-acquisition-field label="Ubicación" model="city" wire:model.live.debounce.400ms="city"/>
        <x-acquisition-field label="Sector" model="sector" wire:model.live.debounce.400ms="sector"/>
        <x-acquisition-field label="Creado desde" model="dateFrom" type="date" wire:model.live="dateFrom"/>
        <x-acquisition-field label="Creado hasta" model="dateTo" type="date" wire:model.live="dateTo"/>
        <label>Estado<select wire:model.live="discarded" class="mt-1 block w-full rounded-xl border-gray-200 text-sm"><option value="active">Activos</option><option value="discarded">Descartados</option><option value="all">Todos</option></select></label>
        <label class="flex items-center gap-2"><input type="checkbox" wire:model.live="overdue" class="rounded border-gray-300 text-slate-800">Seguimiento vencido</label>
    </div></details>
    <label class="mb-4 block text-sm font-medium text-gray-700 md:hidden">Etapa<select x-model="stage" class="mt-1 block w-full rounded-xl border-gray-200 text-sm">@foreach(config('acquisition.stages') as $key => $label)<option value="{{ $key }}">{{ $label }} ({{ $counts[$key] ?? 0 }})</option>@endforeach</select></label>
    <div class="flex min-w-0 w-full items-stretch gap-4 overflow-x-auto pb-3 [contain:inline-size_layout_paint] lg:grid lg:grid-cols-4" data-testid="acquisition-board">
        @foreach(config('acquisition.stages') as $key => $label)
            @php
                [$tone, $iconTone, $stageIcon, $help] = match ($key) {
                    'contactados' => ['border-amber-200 bg-amber-50/50', 'bg-amber-100 text-amber-700', 'phone', 'Primer acercamiento realizado'],
                    'interesados' => ['border-sky-200 bg-sky-50/50', 'bg-sky-100 text-sky-700', 'target', 'Existe una necesidad real'],
                    'calificados' => ['border-emerald-200 bg-emerald-50/50', 'bg-emerald-100 text-emerald-700', 'check', 'Listos para una oportunidad'],
                    default => ['border-slate-200 bg-slate-100/70', 'bg-slate-200 text-slate-700', 'users', 'Nuevos contactos por trabajar'],
                };
            @endphp
            <section :class="stage === @js($key) ? 'flex' : 'hidden md:flex'" class="min-w-0 w-full shrink-0 flex-col rounded-xl border md:w-72 lg:w-auto {{ $tone }}" aria-labelledby="acquisition-{{ $key }}"
                x-on:dragover.prevent x-on:drop.prevent="if (drag) { $wire.move(drag.id, drag.version, @js($key)); drag = null }">
                <div class="flex items-start gap-3 px-3 py-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $iconTone }}"><x-workspace-icon :name="$stageIcon" class="h-5 w-5"/></span>
                    <div class="min-w-0 flex-1"><h2 id="acquisition-{{ $key }}" class="text-sm font-semibold text-gray-900">{{ $label }}</h2><p class="mt-0.5 text-xs leading-4 text-slate-500">{{ $help }}</p></div>
                    <span class="inline-flex min-w-6 items-center justify-center rounded-md bg-white/80 px-1.5 py-0.5 text-sm font-semibold tabular-nums text-slate-600">{{ $counts[$key] ?? 0 }}</span>
                </div>
                <div class="space-y-3 px-2 pb-2 md:max-h-[calc(100vh-24rem)] md:overflow-y-auto" tabindex="0" role="region" aria-label="Lista de {{ $label }}">
                @forelse($columns[$key] as $p)
                    @include('livewire.crm.acquisition-card')
                @empty <p class="px-2 py-5 text-sm text-slate-500">Sin prospectos en esta etapa.</p>@endforelse
                @if(($counts[$key] ?? 0) > $columns[$key]->count())<button type="button" wire:click="more" wire:loading.attr="disabled" class="py-2 text-sm font-semibold text-slate-700">Mostrar más ({{ $counts[$key] - $columns[$key]->count() }} restantes)</button>@endif
                </div>
            </section>
        @endforeach
    </div>
    <dialog x-ref="detail" wire:ignore.self x-on:close="$wire.close(); $nextTick(() => restoreFocus())" class="fixed inset-y-0 left-auto right-0 m-0 h-dvh max-h-none w-full max-w-4xl overflow-y-auto rounded-none bg-white p-0 text-gray-900 shadow-xl backdrop:bg-black/40 md:rounded-l-2xl" aria-labelledby="prospect-detail-title">
        @if($creating || $selected)
        <div class="sticky top-0 z-10 flex items-center justify-between gap-3 border-b border-gray-100 bg-white px-6 py-4"><h2 id="prospect-detail-title" class="font-semibold">{{ $creating ? 'Nuevo prospecto' : $selected->name }}</h2><button type="button" x-on:click="$refs.detail.close()" aria-label="Cerrar detalle" class="rounded-lg px-3 py-2 text-gray-600 hover:bg-gray-100">✕</button></div>
        <div class="p-6">
            @if($errors->any())<div role="alert" class="mb-4 text-sm text-red-800">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
            @include('livewire.crm.acquisition-detail')
        </div>
        @endif
    </dialog>
</div>
