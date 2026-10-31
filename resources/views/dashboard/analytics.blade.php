@php
    $chartWidth = 720;
    $chartHeight = 210;
    $chartPadding = 24;
    $trendMaximum = max(1, collect($tendenciaMensual)->max(fn ($row) => max($row['sales'], $canViewDashboardFinance ? $row['collections'] : 0)));
    $pointFor = function (array $row, int $index, string $key) use ($tendenciaMensual, $chartWidth, $chartHeight, $chartPadding, $trendMaximum): string {
        $x = $chartPadding + ($index * (($chartWidth - ($chartPadding * 2)) / max(1, count($tendenciaMensual) - 1)));
        $y = $chartHeight - $chartPadding - (($row[$key] / $trendMaximum) * ($chartHeight - ($chartPadding * 2)));
        return round($x, 1).','.round($y, 1);
    };
    $salesPoints = collect($tendenciaMensual)->map(fn ($row, $index) => $pointFor($row, $index, 'sales'))->implode(' ');
    $collectionPoints = collect($tendenciaMensual)->map(fn ($row, $index) => $pointFor($row, $index, 'collections'))->implode(' ');
    $pipelineMaximum = max(1, collect($pipelinePorFase)->max('amount'));
    $rawOrderTotal = collect($ordenesPorEstado)->sum('count');
    $orderTotal = max(1, $rawOrderTotal);
    $donutColors = ['#2563eb', '#d97706', '#7c3aed', '#0891b2', '#4f46e5', '#059669'];
    $donutCursor = 0;
    $donutStops = $rawOrderTotal > 0 ? collect($ordenesPorEstado)->map(function ($row, $index) use (&$donutCursor, $orderTotal, $donutColors) {
        $start = $donutCursor;
        $donutCursor += ($row['count'] / $orderTotal) * 100;
        return $donutColors[$index % count($donutColors)].' '.round($start, 2).'% '.round($donutCursor, 2).'%';
    })->implode(', ') : '#e5e7eb 0% 100%';
    $maintenanceColors = ['#e11d48', '#dc2626', '#d97706', '#0f766e'];
    $maintenanceTotal = collect($mantenimientoPorEstado)->sum('count');
    $maintenanceDivisor = max(1, $maintenanceTotal);
    $maintenanceCursor = 0;
    $maintenanceStops = $maintenanceTotal > 0 ? collect($mantenimientoPorEstado)->map(function ($row, $index) use (&$maintenanceCursor, $maintenanceDivisor, $maintenanceColors) {
        $start = $maintenanceCursor;
        $maintenanceCursor += ($row['count'] / $maintenanceDivisor) * 100;
        return $maintenanceColors[$index % count($maintenanceColors)].' '.round($start, 2).'% '.round($maintenanceCursor, 2).'%';
    })->implode(', ') : '#e5e7eb 0% 100%';
@endphp

<section class="grid gap-4 lg:grid-cols-12" aria-label="Análisis operativo">
    <article class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm lg:col-span-8">
        <header class="flex flex-col gap-2 border-b border-gray-100 px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
            <div><h2 class="text-sm font-extrabold text-gray-900">{{ $canViewDashboardFinance ? 'Ventas y cobros' : 'Ventas mensuales' }}</h2><p class="mt-0.5 text-xs text-gray-500">Evolución de los últimos 12 meses</p></div>
            <div class="flex items-center gap-4 text-[11px] font-medium text-gray-500"><span class="flex items-center gap-1.5"><i class="h-2 w-2 rounded-full bg-blue-600"></i>Ventas</span>@if($canViewDashboardFinance)<span class="flex items-center gap-1.5"><i class="h-2 w-2 rounded-full bg-teal-600"></i>Cobros</span>@endif</div>
        </header>
        <div class="px-4 pb-3 pt-4">
            <svg class="h-40 w-full" viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}" role="img" aria-label="Gráfico de ventas y cobros mensuales" preserveAspectRatio="none">
                @foreach([0.25, 0.5, 0.75] as $guide)<line x1="24" x2="696" y1="{{ $chartHeight * $guide }}" y2="{{ $chartHeight * $guide }}" stroke="#e5e7eb" stroke-width="1"/>@endforeach
                <polygon points="24,186 {{ $salesPoints }} 696,186" fill="#dbeafe" fill-opacity="0.65"/>
                <polyline points="{{ $salesPoints }}" fill="none" stroke="#2563eb" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                @if($canViewDashboardFinance)
                    <polyline points="{{ $collectionPoints }}" fill="none" stroke="#0d9488" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                @endif
                @foreach($tendenciaMensual as $index => $row)
                    @php($coordinates = explode(',', $pointFor($row, $index, 'sales')))
                    <circle cx="{{ $coordinates[0] }}" cy="{{ $coordinates[1] }}" r="4" fill="#2563eb" stroke="white" stroke-width="2"><title>{{ $row['label'] }} · Ventas C$ {{ number_format($row['sales'], 2) }}@if($canViewDashboardFinance) · Cobros C$ {{ number_format($row['collections'], 2) }}@endif</title></circle>
                @endforeach
            </svg>
            <div class="grid grid-cols-12 text-center text-[10px] text-gray-400">@foreach($tendenciaMensual as $row)<span>{{ $row['label'] }}</span>@endforeach</div>
        </div>
    </article>

    <article class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm lg:col-span-4">
        <header class="border-b border-gray-100 px-5 py-3"><h2 class="text-sm font-extrabold text-gray-900">Pipeline por fase</h2><p class="mt-0.5 text-xs text-gray-500">Pedidos e importe comprometido por etapa</p></header>
        <div class="space-y-2.5 px-5 py-3">
            @foreach($pipelinePorFase as $row)
                <div>
                    <div class="mb-1.5 flex items-center justify-between gap-3"><span class="text-xs font-semibold text-gray-700">{{ $row['label'] }} <span class="font-normal text-gray-400">({{ $row['count'] }})</span></span><span class="text-xs font-bold text-gray-900">C$ {{ number_format($row['amount'], 0) }}</span></div>
                    <div class="h-1.5 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full {{ ['bg-blue-500', 'bg-amber-500', 'bg-violet-500', 'bg-cyan-500', 'bg-teal-500'][$loop->index % 5] }}" style="width: {{ max($row['amount'] > 0 ? 3 : 0, ($row['amount'] / $pipelineMaximum) * 100) }}%"></div></div>
                </div>
            @endforeach
        </div>
    </article>

    @if($canViewDashboardFinance)
        <article x-data="{ calendarDetails: [] }" class="rounded-2xl border border-gray-100 bg-white shadow-sm lg:col-span-7">
            <header class="flex flex-col gap-3 border-b border-gray-100 px-5 py-3 sm:flex-row sm:items-start sm:justify-between">
                <div><h2 class="text-sm font-extrabold text-gray-900">Calendario de vencimientos</h2><p class="mt-0.5 text-xs capitalize text-gray-500">{{ $calendarioVencimientos['label'] }} · CXC C$ {{ number_format($totalCxcPendiente, 0) }} · CXP C$ {{ number_format($totalCxpPendiente, 0) }}</p></div>
                <div class="flex items-center gap-2"><a href="{{ route('finance.cxc') }}" class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-gray-50">Ir a CXC</a><a href="{{ route('finance.cxp') }}" class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-50">Ir a CXP</a></div>
            </header>
            <div class="p-4">
                <div class="grid grid-cols-7 border-b border-gray-100 pb-2 text-center text-[10px] font-semibold uppercase tracking-wider text-gray-400">@foreach(['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $weekday)<span>{{ $weekday }}</span>@endforeach</div>
                <div class="mt-2 grid grid-cols-7 gap-1">
                    @for($blank = 1; $blank < $calendarioVencimientos['starts_at']; $blank++)<span class="h-10 rounded-lg bg-gray-50/50"></span>@endfor
                    @for($day = 1; $day <= $calendarioVencimientos['days']; $day++)
                        @php($dayEvents = collect($calendarioVencimientos['events'][$day] ?? []))
                        @php($nativeTooltip = $dayEvents->map(fn ($event) => strtoupper($event['type']).' · '.$event['reference'].' · '.$event['name'].' · C$ '.number_format($event['amount'], 0))->implode("\n"))
                        <div @if($dayEvents->isNotEmpty()) x-on:mouseenter="calendarDetails = {{ Js::from($dayEvents->values()) }}" x-on:mouseleave="calendarDetails = []" title="{{ $nativeTooltip }}" @endif class="group relative h-10 rounded-lg border {{ $dayEvents->isNotEmpty() ? 'cursor-help border-slate-200 bg-white' : 'border-transparent bg-gray-50/60' }} p-1.5">
                            <span class="text-[11px] font-semibold {{ $day === now()->day ? 'text-slate-950 underline decoration-2 underline-offset-2' : 'text-gray-500' }}">{{ $day }}</span>
                            @if($dayEvents->isNotEmpty())
                                <div class="mt-1 flex flex-wrap gap-1">@foreach($dayEvents->take(4) as $event)<span class="h-2 w-2 rounded-full {{ $event['type'] === 'cxc' ? 'bg-slate-800' : 'bg-amber-500' }}"></span>@endforeach</div>
                            @endif
                        </div>
                    @endfor
                </div>
                <div class="mt-3 min-h-12 rounded-lg border border-dashed border-slate-200 bg-slate-50 px-3 py-2">
                    <p x-show="calendarDetails.length === 0" class="text-[11px] text-slate-500">Pasa el cursor sobre un día marcado para consultar sus vencimientos.</p>
                    <div x-show="calendarDetails.length > 0" class="flex flex-wrap gap-x-5 gap-y-1">
                        <template x-for="event in calendarDetails" :key="event.type + event.reference"><div class="flex items-center gap-2 text-[11px]"><span class="font-bold uppercase" :class="event.type === 'cxc' ? 'text-slate-700' : 'text-amber-700'" x-text="event.type"></span><span class="max-w-44 truncate text-slate-600" x-text="event.name"></span><strong class="text-slate-900" x-text="'C$ ' + Number(event.amount).toLocaleString('es-NI', {maximumFractionDigits: 0})"></strong></div></template>
                    </div>
                </div>
            </div>
        </article>
    @endif

    <div class="space-y-4 self-start {{ $canViewDashboardFinance ? 'lg:col-span-5' : 'lg:col-span-12' }}">
        <article class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
            <header class="border-b border-gray-100 px-5 py-3"><h2 class="text-sm font-extrabold text-gray-900">Distribución de órdenes</h2><p class="mt-0.5 text-xs text-gray-500">Participación actual por estado operativo</p></header>
            <div class="grid gap-4 px-5 py-3 sm:grid-cols-[120px_1fr] sm:items-center">
                <div class="relative mx-auto h-24 w-24 rounded-full" style="background: conic-gradient({{ $donutStops }});"><div class="absolute inset-3.5 flex flex-col items-center justify-center rounded-full bg-white"><strong class="text-lg font-extrabold text-gray-900">{{ collect($ordenesPorEstado)->sum('count') }}</strong><span class="text-[10px] text-gray-400">órdenes</span></div></div>
                <div class="grid gap-2 sm:grid-cols-2">@foreach($ordenesPorEstado as $index => $row)<div class="flex items-center justify-between gap-3 text-xs"><span class="flex min-w-0 items-center gap-2 text-gray-600"><i class="h-2.5 w-2.5 shrink-0 rounded-sm" style="background-color: {{ $donutColors[$index % count($donutColors)] }}"></i><span class="truncate">{{ $row['label'] }}</span></span><strong class="text-gray-900">{{ $row['count'] }}</strong></div>@endforeach</div>
            </div>
        </article>
        <article class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
            <header class="border-b border-gray-100 px-5 py-3"><h2 class="text-sm font-extrabold text-gray-900">Salud de mantenimiento</h2><p class="mt-0.5 text-xs text-gray-500">Componentes clasificados por nivel de atención</p></header>
            <div class="grid gap-4 px-5 py-3 sm:grid-cols-[120px_1fr] sm:items-center">
                <div class="relative mx-auto h-24 w-24 rounded-full" style="background: conic-gradient({{ $maintenanceStops }});"><div class="absolute inset-3.5 flex flex-col items-center justify-center rounded-full bg-white"><strong class="text-lg font-extrabold text-gray-900">{{ $maintenanceTotal }}</strong><span class="text-[10px] text-gray-400">componentes</span></div></div>
                <div class="space-y-2">@foreach($mantenimientoPorEstado as $index => $row)<div class="flex items-center justify-between gap-3 text-xs"><span class="flex items-center gap-2 text-gray-600"><i class="h-2.5 w-2.5 rounded-sm" style="background-color: {{ $maintenanceColors[$index % count($maintenanceColors)] }}"></i>{{ $row['label'] }}</span><strong class="text-gray-900">{{ $row['count'] }}</strong></div>@endforeach</div>
            </div>
        </article>
    </div>
</section>
