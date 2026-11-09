<x-app-layout>
    @php
        $canViewDashboardFinance = auth()->user()?->can('full_access')
            || auth()->user()?->can('dashboard.finance.view')
            || auth()->user()?->can('finance.view');

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

    <div class="p-4 sm:p-6 lg:p-8 space-y-6 font-sans">
        {{-- ENCABEZADO DE PÁGINA PROFESIONAL --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-extrabold tracking-tight text-gray-900">Resumen del negocio</h1>
                    <span class="inline-flex items-center rounded-md bg-rose-50 px-2 py-0.5 text-xs font-bold uppercase tracking-wider text-[#b50e0e]">Operaciones ERP</span>
                </div>
                <p class="mt-1 text-sm text-gray-500">Alertas operativas, cartera y actividad diaria &bull; {{ \Carbon\Carbon::now()->isoFormat('D [de] MMMM [de] YYYY') }}</p>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                @canany(['direct_order.create', 'direct_order.create_from_inventory'])
                <a href="{{ route('sales.direct-orders.create') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#b50e0e] hover:bg-[#9a0101] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors">
                    <x-portal.icon name="plus" class="w-4 h-4" />
                    <span>Venta directa</span>
                </a>
                @endcanany

                @can('request.create')
                <a href="{{ route('sales.customer-request.create') }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition-colors">
                    <x-portal.icon name="document" class="w-4 h-4 text-gray-400" />
                    <span>Nueva solicitud</span>
                </a>
                @endcan
            </div>
        </div>

        {{-- 4 TARJETAS DE MÉTRICAS (KPIs DE TAMAÑO COMPLETO Y LEGIBILIDAD ÓPTIMA) --}}
        <div class="grid gap-4 sm:grid-cols-2 {{ $canViewDashboardFinance ? 'lg:grid-cols-4' : '' }}">
            {{-- 1. Ventas del mes --}}
            <x-dashboard-metric label="Ventas del mes" :value="'C$ '.number_format($totalVentas, 0)" icon="chart">
                <span class="{{ $variacionVentas >= 0 ? 'text-teal-700' : 'text-rose-600' }} font-bold">
                    {{ $variacionVentas >= 0 ? '+' : '-' }}{{ number_format(abs($variacionVentas), 1) }}%
                </span>
                <span class="text-gray-400">vs mes anterior</span>
                <span class="text-gray-300">&bull;</span>
                <span>{{ $totalOrdenes }} órdenes</span>
            </x-dashboard-metric>

            {{-- 2. Cartera por cobrar (CXC) --}}
            @if($canViewDashboardFinance)
                <x-dashboard-metric label="Por cobrar (CXC)" :value="'C$ '.number_format($totalCxcPendiente, 0)" icon="wallet">
                    @if($cxcVencidas > 0)
                        <span class="text-rose-600 font-bold">{{ $cxcVencidas }} vencidas</span>
                        <span class="text-gray-400">requieren gestión</span>
                    @else
                        <span class="text-teal-700 font-semibold">Cartera al día</span>
                    @endif
                </x-dashboard-metric>

                {{-- 3. Cuentas por pagar (CXP) --}}
                <x-dashboard-metric label="Por pagar (CXP)" :value="'C$ '.number_format($totalCxpPendiente, 0)" icon="wallet">
                    @if($cxpVencidas > 0)
                        <span class="text-rose-600 font-bold">{{ $cxpVencidas }} facturas vencidas</span>
                    @else
                        <span class="text-teal-700 font-semibold">Pagos al día</span>
                    @endif
                </x-dashboard-metric>
            @endif

            {{-- 4. Pedidos activos --}}
            <x-dashboard-metric label="Pedidos en curso" :value="number_format($pedidosActivos)" icon="box">
                <span class="text-[#174a70] font-semibold">{{ $pedidosEnTransito }} en tránsito / aduanas</span>
            </x-dashboard-metric>
        </div>

        {{-- MATRIZ OPERATIVA DE 3 COLUMNAS BALANCEADAS --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">
            {{-- COLUMNA 1: PIPELINE COMERCIAL Y PEDIDOS EN SEGUIMIENTO --}}
            <div class="space-y-5">
                {{-- Pipeline comercial por fase --}}
                <article class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-xs">
                    <header class="flex items-center justify-between border-b border-gray-100 px-5 py-3.5">
                        <div>
                            <h2 class="text-sm font-bold text-gray-900">Pipeline comercial</h2>
                            <p class="text-xs text-gray-400">Importe y pedidos por etapa activa</p>
                        </div>
                        <a href="{{ route('sales.quotations.phase') }}" class="text-xs font-semibold text-[#b50e0e] hover:text-[#9a0101] transition-colors">Ver Kanban &rarr;</a>
                    </header>
                    <div class="space-y-3.5 p-5">
                        @foreach($pipelinePorFase as $row)
                            <div>
                                <div class="flex items-center justify-between gap-2 text-xs">
                                    <span class="font-medium text-gray-700 truncate">
                                        {{ $row['label'] }} <span class="text-gray-400">({{ $row['count'] }})</span>
                                    </span>
                                    <span class="font-bold text-gray-900 tabular-nums shrink-0">C$ {{ number_format($row['amount'], 0) }}</span>
                                </div>
                                <div class="h-2 overflow-hidden rounded-full bg-gray-100 mt-1.5">
                                    <div class="h-full rounded-full transition-all duration-300 {{ ['bg-blue-600', 'bg-amber-500', 'bg-violet-600', 'bg-cyan-600', 'bg-teal-600'][$loop->index % 5] }}" style="width: {{ max($row['amount'] > 0 ? 3 : 0, ($row['amount'] / $pipelineMaximum) * 100) }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </article>

                {{-- Pedidos que requieren seguimiento --}}
                <article class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-xs">
                    <header class="flex items-center justify-between border-b border-gray-100 px-5 py-3.5">
                        <div>
                            <h2 class="text-sm font-bold text-gray-900">Pedidos en seguimiento</h2>
                            <p class="text-xs text-gray-400">Órdenes activas en flujo</p>
                        </div>
                        <a href="{{ route('sales.tracker') }}" class="text-xs font-semibold text-[#b50e0e] hover:text-[#9a0101] transition-colors">Tracker &rarr;</a>
                    </header>
                    <div class="divide-y divide-gray-100">
                        @forelse($recentOrders as $order)
                            @php
                                $stageLabels = [
                                    'cotizacion' => ['Cotización', 'bg-gray-100 text-gray-700'],
                                    'solicitado_proveedor' => ['Pedido Ext.', 'bg-slate-100 text-slate-700'],
                                    'en_transito_aduanas' => ['En Tránsito', 'bg-blue-50 text-blue-700'],
                                    'pending_purchase' => ['Pend. Compra', 'bg-amber-50 text-amber-700'],
                                    'en_inventario_local' => ['En Bodega', 'bg-stone-100 text-stone-700'],
                                    'entregado' => ['Entregado', 'bg-teal-50 text-teal-700'],
                                ];
                                $dashboardStatus = $order->dashboard_status ?: $order->status;
                                $sl = $stageLabels[$dashboardStatus] ?? [str($dashboardStatus)->replace('_', ' ')->headline(), 'bg-gray-100 text-gray-600'];
                                $orderCurrency = strtoupper($order->currency ?: 'NIO');
                                $dashboardRate = $orderCurrency === 'NIO' ? 1 : (float) ($order->dashboard_exchange_rate ?: 0);
                                $dashboardTotalNio = $dashboardRate > 0 ? (float) $order->total_amount * $dashboardRate : null;
                            @endphp
                            <div class="flex items-center justify-between p-4 transition-colors hover:bg-gray-50/70">
                                <div class="min-w-0 pr-2">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="rounded-md bg-gray-100 px-1.5 py-0.5 font-mono text-[11px] font-bold text-gray-700">{{ $order->reference }}</span>
                                        <span class="rounded-md px-1.5 py-0.5 text-[11px] font-bold {{ $sl[1] }}">{{ $sl[0] }}</span>
                                    </div>
                                    <p class="text-xs font-semibold text-gray-800 truncate mt-1">{{ $order->client_name }}</p>
                                </div>
                                <div class="text-right shrink-0">
                                    <p class="text-xs sm:text-sm font-bold text-gray-900 tabular-nums">{{ $dashboardTotalNio !== null ? 'C$ '.number_format($dashboardTotalNio, 0) : $orderCurrency.' '.number_format($order->total_amount, 0) }}</p>
                                    <p class="text-[11px] text-gray-400 mt-0.5">{{ \Carbon\Carbon::parse($order->date)->format('d/m/Y') }}</p>
                                </div>
                            </div>
                        @empty
                            <div class="p-6 text-center text-xs text-gray-400">Sin pedidos activos que requieran atención inmediata.</div>
                        @endforelse
                    </div>
                </article>
            </div>

            {{-- COLUMNA 2: COBROS PRIORITARIOS (CXC) Y PAGOS A PROVEEDORES (CXP) --}}
            <div class="space-y-5">
                {{-- Cobros prioritarios (CXC) --}}
                <article class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-xs">
                    <header class="flex items-center justify-between border-b border-gray-100 px-5 py-3.5">
                        <div>
                            <h2 class="text-sm font-bold text-gray-900">Cobros prioritarios (CXC)</h2>
                            <p class="text-xs text-gray-400">Facturas vencidas o próximas a vencer</p>
                        </div>
                        <a href="{{ route('finance.cxc') }}" class="text-xs font-semibold text-[#b50e0e] hover:text-[#9a0101] transition-colors">Ver todas &rarr;</a>
                    </header>
                    <div class="divide-y divide-gray-100">
                        @forelse($cxcAlerts->take(4) as $rec)
                            @php
                                $dias = now()->diffInDays(\Carbon\Carbon::parse($rec->due_date), false);
                                $isOverdue = $dias < 0;
                            @endphp
                            <div class="flex items-center justify-between p-3.5 sm:px-4 sm:py-3 transition-colors hover:bg-gray-50/70">
                                <div class="flex items-center gap-2.5 min-w-0 pr-2">
                                    <div class="h-7 w-1 shrink-0 rounded-full {{ $isOverdue ? 'bg-rose-500' : 'bg-amber-400' }}"></div>
                                    <div class="min-w-0">
                                        <p class="text-xs font-bold text-gray-800 truncate">{{ optional($rec->entity)->name ?: $rec->concept }}</p>
                                        <p class="text-[11px] text-gray-400 truncate mt-0.5">{{ $rec->concept }} &bull; {{ $rec->invoice_number ?: 'Sin N°' }}</p>
                                    </div>
                                </div>
                                <div class="shrink-0 text-right">
                                    <p class="text-xs sm:text-sm font-bold text-gray-900 tabular-nums">C$ {{ number_format($rec->total_amount - $rec->paid_amount, 0) }}</p>
                                    <p class="text-[11px] font-bold mt-0.5 {{ $isOverdue ? 'text-rose-600' : 'text-amber-600' }}">
                                        {{ $isOverdue ? abs((int) $dias).'d vencida' : (int) $dias.'d restantes' }}
                                    </p>
                                </div>
                            </div>
                        @empty
                            <div class="p-6 text-center text-xs text-gray-400">Sin alertas de cobro pendientes.</div>
                        @endforelse
                    </div>
                </article>

                {{-- Pagos próximos a proveedores (CXP) --}}
                <article class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-xs">
                    <header class="flex items-center justify-between border-b border-gray-100 px-5 py-3.5">
                        <div>
                            <h2 class="text-sm font-bold text-gray-900">Pagos próximos (CXP)</h2>
                            <p class="text-xs text-gray-400">Compromisos con proveedores</p>
                        </div>
                        <a href="{{ route('finance.cxp') }}" class="text-xs font-semibold text-[#b50e0e] hover:text-[#9a0101] transition-colors">Ver CXP &rarr;</a>
                    </header>
                    <div class="divide-y divide-gray-100">
                        @forelse($cxpAlerts->take(4) as $pay)
                            @php
                                $dias = now()->diffInDays(\Carbon\Carbon::parse($pay->due_date), false);
                                $isOverdue = $dias < 0;
                            @endphp
                            <div class="flex items-center justify-between p-3.5 sm:px-4 sm:py-3 hover:bg-gray-50/70 transition-colors">
                                <div class="min-w-0 pr-2">
                                    <p class="text-xs font-bold text-gray-800 truncate">{{ optional($pay->entity)->name ?: $pay->concept }}</p>
                                    <p class="text-[11px] text-gray-400 truncate mt-0.5">{{ $pay->concept }}</p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <p class="text-xs sm:text-sm font-bold text-gray-900 tabular-nums">C$ {{ number_format($pay->total_amount - $pay->paid_amount, 0) }}</p>
                                    <p class="text-[11px] font-bold mt-0.5 {{ $isOverdue ? 'text-rose-600' : 'text-amber-600' }}">
                                        {{ $isOverdue ? abs((int) $dias).'d vencida' : (int) $dias.'d restantes' }}
                                    </p>
                                </div>
                            </div>
                        @empty
                            <div class="p-6 text-center text-xs text-gray-400">Sin pagos próximos a proveedores.</div>
                        @endforelse
                    </div>
                </article>
            </div>

            {{-- COLUMNA 3: CALENDARIO DE VENCIMIENTOS Y DISTRIBUCIÓN DE ÓRDENES --}}
            <div class="space-y-5">
                {{-- Calendario de vencimientos del mes --}}
                @if($canViewDashboardFinance)
                <article x-data="{ calendarDetails: [] }" class="rounded-2xl border border-gray-100 bg-white shadow-xs">
                    <header class="flex items-center justify-between border-b border-gray-100 px-5 py-3.5">
                        <div>
                            <h2 class="text-sm font-bold text-gray-900">Vencimientos del mes</h2>
                            <p class="text-xs text-gray-400">{{ \Carbon\Carbon::now()->isoFormat('MMMM YYYY') }}</p>
                        </div>
                        <div class="flex items-center gap-1.5 shrink-0">
                            <a href="{{ route('finance.cxc') }}" class="rounded-md px-2 py-0.5 text-[11px] font-bold bg-slate-100 text-slate-700 hover:bg-slate-200 transition-colors">CXC</a>
                            <a href="{{ route('finance.cxp') }}" class="rounded-md px-2 py-0.5 text-[11px] font-bold bg-rose-50 text-[#b50e0e] hover:bg-rose-100 transition-colors">CXP</a>
                        </div>
                    </header>
                    <div class="p-4 sm:p-5">
                        <div class="grid grid-cols-7 border-b border-gray-100 pb-2 text-center text-[11px] font-bold uppercase tracking-wider text-gray-400">
                            @foreach(['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $weekday)
                                <span>{{ $weekday }}</span>
                            @endforeach
                        </div>
                        <div class="mt-2 grid grid-cols-7 gap-1">
                            @for($blank = 1; $blank < $calendarioVencimientos['starts_at']; $blank++)
                                <span class="h-7 sm:h-8 rounded-lg bg-gray-50/40"></span>
                            @endfor
                            @for($day = 1; $day <= $calendarioVencimientos['days']; $day++)
                                @php($dayEvents = collect($calendarioVencimientos['events'][$day] ?? []))
                                @php($nativeTooltip = $dayEvents->map(fn ($event) => strtoupper($event['type']).' · '.$event['reference'].' · '.$event['name'].' · C$ '.number_format($event['amount'], 0))->implode("\n"))
                                <div @if($dayEvents->isNotEmpty()) x-on:mouseenter="calendarDetails = {{ Js::from($dayEvents->values()) }}" x-on:mouseleave="calendarDetails = []" title="{{ $nativeTooltip }}" @endif class="group relative h-7 sm:h-8 rounded-lg border {{ $dayEvents->isNotEmpty() ? 'cursor-help border-slate-200 bg-white shadow-2xs hover:border-[#b50e0e]' : 'border-transparent bg-gray-50/60' }} p-1 flex items-center justify-between transition-colors">
                                    <span class="text-xs font-semibold {{ $day === now()->day ? 'text-[#b50e0e] font-extrabold underline' : 'text-gray-700' }} leading-none">{{ $day }}</span>
                                    @if($dayEvents->isNotEmpty())
                                        <div class="flex items-center gap-0.5">
                                            @foreach($dayEvents->take(2) as $event)
                                                <span class="h-1.5 w-1.5 rounded-full {{ $event['type'] === 'cxc' ? 'bg-[#174a70]' : 'bg-[#b50e0e]' }}"></span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endfor
                        </div>
                        <div class="mt-3 min-h-6 rounded-lg border border-dashed border-gray-200 bg-gray-50/80 px-2.5 py-1.5 text-xs text-gray-500">
                            <span x-show="calendarDetails.length === 0" class="text-gray-400">Pasa el cursor sobre un día marcado para ver detalle.</span>
                            <div x-show="calendarDetails.length > 0" class="flex flex-wrap gap-x-3 gap-y-1">
                                <template x-for="event in calendarDetails" :key="event.type + event.reference">
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-bold uppercase text-[10px]" :class="event.type === 'cxc' ? 'text-slate-700' : 'text-[#b50e0e]'" x-text="event.type"></span>
                                        <span class="max-w-28 truncate text-gray-600" x-text="event.name"></span>
                                        <strong class="text-gray-900" x-text="'C$' + Number(event.amount).toLocaleString('es-NI', {maximumFractionDigits: 0})"></strong>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </article>
                @endif

                {{-- Distribución de órdenes --}}
                <article class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-xs">
                    <header class="flex items-center justify-between border-b border-gray-100 px-5 py-3.5">
                        <div>
                            <h2 class="text-sm font-bold text-gray-900">Distribución de órdenes</h2>
                            <p class="text-xs text-gray-400">Participación por estado en curso</p>
                        </div>
                        <a href="{{ route('orders.status.index') }}" class="text-xs font-semibold text-[#b50e0e] hover:text-[#9a0101] transition-colors">Ver órdenes &rarr;</a>
                    </header>
                    <div class="p-5 flex items-center gap-4">
                        <div class="relative h-14 w-14 shrink-0 rounded-full" style="background: conic-gradient({{ $donutStops }});">
                            <div class="absolute inset-2 flex flex-col items-center justify-center rounded-full bg-white shadow-2xs">
                                <strong class="text-sm font-bold text-gray-900 leading-none">{{ collect($ordenesPorEstado)->sum('count') }}</strong>
                                <span class="text-[8px] text-gray-400 uppercase font-semibold">órdenes</span>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-x-3 gap-y-1.5 w-full">
                            @foreach($ordenesPorEstado as $index => $row)
                                <div class="flex items-center justify-between gap-1 text-xs">
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
        </div>
    </div>
</x-app-layout>
