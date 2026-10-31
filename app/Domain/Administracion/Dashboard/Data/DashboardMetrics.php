<?php

namespace App\Domain\Administracion\Dashboard\Data;

use Illuminate\Support\Collection;

final readonly class DashboardMetrics
{
    public function __construct(
        public string $totalVentas,
        public int $totalOrdenes,
        public int $pedidosActivos,
        public int $pedidosEnTransito,
        public string $totalCxcPendiente,
        public int $cxcVencidas,
        public string $totalCxpPendiente,
        public int $cxpVencidas,
        public float $variacionVentas,
        public string $cobrosMes,
        public float $variacionCobros,
        public string $pagosMes,
        public float $variacionPagos,
        public float $variacionOrdenes,
        public array $tendenciaMensual,
        public array $pipelinePorFase,
        public array $ordenesPorEstado,
        public array $mantenimientoPorEstado,
        public array $calendarioVencimientos,
        public Collection $cxcAlerts,
        public Collection $cxpAlerts,
        public Collection $recentOrders,
        public Collection $maintenanceAlerts,
    ) {}

    /** @return array<string, mixed> */
    public function toViewData(): array
    {
        return get_object_vars($this);
    }
}
