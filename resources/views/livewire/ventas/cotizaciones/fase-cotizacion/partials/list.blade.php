<div class="min-w-0" x-data="{ filter: 'all', view: 'kanban' }">
    <header class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-gray-900">Cotizaciones</h1>
            <p class="mt-0.5 text-sm text-gray-500">Solicitudes, proveedores y decisiones del proceso comercial.</p>
        </div>
        <a href="{{ route('sales.customer-request.create') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm">+ Nueva orden</a>
    </header>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        @include('livewire.ventas.cotizaciones.fase-cotizacion.partials.filters')
        <div class="flex items-center gap-1 rounded-xl border border-gray-200 bg-white p-1" role="group" aria-label="Vista de cotizaciones">
            @foreach(['kanban' => 'Kanban', 'list' => 'Lista'] as $mode => $name)
                <button type="button" x-on:click="view = '{{ $mode }}'" x-bind:aria-pressed="view === '{{ $mode }}'" x-bind:class="view === '{{ $mode }}' ? 'bg-slate-800 text-white' : 'text-gray-600'" class="rounded-lg px-3 py-2 text-sm font-semibold">{{ $name }}</button>
            @endforeach
        </div>
    </div>
    <div x-show="view === 'kanban'" class="min-w-0">
        @include('livewire.ventas.cotizaciones.fase-cotizacion.partials.kanban-board')
    </div>
    <div x-cloak x-show="view === 'list'" class="space-y-5" data-testid="commercial-grouped-list">
        @include('livewire.ventas.cotizaciones.fase-cotizacion.partials.grouped-list')
    </div>
</div>
