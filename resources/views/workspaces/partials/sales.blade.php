@php
    $salesShortcuts = [
        'sales.customer-request.create' => ['Nueva solicitud', 'Iniciar una cotización', 'document'],
        'sales.direct-orders.create' => ['Venta directa', 'Crear una orden desde inventario', 'cart'],
        'sales.quotations.phase' => ['Ver Kanban', 'Cotizaciones por etapa', 'grid'],
        'orders.status.index' => ['Seguimiento de órdenes', 'Preparación, despacho y entrega', 'box'],
        'purchases.create' => ['Nueva orden de compra', 'Compra directa a proveedor', 'cart'],
    ];
@endphp
<nav aria-label="Acciones de Ventas" class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
    @foreach($salesShortcuts as $destination => [$label, $description, $shortcutIcon])
        @continue(!isset($allItems[$destination]))
        <a href="{{ route($destination) }}" class="flex min-w-0 items-start gap-3 rounded-xl border border-gray-200 bg-white p-3 shadow-sm">
            <span class="rounded-lg bg-slate-100 p-2 text-slate-700"><x-workspace-icon :name="$shortcutIcon" class="h-4 w-4" /></span>
            <span class="min-w-0"><span class="block text-sm font-semibold text-gray-900">{{ $label }}</span><span class="mt-1 block text-xs text-gray-500">{{ $description }}</span></span>
        </a>
    @endforeach
</nav>
<div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
    @foreach($metrics as $metric)
        <div class="min-w-0">
            <x-dashboard-metric :compact="true" :label="$metric['label']" :value="number_format($metric['value'])" :tone="$metric['value'] > 0 ? 'amber' : 'teal'">
                <a href="{{ route($metric['route']) }}" class="font-semibold text-slate-700 underline underline-offset-2">Revisar pendientes</a>
            </x-dashboard-metric>
        </div>
    @endforeach
</div>
<div class="mb-3 flex flex-wrap items-center justify-between gap-3">
    <h2 class="text-base font-semibold text-gray-900">Bandeja de seguimiento</h2>
    <div class="flex flex-wrap gap-3 text-sm font-semibold text-slate-700">
        @if(isset($items['sales.quotations.solicitud']))<a href="{{ route('sales.quotations.solicitud') }}">Todas las solicitudes →</a>@endif
        @if(isset($items['sales.quotations.corrections']))<a href="{{ route('sales.quotations.corrections') }}">Atender correcciones →</a>@endif
    </div>
</div>
<div class="mb-5 grid items-start gap-4 lg:grid-cols-2">
    @if(isset($items['sales.quotations.phase']))
        @include('workspaces.partials.sales-queue', ['queueTitle' => 'Cotizaciones para continuar', 'queueContext' => 'Tus expedientes recientes; cada uno abre su fase actual.', 'queueRows' => $recentQuotes, 'queueRoute' => 'sales.quotations.phase'])
    @endif
    @if(isset($items['orders.status.index']))
        @include('workspaces.partials.sales-queue', ['queueTitle' => 'Órdenes por seguir', 'queueContext' => 'Hasta 6 órdenes activas, ordenadas por actualización.', 'queueRows' => $salesOrders, 'queueRoute' => 'orders.status.index'])
    @endif
</div>
