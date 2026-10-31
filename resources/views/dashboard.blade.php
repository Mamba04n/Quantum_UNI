<x-app-layout>
    @php
        $canViewDashboardFinance = auth()->user()?->can('full_access')
            || auth()->user()?->can('dashboard.finance.view')
            || auth()->user()?->can('finance.view');
    @endphp

    <div class="space-y-4 p-6 font-sans sm:p-8">
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
            <div class="min-w-0 flex-1">
                <h1 class="text-2xl font-extrabold tracking-tight text-gray-900">Resumen del negocio</h1>
                <p class="mt-0.5 text-sm text-gray-500">Resultados, alertas y actividad · {{ \Carbon\Carbon::now()->isoFormat('D [de] MMMM [de] YYYY') }}</p>
            </div>

            @canany(['direct_order.create', 'direct_order.create_from_inventory'])
            <a href="{{ route('sales.direct-orders.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-slate-800 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition-colors hover:bg-slate-900">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Nueva venta directa
            </a>
            @endcanany
        </div>

        <div class="grid gap-4 sm:grid-cols-2 {{ $canViewDashboardFinance ? 'lg:grid-cols-4' : '' }}">
            <x-dashboard-metric :compact="true" label="Ventas del mes" :value="'C$ '.number_format($totalVentas, 0)" icon="chart" tone="blue">
                <span class="{{ $variacionVentas >= 0 ? 'text-teal-700' : 'text-rose-600' }}">{{ $variacionVentas >= 0 ? '+' : '-' }} {{ number_format(abs($variacionVentas), 1) }} %</span> frente al mes anterior
            </x-dashboard-metric>
            @if($canViewDashboardFinance)
                <x-dashboard-metric :compact="true" label="Cobros del mes" :value="'C$ '.number_format($cobrosMes, 0)" icon="wallet" tone="teal">
                    <span class="{{ $variacionCobros >= 0 ? 'text-teal-700' : 'text-rose-600' }}">{{ $variacionCobros >= 0 ? '+' : '-' }} {{ number_format(abs($variacionCobros), 1) }} %</span> frente al mes anterior
                </x-dashboard-metric>
                <x-dashboard-metric :compact="true" label="Pagos del mes" :value="'C$ '.number_format($pagosMes, 0)" icon="wallet" tone="amber">
                    {{ $variacionPagos >= 0 ? '+' : '-' }} {{ number_format(abs($variacionPagos), 1) }} % frente al mes anterior
                </x-dashboard-metric>
            @endif
            <x-dashboard-metric :compact="true" label="Órdenes del mes" :value="number_format($totalOrdenes)" icon="box" tone="violet">
                <span class="{{ $variacionOrdenes >= 0 ? 'text-teal-700' : 'text-rose-600' }}">{{ $variacionOrdenes >= 0 ? '+' : '-' }} {{ number_format(abs($variacionOrdenes), 1) }} %</span> frente al mes anterior
            </x-dashboard-metric>
        </div>

        @include('dashboard.analytics')

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="contents">
                @if($canViewDashboardFinance)
                <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm lg:order-3">
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                        <h2 class="text-sm font-extrabold text-gray-900">Alertas de cobro</h2>
                        <a href="{{ route('finance.cxc') }}" class="text-xs font-bold text-slate-600 hover:text-slate-900">Ver todas</a>
                    </div>
                    <div class="divide-y divide-gray-50">
                        @forelse($cxcAlerts as $rec)
                            @php
                                $dias = now()->diffInDays(\Carbon\Carbon::parse($rec->due_date), false);
                                $isOverdue = $dias < 0;
                            @endphp
                            <div class="flex items-center justify-between px-5 py-3 transition-colors hover:bg-gray-50/50">
                                <div class="flex items-center gap-3">
                                    <div class="h-8 w-1.5 shrink-0 rounded-full {{ $isOverdue ? 'bg-rose-500' : 'bg-amber-400' }}"></div>
                                    <div>
                                        <p class="text-xs font-bold text-gray-900">{{ optional($rec->entity)->name ?: $rec->concept }}</p>
                                        <p class="mt-0.5 text-[10px] text-gray-500">{{ $rec->concept }} &bull; {{ $rec->invoice_number ?: 'Sin N°' }}</p>
                                    </div>
                                </div>
                                <div class="ml-4 shrink-0 text-right">
                                    <p class="text-sm font-black text-gray-900">C$ {{ number_format($rec->total_amount - $rec->paid_amount, 0) }}</p>
                                    <p class="text-[10px] font-bold {{ $isOverdue ? 'text-rose-600' : 'text-amber-600' }}">
                                        {{ $isOverdue ? abs((int) $dias).'d vencida' : (int) $dias.'d restantes' }}
                                    </p>
                                </div>
                            </div>
                        @empty
                            <div class="px-5 py-4 text-center text-xs text-gray-400">Sin alertas pendientes.</div>
                        @endforelse
                    </div>
                </div>
                @endif

                <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm lg:order-1">
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                        <h2 class="text-sm font-extrabold text-gray-900">Pedidos que requieren seguimiento</h2>
                        <a href="{{ route('sales.tracker') }}" class="text-xs font-bold text-slate-600 hover:text-slate-900">Pipeline completo</a>
                    </div>
                    <div class="divide-y divide-gray-50">
                        @forelse($recentOrders as $order)
                            @php
                                $stageLabels = [
                                    'cotizacion' => ['Cotizacion', 'bg-gray-100 text-gray-700'],
                                    'solicitado_proveedor' => ['Pedido Ext.', 'bg-slate-100 text-slate-700'],
                                    'en_transito_aduanas' => ['En Transito', 'bg-zinc-100 text-zinc-700'],
                                    'pending_purchase' => ['Pendiente compra', 'bg-amber-50 text-amber-700'],
                                    'en_inventario_local' => ['En Bodega', 'bg-stone-100 text-stone-700'],
                                    'entregado' => ['Entregado', 'bg-teal-100 text-teal-700'],
                                ];
                                $dashboardStatus = $order->dashboard_status ?: $order->status;
                                $sl = $stageLabels[$dashboardStatus] ?? [str($dashboardStatus)->replace('_', ' ')->headline(), 'bg-gray-100 text-gray-600'];
                                $orderCurrency = strtoupper($order->currency ?: 'NIO');
                                $dashboardRate = $orderCurrency === 'NIO' ? 1 : (float) ($order->dashboard_exchange_rate ?: 0);
                                $dashboardTotalNio = $dashboardRate > 0 ? (float) $order->total_amount * $dashboardRate : null;
                            @endphp
                            <div class="flex items-center justify-between px-5 py-3 transition-colors hover:bg-gray-50/50">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-[10px] font-black text-gray-700">{{ $order->reference }}</span>
                                        <span class="rounded px-1.5 py-0.5 text-[10px] font-bold {{ $sl[1] }}">{{ $sl[0] }}</span>
                                    </div>
                                    <p class="mt-1 text-xs font-bold text-gray-700">{{ $order->client_name }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-black text-gray-900">{{ $dashboardTotalNio !== null ? 'C$ '.number_format($dashboardTotalNio, 2) : $orderCurrency.' '.number_format($order->total_amount, 2) }}</p>
                                    @if($orderCurrency !== 'NIO' && $dashboardRate > 0)
                                        <p class="text-[10px] font-medium text-gray-500">{{ $orderCurrency }} {{ number_format($order->total_amount, 2) }} · tasa {{ number_format($dashboardRate, 4) }}</p>
                                    @endif
                                    <p class="text-[10px] text-gray-400">{{ \Carbon\Carbon::parse($order->date)->format('d/m/Y') }}</p>
                                </div>
                            </div>
                        @empty
                            <div class="px-5 py-4 text-center text-xs text-gray-400">Sin pedidos activos.</div>
                        @endforelse
                    </div>
                </div>

                @if($canViewDashboardFinance)
                <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm lg:order-2">
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                        <div><h2 class="text-sm font-extrabold text-gray-900">Mantenimiento prioritario</h2><p class="mt-0.5 text-[11px] text-gray-500">{{ collect($mantenimientoPorEstado)->whereIn('label', ['Vencido', 'Crítico'])->sum('count') }} componentes críticos o vencidos</p></div>
                        <a href="{{ route('clients.index') }}" class="text-xs font-bold text-slate-600 hover:text-slate-900">Ver clientes</a>
                    </div>
                    <div class="divide-y divide-gray-50">
                        @forelse($maintenanceAlerts as $component)
                            @php
                                $badge = match($component->status) {
                                    'vencido' => 'bg-rose-100 text-rose-700',
                                    'critico' => 'bg-red-50 text-red-700',
                                    'atencion_proxima' => 'bg-amber-50 text-amber-700',
                                    default => 'bg-emerald-50 text-emerald-700',
                                };
                            @endphp
                            <div class="flex items-center justify-between gap-4 px-5 py-3 hover:bg-gray-50/50">
                                <div>
                                    <p class="text-xs font-black text-gray-900">{{ $component->machine->entity->name ?? 'Cliente' }} · {{ $component->machine->name ?? 'Maquina' }}</p>
                                    <p class="text-[10px] text-gray-500">{{ $component->component_name }} · {{ $component->part_number }} · {{ $component->serial_number ?: 'sin serie' }}</p>
                                </div>
                                <div class="text-right">
                                    <span class="rounded px-2 py-1 text-[10px] font-black {{ $badge }}">{{ str_replace('_', ' ', $component->status) }}</span>
                                    <p class="mt-1 text-[10px] text-gray-500">{{ $component->current_usage }}/{{ $component->estimated_life }} {{ $component->counter_type }} · residual {{ $component->residual_life }}</p>
                                </div>
                            </div>
                        @empty
                            <div class="px-5 py-4 text-center text-xs text-gray-400">Sin componentes registrados.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="contents">
                <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm lg:order-4">
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                        <h2 class="text-sm font-extrabold text-gray-900">Pagos próximos</h2>
                        <a href="{{ route('finance.cxp') }}" class="text-xs font-bold text-slate-600 hover:text-slate-900">Ver CXP</a>
                    </div>
                    <div class="divide-y divide-gray-50">
                        @forelse($cxpAlerts as $pay)
                            @php
                                $dias = now()->diffInDays(\Carbon\Carbon::parse($pay->due_date), false);
                                $isOverdue = $dias < 0;
                            @endphp
                            <div class="flex items-center justify-between px-5 py-3">
                                <div>
                                    <p class="text-xs font-bold text-gray-900">{{ optional($pay->entity)->name ?: $pay->concept }}</p>
                                    <p class="mt-0.5 text-[10px] text-gray-500">{{ $pay->concept }}</p>
                                </div>
                                <div class="ml-3 shrink-0 text-right">
                                    <p class="text-xs font-black text-gray-900">C$ {{ number_format($pay->total_amount - $pay->paid_amount, 0) }}</p>
                                    <p class="text-[10px] font-bold {{ $isOverdue ? 'text-rose-600' : 'text-amber-600' }}">
                                        {{ $isOverdue ? abs((int) $dias).'d vencida' : (int) $dias.'d restantes' }}
                                    </p>
                                </div>
                            </div>
                        @empty
                            <div class="px-5 py-4 text-center text-xs text-gray-400">Sin pagos proximos.</div>
                        @endforelse
                    </div>
                </div>
                @endif

            </div>
        </div>
    </div>
</x-app-layout>
