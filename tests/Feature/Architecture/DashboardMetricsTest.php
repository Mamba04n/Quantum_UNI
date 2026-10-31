<?php

namespace Tests\Feature\Architecture;

use App\Domain\Administracion\Dashboard\Queries\GetDashboardMetrics;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Entity;
use App\Models\SaleOrder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_aggregates_are_calculated_in_the_database(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-02 12:00:00'));

        SaleOrder::query()->create([
            'reference' => 'SO-DASH-1',
            'date' => today(),
            'client_name' => 'Cliente',
            'status' => 'confirmed',
            'total_amount' => 150,
        ]);
        SaleOrder::query()->create([
            'reference' => 'SO-DASH-2',
            'date' => today(),
            'client_name' => 'Cliente',
            'status' => 'cancelado',
            'total_amount' => 90,
        ]);

        $customer = Entity::query()->create(['name' => 'Cliente', 'type' => 'cliente']);
        $supplier = Entity::query()->create(['name' => 'Proveedor', 'type' => 'proveedor']);

        AccountReceivable::query()->create([
            'entity_id' => $customer->id,
            'concept' => 'Factura 1',
            'total_amount' => 200,
            'paid_amount' => 75,
            'due_date' => today()->subDay(),
            'status' => 'parcial',
        ]);
        AccountPayable::query()->create([
            'entity_id' => $supplier->id,
            'concept' => 'Compra 1',
            'total_amount' => 300,
            'paid_amount' => 50,
            'due_date' => today()->addDay(),
            'status' => 'parcial',
        ]);

        $metrics = app(GetDashboardMetrics::class)->execute();

        $this->assertSame('150.00', $metrics->totalVentas);
        $this->assertSame(2, $metrics->totalOrdenes);
        $this->assertSame(1, $metrics->pedidosActivos);
        $this->assertSame('125.00', $metrics->totalCxcPendiente);
        $this->assertSame(1, $metrics->cxcVencidas);
        $this->assertSame('250.00', $metrics->totalCxpPendiente);
        $this->assertSame(0, $metrics->cxpVencidas);
    }

    public function test_empty_dashboard_uses_a_bounded_number_of_queries(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        app(GetDashboardMetrics::class)->execute();

        $this->assertLessThanOrEqual(8, count(DB::getQueryLog()));
    }
}
