@php
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
@endphp

<section class="grid gap-3.5 lg:grid-cols-12" aria-label="Control Operativo y Financiero">
    {{-- COLUMNA IZQUIERDA: Pipeline comercial + Distribución de órdenes --}}
    <div class="grid gap-3.5 sm:grid-cols-2 lg:col-span-6">
        {{-- Pipeline por fase --}}
        <article class="overflow-hidden rounded-xl border border-[#e8e8e9] bg-white shadow-2xs flex flex-col justify-between">
            <header class="flex items-center justify-between border-b border-[#e8e8e9] px-3.5 py-2">
                <div>
                    <h2 class="text-xs font-bold text-[#1e1e1e]">Pipeline comercial</h2>
                    <p class="text-[10px] text-gray-400 font-medium">Importe y pedidos por etapa</p>
                </div>
                <a href="{{ route('sales.quotations.phase') }}" class="text-[10px] font-semibold text-[#b50e0e] hover:text-[#9a0101] transition-colors">Ver Kanban</a>
            </header>
            <div class="space-y-2 p-3">
                @foreach($pipelinePorFase as $row)
                    <div>
                        <div class="mb-1 flex items-center justify-between gap-2 text-xs">
                            <span class="text-[11px] font-semibold text-gray-700 truncate">
                                {{ $row['label'] }} <span class="font-normal text-gray-400 text-[10px]">({{ $row['count'] }})</span>
                            </span>
                            <span class="text-[11px] font-bold text-gray-900 tabular-nums shrink-0">C$ {{ number_format($row['amount'], 0) }}</span>
                        </div>
                        <div class="h-1.5 overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full rounded-full {{ ['bg-blue-500', 'bg-amber-500', 'bg-violet-500', 'bg-cyan-500', 'bg-teal-500'][$loop->index % 5] }}" style="width: {{ max($row['amount'] > 0 ? 3 : 0, ($row['amount'] / $pipelineMaximum) * 100) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </article>

        {{-- Distribución de órdenes --}}
        <article class="overflow-hidden rounded-xl border border-[#e8e8e9] bg-white shadow-2xs flex flex-col justify-between">
            <header class="flex items-center justify-between border-b border-[#e8e8e9] px-3.5 py-2">
                <div>
                    <h2 class="text-xs font-bold text-[#1e1e1e]">Distribución de órdenes</h2>
                    <p class="text-[10px] text-gray-400 font-medium">Estado actual del flujo</p>
                </div>
                <a href="{{ route('orders.status.index') }}" class="text-[10px] font-semibold text-[#b50e0e] hover:text-[#9a0101] transition-colors">Ver órdenes</a>
            </header>
            <div class="p-3 flex flex-col items-center justify-center gap-3">
                <div class="relative mx-auto h-20 w-20 rounded-full shrink-0" style="background: conic-gradient({{ $donutStops }});">
                    <div class="absolute inset-2.5 flex flex-col items-center justify-center rounded-full bg-white shadow-xs">
                        <strong class="text-base font-bold text-gray-900 leading-none">{{ collect($ordenesPorEstado)->sum('count') }}</strong>
                        <span class="text-[8px] text-gray-400 uppercase font-semibold">órdenes</span>
                    </div>
                </div>
                <div class="w-full grid grid-cols-2 gap-x-2 gap-y-1">
                    @foreach($ordenesPorEstado as $index => $row)
                        <div class="flex items-center justify-between gap-1 text-[10px]">
                            <span class="flex min-w-0 items-center gap-1.5 text-gray-600">
                                <i class="h-2 w-2 shrink-0 rounded-xs" style="background-color: {{ $donutColors[$index % count($donutColors)] }}"></i>
                                <span class="truncate">{{ $row['label'] }}</span>
                            </span>
                            <strong class="text-gray-900 tabular-nums shrink-0">{{ $row['count'] }}</strong>
                        </div>
                    @endforeach
                </div>
            </div>
        </article>
    </div>

    {{-- COLUMNA DERECHA: Calendario de vencimientos del mes --}}
    @if($canViewDashboardFinance)
        <article x-data="{ calendarDetails: [] }" class="rounded-xl border border-[#e8e8e9] bg-white shadow-2xs lg:col-span-6 flex flex-col justify-between">
            <header class="flex items-center justify-between border-b border-[#e8e8e9] px-4 py-2">
                <div>
                    <h2 class="text-xs font-bold text-[#1e1e1e]">Calendario de vencimientos financieros</h2>
                    <p class="text-[10px] text-gray-400 capitalize font-medium">{{ $calendarioVencimientos['label'] }} · Cobros C$ {{ number_format($totalCxcPendiente, 0) }} · Pagos C$ {{ number_format($totalCxpPendiente, 0) }}</p>
                </div>
                <div class="flex items-center gap-1.5 shrink-0">
                    <a href="{{ route('finance.cxc') }}" class="rounded-lg border border-gray-200 bg-white px-2 py-0.5 text-[10px] font-semibold text-slate-700 hover:bg-gray-50 transition-colors">CXC</a>
                    <a href="{{ route('finance.cxp') }}" class="rounded-lg border border-gray-200 bg-white px-2 py-0.5 text-[10px] font-semibold text-[#b50e0e] hover:bg-rose-50 transition-colors">CXP</a>
                </div>
            </header>
            <div class="p-3">
                <div class="grid grid-cols-7 border-b border-gray-100 pb-1 text-center text-[9px] font-bold uppercase tracking-wider text-gray-400">
                    @foreach(['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $weekday)
                        <span>{{ $weekday }}</span>
                    @endforeach
                </div>
                <div class="mt-1.5 grid grid-cols-7 gap-1">
                    @for($blank = 1; $blank < $calendarioVencimientos['starts_at']; $blank++)
                        <span class="h-7 sm:h-8 rounded-lg bg-gray-50/40"></span>
                    @endfor
                    @for($day = 1; $day <= $calendarioVencimientos['days']; $day++)
                        @php($dayEvents = collect($calendarioVencimientos['events'][$day] ?? []))
                        @php($nativeTooltip = $dayEvents->map(fn ($event) => strtoupper($event['type']).' · '.$event['reference'].' · '.$event['name'].' · C$ '.number_format($event['amount'], 0))->implode("\n"))
                        <div @if($dayEvents->isNotEmpty()) x-on:mouseenter="calendarDetails = {{ Js::from($dayEvents->values()) }}" x-on:mouseleave="calendarDetails = []" title="{{ $nativeTooltip }}" @endif class="group relative h-7 sm:h-8 rounded-md border {{ $dayEvents->isNotEmpty() ? 'cursor-help border-slate-200 bg-white shadow-2xs' : 'border-transparent bg-gray-50/50' }} p-1 flex flex-col justify-between">
                            <span class="text-[10px] font-semibold {{ $day === now()->day ? 'text-[#b50e0e] font-extrabold underline decoration-2' : 'text-gray-600' }} leading-none">{{ $day }}</span>
                            @if($dayEvents->isNotEmpty())
                                <div class="flex items-center gap-0.5">
                                    @foreach($dayEvents->take(3) as $event)
                                        <span class="h-1.5 w-1.5 rounded-full {{ $event['type'] === 'cxc' ? 'bg-[#174a70]' : 'bg-[#b50e0e]' }}"></span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endfor
                </div>
                <div class="mt-2 min-h-7 rounded-lg border border-dashed border-gray-200 bg-gray-50/80 px-2.5 py-1 flex items-center">
                    <p x-show="calendarDetails.length === 0" class="text-[10px] text-gray-400">Pasa el cursor sobre un día con puntos para ver cobros y pagos.</p>
                    <div x-show="calendarDetails.length > 0" class="flex flex-wrap gap-x-4 gap-y-0.5">
                        <template x-for="event in calendarDetails" :key="event.type + event.reference">
                            <div class="flex items-center gap-1.5 text-[10px]">
                                <span class="font-bold uppercase" :class="event.type === 'cxc' ? 'text-slate-700' : 'text-[#b50e0e]'" x-text="event.type"></span>
                                <span class="max-w-36 truncate text-gray-600" x-text="event.name"></span>
                                <strong class="text-gray-900" x-text="'C$ ' + Number(event.amount).toLocaleString('es-NI', {maximumFractionDigits: 0})"></strong>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </article>
    @endif
</section>
