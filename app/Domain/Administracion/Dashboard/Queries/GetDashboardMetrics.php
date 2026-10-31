<?php

namespace App\Domain\Administracion\Dashboard\Queries;

use App\Domain\Administracion\Dashboard\Data\DashboardMetrics;
use App\Domain\Finanzas\Api\Support\DecimalString;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\CustomerMachineComponent;
use App\Models\ExchangeRate;
use App\Models\Payment;
use App\Models\QuotationRequest;
use App\Models\SaleOrder;
use App\Services\OrderStatusService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class GetDashboardMetrics
{
    public function execute(): DashboardMetrics
    {
        $monthStart = now()->startOfMonth();
        $previousMonthStart = $monthStart->copy()->subMonth();

        $orders = SaleOrder::query()
            ->with('orderStatus:id,sale_order_id,administrative_phase')
            ->get(['id', 'date', 'status', 'currency', 'total_amount']);
        $nioRates = $this->latestNioRates($orders);
        $activeOrders = $orders->reject(fn (SaleOrder $order) => in_array($this->dashboardStatus($order), ['entregado', 'cancelado'], true));
        $currentMonthOrders = $orders->filter(fn (SaleOrder $order) => $order->date?->gte($monthStart));
        $previousMonthOrders = $orders->filter(fn (SaleOrder $order) => $order->date?->gte($previousMonthStart) && $order->date?->lt($monthStart));
        $currentSales = $currentMonthOrders
            ->reject(fn (SaleOrder $order) => $this->dashboardStatus($order) === 'cancelado')
            ->sum(fn (SaleOrder $order) => $this->amountInNio($order, $nioRates));
        $previousSales = $previousMonthOrders
            ->reject(fn (SaleOrder $order) => $this->dashboardStatus($order) === 'cancelado')
            ->sum(fn (SaleOrder $order) => $this->amountInNio($order, $nioRates));
        $salesVariation = $previousSales > 0
            ? (($currentSales - $previousSales) / $previousSales) * 100
            : ($currentSales > 0 ? 100 : 0);
        $trendStart = $monthStart->copy()->subMonths(11);
        $payments = Payment::query()->where('payment_date', '>=', $trendStart)
            ->get(['payment_date', 'amount', 'payable_type', 'accounts_receivable_id', 'accounts_payable_id']);
        $monthlyPayments = $this->monthlyPaymentComparison($monthStart, $previousMonthStart, $payments);
        $currentOrderCount = $currentMonthOrders->count();
        $previousOrderCount = $previousMonthOrders->reject(fn (SaleOrder $order) => $this->dashboardStatus($order) === 'cancelado')->count();

        $receivables = AccountReceivable::with('entity')->where('status', '!=', 'pagado')->get();
        $payables = AccountPayable::with('entity')->where('status', '!=', 'pagado')->get();
        $components = CustomerMachineComponent::with('machine.entity')->get();
        $statusRows = $orders
            ->groupBy(fn (SaleOrder $order) => $this->dashboardStatus($order))
            ->map(fn (Collection $stageOrders) => (object) [
                'aggregate' => $stageOrders->count(),
                'amount' => $stageOrders->sum(fn (SaleOrder $order) => $this->amountInNio($order, $nioRates)),
            ]);

        $activeQuotations = QuotationRequest::query()
            ->whereNotIn('status', ['closed', 'cancelled', 'rejected'])
            ->get(['id', 'currency', 'total_amount']);
        $quotationNioRates = $this->latestNioRates($activeQuotations);
        $quotationsCount = $activeQuotations->count();
        $quotationsAmount = $activeQuotations->sum(fn ($q) => $this->amountInNio($q, $quotationNioRates));

        $statusRows->put('cotizacion', (object) [
            'aggregate' => $quotationsCount + ($statusRows->get('cotizacion')->aggregate ?? 0),
            'amount' => $quotationsAmount + ($statusRows->get('cotizacion')->amount ?? 0),
        ]);

        $receivablePending = $receivables->sum(fn (AccountReceivable $row) => (float) $row->total_amount - (float) $row->paid_amount);
        $payablePending = $payables->sum(fn (AccountPayable $row) => (float) $row->total_amount - (float) $row->paid_amount);
        $recentOrders = SaleOrder::query()
            ->with('orderStatus:id,sale_order_id,administrative_phase')
            ->addSelect([
                'dashboard_exchange_rate' => ExchangeRate::query()
                    ->select('rate')
                    ->whereColumn('base_currency', 'sale_orders.currency')
                    ->where('quote_currency', 'NIO')
                    ->latest('rate_date')
                    ->limit(1),
            ])
            ->whereIn('id', $activeOrders->pluck('id'))
            ->oldest('date')
            ->limit(5)
            ->get()
            ->each(fn (SaleOrder $order) => $order->setAttribute('dashboard_status', $this->dashboardStatus($order)));

        return new DashboardMetrics(
            totalVentas: $this->decimal($currentSales),
            totalOrdenes: $currentOrderCount,
            pedidosActivos: $activeOrders->count(),
            pedidosEnTransito: $activeOrders->filter(fn (SaleOrder $order) => in_array($this->dashboardStatus($order), ['en_transito_aduanas', 'solicitado_proveedor'], true))->count(),
            totalCxcPendiente: $this->decimal($receivablePending),
            cxcVencidas: $receivables->filter(fn (AccountReceivable $row) => $row->due_date?->isPast())->count(),
            totalCxpPendiente: $this->decimal($payablePending),
            cxpVencidas: $payables->filter(fn (AccountPayable $row) => $row->due_date?->isPast())->count(),
            variacionVentas: round($salesVariation, 1),
            cobrosMes: $this->decimal($monthlyPayments['collections_current']),
            variacionCobros: $this->variation($monthlyPayments['collections_current'], $monthlyPayments['collections_previous']),
            pagosMes: $this->decimal($monthlyPayments['payments_current']),
            variacionPagos: $this->variation($monthlyPayments['payments_current'], $monthlyPayments['payments_previous']),
            variacionOrdenes: $this->variation($currentOrderCount, $previousOrderCount),
            tendenciaMensual: $this->monthlyTrend($payments, $orders, $nioRates),
            pipelinePorFase: $this->pipelineByStage($statusRows),
            ordenesPorEstado: $this->ordersByStatus($statusRows),
            mantenimientoPorEstado: $this->maintenanceByStatus($components),
            calendarioVencimientos: $this->dueCalendar($receivables, $payables),
            cxcAlerts: $receivables->filter(fn (AccountReceivable $row) => $row->due_date && $row->due_date->lte(now()->addDays(15)))->sortBy('due_date')->take(4)->values(),
            cxpAlerts: $payables->filter(fn (AccountPayable $row) => $row->due_date && $row->due_date->lte(now()->addDays(15)))->sortBy('due_date')->take(4)->values(),
            recentOrders: $recentOrders,
            maintenanceAlerts: $components->sortBy(fn (CustomerMachineComponent $row) => sprintf('%d-%010.3f', ['vencido' => 1, 'critico' => 2, 'atencion_proxima' => 3][$row->status] ?? 4, 1000000 - (float) $row->consumed_percentage))->take(5)->values(),
        );
    }

    private function monthlyTrend(Collection $payments, Collection $orders, Collection $nioRates): array
    {
        $start = now()->startOfMonth()->subMonths(11);
        $months = collect(range(0, 11))->map(fn (int $offset) => $start->copy()->addMonths($offset));
        $sales = $orders->filter(fn (SaleOrder $order) => $order->date?->gte($start) && $this->dashboardStatus($order) !== 'cancelado')
            ->groupBy(fn (SaleOrder $order) => Carbon::parse($order->date)->format('Y-m'));
        $collections = $payments->filter(fn (Payment $payment) => $payment->payable_type === AccountReceivable::class || filled($payment->accounts_receivable_id))
            ->groupBy(fn (Payment $payment) => Carbon::parse($payment->payment_date)->format('Y-m'));

        return $months->map(fn (Carbon $month) => [
            'label' => $month->translatedFormat('M'),
            'sales' => round((float) $sales->get($month->format('Y-m'), collect())->sum(fn (SaleOrder $order) => $this->amountInNio($order, $nioRates)), 2),
            'collections' => round((float) $collections->get($month->format('Y-m'), collect())->sum('amount'), 2),
        ])->all();
    }

    private function monthlyPaymentComparison(Carbon $monthStart, Carbon $previousMonthStart, Collection $rows): array
    {
        $sum = function (string $kind, Carbon $start, Carbon $end) use ($rows): float {
            return (float) $rows->filter(function (Payment $payment) use ($kind, $start, $end): bool {
                $date = Carbon::parse($payment->payment_date);
                $isKind = $kind === 'collection'
                    ? ($payment->payable_type === AccountReceivable::class || filled($payment->accounts_receivable_id))
                    : ($payment->payable_type === AccountPayable::class || filled($payment->accounts_payable_id));

                return $isKind && $date->gte($start) && $date->lt($end);
            })->sum('amount');
        };

        $nextMonth = $monthStart->copy()->addMonth();

        return [
            'collections_current' => $sum('collection', $monthStart, $nextMonth),
            'collections_previous' => $sum('collection', $previousMonthStart, $monthStart),
            'payments_current' => $sum('payment', $monthStart, $nextMonth),
            'payments_previous' => $sum('payment', $previousMonthStart, $monthStart),
        ];
    }

    private function dueCalendar(Collection $receivables, Collection $payables): array
    {
        $start = now()->startOfMonth();
        $end = now()->endOfMonth();
        $events = collect();

        $receivables->filter(fn (AccountReceivable $row) => $row->due_date?->betweenIncluded($start, $end))
            ->each(fn (AccountReceivable $row) => $events->push([
                'day' => (int) $row->due_date->format('j'),
                'type' => 'cxc',
                'reference' => $row->receivable_code ?: $row->invoice_number ?: 'CXC',
                'name' => $row->entity?->name ?: $row->concept,
                'amount' => max(0, (float) $row->total_amount - (float) $row->paid_amount),
            ]));

        $payables->filter(fn (AccountPayable $row) => $row->due_date?->betweenIncluded($start, $end))
            ->each(fn (AccountPayable $row) => $events->push([
                'day' => (int) $row->due_date->format('j'),
                'type' => 'cxp',
                'reference' => $row->payable_code ?: $row->invoice_number ?: 'CXP',
                'name' => $row->entity?->name ?: $row->concept,
                'amount' => max(0, (float) $row->total_amount - (float) $row->paid_amount),
            ]));

        return [
            'label' => now()->translatedFormat('F Y'),
            'days' => now()->daysInMonth,
            'starts_at' => now()->startOfMonth()->dayOfWeekIso,
            'events' => $events->groupBy('day')->map->values()->all(),
        ];
    }

    private function pipelineByStage(Collection $rows): array
    {
        $labels = [
            'cotizacion' => 'Cotización',
            'pending_purchase' => 'Compra pendiente',
            'solicitado_proveedor' => 'Solicitado al proveedor',
            'en_transito_aduanas' => 'En tránsito / aduanas',
            'en_inventario_local' => 'Inventario local',
        ];

        return collect($labels)->map(fn (string $label, string $status) => [
            'label' => $label,
            'count' => (int) ($rows->get($status)?->aggregate ?? 0),
            'amount' => round((float) ($rows->get($status)?->amount ?? 0), 2),
        ])->values()->all();
    }

    private function ordersByStatus(Collection $rows): array
    {
        $labels = [
            'cotizacion' => 'Cotización',
            'pending_purchase' => 'Compra pendiente',
            'solicitado_proveedor' => 'Proveedor',
            'en_transito_aduanas' => 'Tránsito',
            'en_inventario_local' => 'Inventario',
            'entregado' => 'Entregado',
        ];

        return collect($labels)->map(fn (string $label, string $status) => [
            'label' => $label,
            'count' => (int) ($rows->get($status)?->aggregate ?? 0),
        ])->values()->all();
    }

    private function maintenanceByStatus(Collection $components): array
    {
        $labels = ['vencido' => 'Vencido', 'critico' => 'Crítico', 'atencion_proxima' => 'Atención próxima', 'bueno' => 'Estable'];
        $counts = $components->countBy('status');

        return collect($labels)->map(fn (string $label, string $status) => [
            'label' => $label,
            'count' => (int) ($counts[$status] ?? 0),
        ])->values()->all();
    }

    private function decimal(mixed $value): string
    {
        return DecimalString::normalize($value, 2) ?? '0.00';
    }

    private function variation(float|int $current, float|int $previous): float
    {
        if ((float) $previous === 0.0) {
            return (float) $current > 0 ? 100 : 0;
        }

        return round((((float) $current - (float) $previous) / (float) $previous) * 100, 1);
    }

    private function latestNioRates(Collection $orders): Collection
    {
        $currencies = $orders->pluck('currency')
            ->map(fn ($currency) => strtoupper(trim((string) ($currency ?: 'NIO'))))
            ->reject(fn (string $currency) => $currency === 'NIO')
            ->unique()
            ->values();

        if ($currencies->isEmpty()) {
            return collect();
        }

        return ExchangeRate::query()
            ->whereIn('base_currency', $currencies)
            ->where('quote_currency', 'NIO')
            ->latest('rate_date')
            ->latest('id')
            ->get(['base_currency', 'rate'])
            ->unique(fn (ExchangeRate $rate) => strtoupper($rate->base_currency))
            ->mapWithKeys(fn (ExchangeRate $rate) => [strtoupper($rate->base_currency) => (float) $rate->rate]);
    }

    private function amountInNio(mixed $order, Collection $nioRates): float
    {
        $currency = strtoupper(trim((string) ($order->currency ?: 'NIO')));
        $rate = $currency === 'NIO' ? 1.0 : (float) $nioRates->get($currency, 0);

        return round((float) $order->total_amount * $rate, 2);
    }

    private function dashboardStatus(SaleOrder $order): string
    {
        return match ($order->orderStatus?->administrative_phase) {
            OrderStatusService::PHASE_PURCHASE => 'pending_purchase',
            OrderStatusService::PHASE_SUPPLIER_CONFIRMATION => 'solicitado_proveedor',
            OrderStatusService::PHASE_TRANSIT,
            OrderStatusService::PHASE_CUSTOMS => 'en_transito_aduanas',
            OrderStatusService::PHASE_WAREHOUSE,
            OrderStatusService::PHASE_KARDEX_ENTRY,
            OrderStatusService::PHASE_KARDEX_OUTPUT => 'en_inventario_local',
            OrderStatusService::PHASE_LOGISTICS => 'en_ruta',
            OrderStatusService::PHASE_DELIVERY => 'entregado',
            default => match ((string) $order->status) {
                'generated', 'confirmed', 'processing' => 'pending_purchase',
                'completed' => 'entregado',
                default => (string) ($order->status ?: 'pending_purchase'),
            },
        };
    }
}
