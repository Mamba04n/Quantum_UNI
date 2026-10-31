<?php

namespace App\Support;

use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\ActivityLog;
use App\Models\ApprovalRequest;
use App\Models\Entity;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\QuotationRequest;
use App\Models\SaleOrder;
use App\Models\StockMove;
use App\Models\Ticket;
use App\Models\User;

final class WorkspaceDashboard
{
    public function summary(string $area, array $items): array
    {
        $sources = match ($area) {
            'ventas' => [
                ['sales.quotations.phase', 'Solicitudes de cotización', fn () => QuotationRequest::query()],
                ['orders.status.index', 'Órdenes de venta', fn () => SaleOrder::query()],
            ],
            'finanzas' => [
                ['finance.cxc', 'Cuentas por cobrar', fn () => AccountReceivable::query()],
                ['finance.cxp', 'Cuentas por pagar', fn () => AccountPayable::query()],
            ],
            'bodega' => [
                ['inventory.products', 'Productos registrados', fn () => Product::query()],
                ['inventory.moves', 'Movimientos de inventario', fn () => StockMove::query()],
            ],
            'clientes' => [
                ['clients.index', 'Clientes registrados', fn () => Entity::where('type', 'cliente')],
                ['admin.tickets', 'Tickets registrados', fn () => Ticket::query()],
            ],
            'proveedores' => [
                ['admin.suppliers', 'Proveedores registrados', fn () => Entity::where('type', 'proveedor')],
                ['purchases.create', 'Órdenes de compra', fn () => PurchaseOrder::query()],
            ],
            'operaciones' => [
                ['admin.approvals', 'Solicitudes de aprobación', fn () => ApprovalRequest::query()],
            ],
            'equipo' => [
                ['admin.users', 'Usuarios internos', fn () => User::whereHas('roles', fn ($q) => $q->where('slug', '!=', User::ROLE_CLIENTE_TIENDA))],
            ],
            'reportes' => [
                ['admin.activity', 'Eventos de auditoría', fn () => ActivityLog::query()],
            ],
            default => [],
        };
        $months = collect(range(5, 0))->map(fn ($offset) => now()->startOfMonth()->subMonths($offset));
        $series = [];
        foreach ($sources as [$route, $label, $query]) {
            // Items have already been filtered by the actual route middleware permissions.
            if (! isset($items[$route])) {
                continue;
            }
            $builder = $query();
            $period = $builder->getConnection()->getDriverName() === 'sqlite'
                ? "strftime('%Y-%m', created_at)" : "DATE_FORMAT(created_at, '%Y-%m')";
            $counts = $builder->selectRaw($period.' AS period, COUNT(*) AS aggregate')->where('created_at', '>=', $months->first())
                ->where('created_at', '<', now()->startOfMonth()->addMonth())
                ->groupBy('period')->pluck('aggregate', 'period');
            $series[] = [
                'route' => $route, 'label' => $label, 'value' => $query()->count(),
                'values' => $months->map(fn ($month) => (int) $counts->get($month->format('Y-m'), 0))->all(),
            ];
        }

        return ['series' => $series, 'months' => $months->map(fn ($month) => $month->locale('es')->translatedFormat('M Y'))->all()];
    }
}
