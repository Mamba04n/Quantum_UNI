<div class="min-w-0" x-data="{ filter: 'all', view: 'kanban' }">
    {{-- Breadcrumb Navigation --}}
    <nav class="mb-4 flex items-center gap-2 text-xs font-medium text-gray-400" aria-label="Ubicación">
        <a href="{{ route('workspace') }}" class="hover:text-gray-700 transition-colors">Inicio</a>
        <span>/</span>
        <a href="{{ route('workspace', 'ventas') }}" class="hover:text-gray-700 transition-colors">Ventas</a>
        <span>/</span>
        <span class="text-[#b50e0e] font-semibold" aria-current="page">Cotizaciones</span>
    </nav>

    {{-- Encabezado de Página --}}
    <header class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-2xl font-extrabold tracking-tight text-gray-900">Cotizaciones</h1>
                <span class="inline-flex items-center rounded-md bg-rose-50 px-2 py-0.5 text-xs font-bold uppercase tracking-wider text-[#b50e0e]">Pipeline Comercial</span>
            </div>
            <p class="mt-1 text-sm text-gray-500">Solicitudes de clientes, licitaciones a proveedores y decisiones del proceso comercial.</p>
        </div>
        <a href="{{ route('sales.customer-request.create') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#b50e0e] hover:bg-[#9a0101] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors">
            <x-portal.icon name="plus" class="w-4 h-4" />
            <span>+ Nueva orden</span>
        </a>
    </header>

    {{-- Resumen Rápido de Métricas del Pipeline --}}
    @php
        $totalQuotes = $quotes->count();
        $activeQuotes = $quotes->filter(fn ($q) => ! $q->sale_order_id && ! in_array($q->status, [\App\Models\QuotationRequest::STATUS_CLOSED, \App\Models\QuotationRequest::STATUS_CANCELLED]))->count();
        $bidQuotes = $stageCounts['supplier_bid'] ?? 0;
        $offerQuotes = ($stageCounts['client_offer'] ?? 0) + ($stageCounts['customer_approval'] ?? 0);
    @endphp

    <div class="mb-6 grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-2xs">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Total Solicitudes</p>
            <p class="mt-1 text-2xl font-extrabold text-gray-900 tabular-nums">{{ number_format($totalQuotes) }}</p>
            <p class="mt-1 text-xs text-gray-400">Registradas en el sistema</p>
        </div>

        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-2xs">
            <p class="text-xs font-semibold uppercase tracking-wider text-blue-600">En Licitación</p>
            <p class="mt-1 text-2xl font-extrabold text-blue-700 tabular-nums">{{ number_format($bidQuotes) }}</p>
            <p class="mt-1 text-xs text-blue-500">Esperando ofertas de proveedor</p>
        </div>

        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-2xs">
            <p class="text-xs font-semibold uppercase tracking-wider text-violet-600">En Oferta / Aprobación</p>
            <p class="mt-1 text-2xl font-extrabold text-violet-700 tabular-nums">{{ number_format($offerQuotes) }}</p>
            <p class="mt-1 text-xs text-violet-500">Con oferta lista al cliente</p>
        </div>

        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-2xs">
            <p class="text-xs font-semibold uppercase tracking-wider text-[#b50e0e]">Órdenes Activas</p>
            <p class="mt-1 text-2xl font-extrabold text-[#b50e0e] tabular-nums">{{ number_format($activeQuotes) }}</p>
            <p class="mt-1 text-xs text-rose-400">En curso comercial</p>
        </div>
    </div>

    {{-- Barra de Búsqueda, Filtros Rápidos y Selector de Vista --}}
    <div class="mb-5 rounded-2xl border border-gray-100 bg-white p-3.5 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-3">
        <div class="flex flex-1 items-center gap-3 flex-wrap">
            <div class="relative w-full sm:w-80">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                    <x-portal.icon name="search" class="h-4 w-4" />
                </div>
                <input
                    type="search"
                    aria-label="Buscar cotizaciones"
                    wire:model.live.debounce.500ms="search"
                    placeholder="Buscar orden, cliente, producto..."
                    class="w-full rounded-xl border border-gray-200 bg-gray-50/60 pl-9 pr-3.5 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:bg-white focus:border-[#b50e0e] focus:ring-2 focus:ring-[#b50e0e]/20 transition-all"
                />
            </div>

            {{-- Filter Chips --}}
            <div class="flex items-center gap-1.5 text-xs font-semibold flex-wrap">
                @foreach([
                    'all' => 'Todas',
                    'active' => 'Activas',
                    'pending' => 'Pendientes',
                    'finished' => 'Finalizadas'
                ] as $fKey => $fLabel)
                    <button
                        type="button"
                        x-on:click="filter = '{{ $fKey }}'"
                        :class="filter === '{{ $fKey }}' ? 'bg-[#fef2f2] text-[#b50e0e] border border-rose-200' : 'text-gray-600 hover:bg-gray-100 border border-transparent'"
                        class="rounded-lg px-2.5 py-1.5 transition-colors cursor-pointer"
                    >
                        {{ $fLabel }}
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Switcher Kanban / Lista --}}
        <div class="flex items-center gap-1 rounded-xl border border-gray-200 bg-gray-50/80 p-1 self-end md:self-auto shrink-0" role="group" aria-label="Vista de cotizaciones">
            @foreach(['kanban' => 'Kanban', 'list' => 'Lista'] as $mode => $name)
                <button
                    type="button"
                    x-on:click="view = '{{ $mode }}'"
                    x-bind:aria-pressed="view === '{{ $mode }}'"
                    :class="view === '{{ $mode }}' ? 'bg-white text-gray-900 shadow-2xs font-bold border border-gray-200/60' : 'text-gray-500 hover:text-gray-900 font-medium'"
                    class="rounded-lg px-3 py-1.5 text-xs transition-all cursor-pointer"
                >
                    {{ $name }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- Vista Kanban --}}
    <div x-show="view === 'kanban'" class="min-w-0">
        @include('livewire.ventas.cotizaciones.fase-cotizacion.partials.kanban-board')
    </div>

    {{-- Vista Lista Agrupada --}}
    <div x-cloak x-show="view === 'list'" class="space-y-5" data-testid="commercial-grouped-list">
        @include('livewire.ventas.cotizaciones.fase-cotizacion.partials.grouped-list')
    </div>
</div>
