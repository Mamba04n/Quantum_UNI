<?php

namespace Tests\Feature;

use App\Domain\Administracion\Dashboard\Queries\GetDashboardMetrics;
use App\Models\ExchangeRate;
use App\Models\OrderStatus;
use App\Models\SaleOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_metrics_include_operational_charts(): void
    {
        $metrics = app(GetDashboardMetrics::class)->execute();

        $this->assertCount(12, $metrics->tendenciaMensual);
        $this->assertCount(5, $metrics->pipelinePorFase);
        $this->assertCount(6, $metrics->ordenesPorEstado);
        $this->assertCount(4, $metrics->mantenimientoPorEstado);
        $this->assertArrayHasKey('events', $metrics->calendarioVencimientos);
    }

    public function test_recent_usd_orders_include_the_nio_conversion_rate(): void
    {
        ExchangeRate::query()->create([
            'base_currency' => 'USD',
            'quote_currency' => 'NIO',
            'rate' => 37,
            'rate_date' => today(),
            'source' => 'manual',
        ]);

        SaleOrder::query()->create([
            'reference' => 'SO-USD-DASHBOARD',
            'date' => today(),
            'client_name' => 'Cliente USD',
            'status' => 'pending_purchase',
            'currency' => 'USD',
            'total_amount' => 736,
        ]);

        $metrics = app(GetDashboardMetrics::class)->execute();
        $order = $metrics->recentOrders->first();

        $this->assertSame('USD', $order->currency);
        $this->assertSame('37.000000', (string) $order->dashboard_exchange_rate);
        $this->assertSame(27232.0, (float) $order->total_amount * (float) $order->dashboard_exchange_rate);
        $this->assertSame('27232.00', $metrics->totalVentas);
        $this->assertSame(27232.0, collect($metrics->pipelinePorFase)->firstWhere('label', 'Compra pendiente')['amount']);
    }

    public function test_pipeline_uses_current_operational_phase_instead_of_stale_sale_order_status(): void
    {
        $order = SaleOrder::query()->create([
            'reference' => 'SO-MOVED-DASHBOARD',
            'date' => today(),
            'client_name' => 'Cliente movido',
            'status' => 'pending_purchase',
            'currency' => 'NIO',
            'total_amount' => 1000,
        ]);
        OrderStatus::query()->create([
            'sale_order_id' => $order->id,
            'administrative_status' => 'en_transito',
            'administrative_phase' => 'aduana',
        ]);

        $pipeline = collect(app(GetDashboardMetrics::class)->execute()->pipelinePorFase)->keyBy('label');

        $this->assertSame(0, $pipeline['Compra pendiente']['count']);
        $this->assertSame(1, $pipeline['En tránsito / aduanas']['count']);
        $this->assertSame(1000.0, $pipeline['En tránsito / aduanas']['amount']);
    }
}
