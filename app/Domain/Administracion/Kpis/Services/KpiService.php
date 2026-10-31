<?php

namespace App\Domain\Administracion\Kpis\Services;

use App\Models\ActivityLog;
use App\Models\Entity;
use App\Models\QuotationRequest;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class KpiService
{
    public const SENT_OFFER_STATUSES = [
        QuotationRequest::STATUS_CLIENT_OFFER_SENT,
        QuotationRequest::STATUS_APPROVED_TOTAL,
        QuotationRequest::STATUS_APPROVED_PARTIAL,
        QuotationRequest::STATUS_REJECTED,
        QuotationRequest::STATUS_REQUIRES_ADJUSTMENT,
        QuotationRequest::STATUS_APPROVED_TOTAL,
    ];

    public const APPROVED_OFFER_STATUSES = [
        QuotationRequest::STATUS_APPROVED_TOTAL,
        QuotationRequest::STATUS_APPROVED_PARTIAL,
        QuotationRequest::STATUS_APPROVED_TOTAL,
    ];

    public function getDashboard(array $filters, User $user): array
    {
        $canViewSensitiveCosts = $this->canViewSensitiveCosts($user);

        return [
            'salesMargin' => $canViewSensitiveCosts
                ? $this->getAverageSalesMargin($filters)
                : $this->hidden('Necesitas permiso kpi.view_sales_margin para ver margen y costos.'),
            'deliveryTime' => $this->getAverageDeliveryTime($filters),
            'salesVolume' => $user->can('kpi.view_sales_volume') ? $this->getSalesVolume($filters) : $this->hidden(),
            'closeRate' => $user->can('kpi.view_close_rate') ? $this->getSalesCloseRate($filters) : $this->hidden(),
            'inventoryCost' => $user->can('kpi.view_inventory_cost')
                ? $this->getInventoryTotalCost($filters)
                : $this->hidden('Necesitas permiso kpi.view_inventory_cost para ver costos de inventario.'),
            'notes' => $this->dataNotes(),
        ];
    }

    public function getAverageSalesMargin(array $filters): array
    {
        $query = DB::table('client_offer_version_items as items')
            ->join('client_offer_versions as offers', 'offers.id', '=', 'items.client_offer_version_id')
            ->join('quotation_requests as requests', 'requests.id', '=', 'offers.quotation_request_id')
            ->where('offers.status', '!=', 'draft')
            ->whereNotNull('items.unit_cost')
            ->where('items.unit_cost', '>', 0)
            ->where('items.unit_price', '>', 0);

        $this->applyOfferFilters($query, $filters);

        $totals = (clone $query)->selectRaw('
            COUNT(DISTINCT offers.id) as offers_count,
            SUM(items.quantity * items.unit_price) as sales_total,
            SUM(items.quantity * items.unit_cost) as cost_total,
            SUM(items.quantity * (items.unit_price - items.unit_cost)) as margin_total
        ')->first();

        $salesTotal = (float) ($totals->sales_total ?? 0);
        $marginTotal = (float) ($totals->margin_total ?? 0);

        $byBrand = (clone $query)
            ->selectRaw("
                COALESCE(NULLIF(items.brand, ''), 'Sin marca') as label,
                SUM(items.quantity * items.unit_price) as sales_total,
                SUM(items.quantity * (items.unit_price - items.unit_cost)) as margin_total,
                COUNT(DISTINCT offers.id) as count
            ")
            ->groupBy('label')
            ->orderByDesc('margin_total')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'label' => $row->label,
                'count' => (int) $row->count,
                'amount' => round((float) $row->margin_total, 2),
                'percentage' => (float) $row->sales_total > 0
                    ? round(((float) $row->margin_total / (float) $row->sales_total) * 100, 2)
                    : 0,
            ]);

        return [
            'available' => true,
            'averagePercent' => $salesTotal > 0 ? round(($marginTotal / $salesTotal) * 100, 2) : 0,
            'totalMoney' => round($marginTotal, 2),
            'salesAnalyzed' => (int) ($totals->offers_count ?? 0),
            'byBrand' => $byBrand,
        ];
    }

    public function getAverageDeliveryTime(array $filters): array
    {
        return [
            'available' => false,
            'message' => 'No existe un campo confiable fecha_aprobacion_compra ni una relacion factura-orden/compra para calcular fecha_factura - fecha_aprobacion_compra.',
            'missing' => [
                'purchase_orders.approved_at o approval_requests vinculada a purchase_orders',
                'invoices.sale_order_id o invoices.purchase_order_id',
            ],
            'averageDays' => null,
            'minDays' => null,
            'maxDays' => null,
            'delayedOrders' => null,
            'supplierRanking' => collect(),
        ];
    }

    public function getSalesVolume(array $filters): array
    {
        $query = DB::table('sale_order_items as items')
            ->join('sale_orders as orders', 'orders.id', '=', 'items.sale_order_id')
            ->leftJoin('products as products', 'products.id', '=', 'items.product_id')
            ->leftJoin('entities as customers', 'customers.id', '=', 'orders.customer_id')
            ->whereNotIn('orders.status', ['draft', 'cancelled', 'cancelado']);

        $this->applySalesOrderFilters($query, $filters);

        $totals = (clone $query)->selectRaw('
            COUNT(DISTINCT orders.id) as orders_count,
            SUM(items.quantity) as units_total,
            SUM(items.total_price) as sales_total
        ')->first();

        return [
            'available' => true,
            'totalMoney' => round((float) ($totals->sales_total ?? 0), 2),
            'units' => round((float) ($totals->units_total ?? 0), 2),
            'orders' => (int) ($totals->orders_count ?? 0),
            'topBrands' => $this->topFromSalesQuery($query, "COALESCE(NULLIF(items.brand, ''), NULLIF(products.brand, ''), 'Sin marca')"),
            'topCustomers' => $this->topFromSalesQuery($query, "COALESCE(customers.name, orders.client_name, 'Sin cliente')"),
            'monthlySales' => $this->monthlySales($query),
        ];
    }

    public function getSalesCloseRate(array $filters): array
    {
        $query = DB::table('quotation_requests as requests')
            ->leftJoin('client_offer_versions as offers', 'offers.quotation_request_id', '=', 'requests.id')
            ->whereIn('requests.status', self::SENT_OFFER_STATUSES);

        $this->applyCloseRateFilters($query, $filters);

        $row = (clone $query)->selectRaw('
            COUNT(DISTINCT requests.id) as sent_total,
            COUNT(DISTINCT CASE WHEN requests.status in (?, ?, ?) THEN requests.id END) as approved_total,
            COUNT(DISTINCT CASE WHEN requests.status = ? THEN requests.id END) as rejected_total,
            COUNT(DISTINCT CASE WHEN requests.status in (?, ?) THEN requests.id END) as negotiation_total
        ', [
            QuotationRequest::STATUS_APPROVED_TOTAL,
            QuotationRequest::STATUS_APPROVED_PARTIAL,
            QuotationRequest::STATUS_APPROVED_TOTAL,
            QuotationRequest::STATUS_REJECTED,
            QuotationRequest::STATUS_CLIENT_OFFER_SENT,
            QuotationRequest::STATUS_REQUIRES_ADJUSTMENT,
        ])->first();

        $sent = (int) ($row->sent_total ?? 0);
        $approved = (int) ($row->approved_total ?? 0);

        return [
            'available' => true,
            'closeRate' => $sent > 0 ? round(($approved / $sent) * 100, 2) : 0,
            'sent' => $sent,
            'approved' => $approved,
            'rejected' => (int) ($row->rejected_total ?? 0),
            'negotiation' => (int) ($row->negotiation_total ?? 0),
            'bySeller' => $this->closeRateGrouped($query, "COALESCE(users.name, 'Sin vendedor')", 'users', 'users.id', 'offers.created_by'),
            'byCustomer' => $this->closeRateGrouped($query, 'requests.quote_to_name'),
        ];
    }

    public function getInventoryTotalCost(array $filters): array
    {
        $query = DB::table('products')
            ->where('active', true)
            ->where('stock_on_hand', '>', 0);

        $this->applyInventoryFilters($query, $filters);

        $row = (clone $query)->selectRaw('
            SUM(stock_on_hand * cost) as computed_total,
            SUM(total_cost) as stored_total,
            SUM(stock_on_hand) as units_total,
            COUNT(*) as product_count
        ')->first();

        $valueExpression = 'CASE WHEN total_cost > 0 THEN total_cost ELSE stock_on_hand * cost END';

        return [
            'available' => true,
            'totalValue' => round((float) (($row->stored_total ?? 0) ?: ($row->computed_total ?? 0)), 2),
            'units' => round((float) ($row->units_total ?? 0), 2),
            'productCount' => (int) ($row->product_count ?? 0),
            'topBrands' => (clone $query)
                ->selectRaw("COALESCE(NULLIF(brand, ''), 'Sin marca') as label, SUM($valueExpression) as amount, SUM(stock_on_hand) as units")
                ->groupBy('label')
                ->orderByDesc('amount')
                ->limit(8)
                ->get(),
            'topProducts' => (clone $query)
                ->selectRaw("id, sku, name, brand, stock_on_hand, cost, $valueExpression as amount")
                ->orderByDesc('amount')
                ->limit(10)
                ->get(),
            'lowStockProducts' => (clone $query)
                ->select('id', 'sku', 'name', 'brand', 'stock_on_hand')
                ->where('stock_on_hand', '<=', 3)
                ->orderBy('stock_on_hand')
                ->limit(10)
                ->get(),
        ];
    }

    public function filterOptions(): array
    {
        $brandUnion = DB::query()
            ->fromSub(function ($query) {
                $query->from('products')->selectRaw('brand')->whereNotNull('brand')->where('brand', '!=', '')
                    ->union(DB::table('client_offer_version_items')->selectRaw('brand')->whereNotNull('brand')->where('brand', '!=', ''))
                    ->union(DB::table('sale_order_items')->selectRaw('brand')->whereNotNull('brand')->where('brand', '!=', ''));
            }, 'brands')
            ->distinct()
            ->orderBy('brand')
            ->pluck('brand');

        return [
            'customers' => Entity::where('type', 'cliente')->orderBy('name')->get(['id', 'name']),
            'suppliers' => Entity::where('type', 'proveedor')->orderBy('name')->get(['id', 'name']),
            'brands' => $brandUnion,
            'sellers' => User::orderBy('name')->get(['id', 'name']),
            'technicalCategories' => DB::table('products')->whereNotNull('technical_category')->distinct()->orderBy('technical_category')->pluck('technical_category'),
            'segments' => ['agricola', 'industrial', 'minero'],
        ];
    }

    public function exportRows(array $filters, User $user): Collection
    {
        $dashboard = $this->getDashboard($filters, $user);

        return collect([
            ['KPI', 'Valor', 'Detalle'],
            ['Margen promedio', data_get($dashboard, 'salesMargin.available') ? data_get($dashboard, 'salesMargin.averagePercent').'%' : 'Oculto/no disponible', data_get($dashboard, 'salesMargin.message')],
            ['Margen total', data_get($dashboard, 'salesMargin.totalMoney', 0), 'Solo items con costo proveedor real'],
            ['Volumen ventas', data_get($dashboard, 'salesVolume.totalMoney', 0), data_get($dashboard, 'salesVolume.orders', 0).' ordenes'],
            ['% cierre', data_get($dashboard, 'closeRate.closeRate', 0).'%', data_get($dashboard, 'closeRate.sent', 0).' cotizaciones enviadas'],
            ['Inventario costo total', data_get($dashboard, 'inventoryCost.totalValue', 0), data_get($dashboard, 'inventoryCost.units', 0).' unidades'],
            ['Tiempo entrega', data_get($dashboard, 'deliveryTime.available') ? data_get($dashboard, 'deliveryTime.averageDays') : 'No disponible', data_get($dashboard, 'deliveryTime.message')],
        ]);
    }

    public function logDashboardViewed(array $filters): void
    {
        ActivityLog::log('kpi', 'kpi.dashboard_viewed', 'Usuario entro al dashboard KPI', ['filters' => $filters]);
    }

    public function logExported(string $format, array $filters): void
    {
        ActivityLog::log('kpi', 'kpi.exported', "Usuario exporto dashboard KPI en {$format}", ['filters' => $filters]);
    }

    public function logSensitiveCostsViewed(array $filters): void
    {
        ActivityLog::log('kpi', 'kpi.sensitive_costs_viewed', 'Usuario visualizo costos sensibles en KPI', ['filters' => $filters]);
    }

    public function dataNotes(): array
    {
        return [
            'segment' => 'No hay columna de segmento comercial agricola/industrial/minero; el filtro queda documentado pero no afecta consultas hasta agregar ese campo.',
            'delivery_time' => 'Tiempo de entrega requiere fecha de aprobacion de compra y factura vinculada a orden/compra.',
            'low_stock' => 'No existe punto de reorden por producto; stock bajo usa <= 3 unidades como alerta operativa temporal.',
        ];
    }

    private function applyOfferFilters($query, array $filters): void
    {
        $query
            ->when($filters['date_from'] ?? null, fn ($q, $date) => $q->whereDate('offers.created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($q, $date) => $q->whereDate('offers.created_at', '<=', $date))
            ->when($filters['brand'] ?? null, fn ($q, $brand) => $q->where('items.brand', $brand))
            ->when($filters['seller_id'] ?? null, fn ($q, $seller) => $q->where('offers.created_by', $seller))
            ->when($filters['customer_id'] ?? null, function ($q, $customerId) {
                $customerName = Entity::whereKey($customerId)->value('name');
                $q->where('requests.quote_to_name', $customerName);
            });
    }

    private function applySalesOrderFilters($query, array $filters): void
    {
        $query
            ->when($filters['date_from'] ?? null, fn ($q, $date) => $q->whereDate('orders.date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($q, $date) => $q->whereDate('orders.date', '<=', $date))
            ->when($filters['customer_id'] ?? null, fn ($q, $id) => $q->where('orders.customer_id', $id))
            ->when($filters['seller_id'] ?? null, fn ($q, $id) => $q->where(function ($inner) use ($id) {
                $inner->where('orders.generated_by', $id)->orWhere('orders.approved_by', $id);
            }))
            ->when($filters['brand'] ?? null, fn ($q, $brand) => $q->where(function ($inner) use ($brand) {
                $inner->where('items.brand', $brand)->orWhere('products.brand', $brand);
            }))
            ->when($filters['technical_category'] ?? null, fn ($q, $category) => $q->where('products.technical_category', $category));
    }

    private function applyCloseRateFilters($query, array $filters): void
    {
        $query
            ->when($filters['date_from'] ?? null, fn ($q, $date) => $q->whereDate('requests.date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($q, $date) => $q->whereDate('requests.date', '<=', $date))
            ->when($filters['seller_id'] ?? null, fn ($q, $id) => $q->where('offers.created_by', $id))
            ->when($filters['brand'] ?? null, fn ($q, $brand) => $q->whereExists(function ($exists) use ($brand) {
                $exists->from('client_offer_version_items as filter_items')
                    ->whereColumn('filter_items.client_offer_version_id', 'offers.id')
                    ->where('filter_items.brand', $brand);
            }))
            ->when($filters['customer_id'] ?? null, function ($q, $customerId) {
                $customerName = Entity::whereKey($customerId)->value('name');
                $q->where('requests.quote_to_name', $customerName);
            });
    }

    private function applyInventoryFilters($query, array $filters): void
    {
        $query
            ->when($filters['brand'] ?? null, fn ($q, $brand) => $q->where('brand', $brand))
            ->when($filters['technical_category'] ?? null, fn ($q, $category) => $q->where('technical_category', $category));
    }

    private function topFromSalesQuery($query, string $labelExpression): Collection
    {
        return (clone $query)
            ->selectRaw("$labelExpression as label, SUM(items.total_price) as amount, SUM(items.quantity) as units, COUNT(DISTINCT orders.id) as count")
            ->groupBy('label')
            ->orderByDesc('amount')
            ->limit(8)
            ->get();
    }

    private function monthlySales($query): Collection
    {
        return (clone $query)
            ->selectRaw("DATE_FORMAT(orders.date, '%Y-%m') as label, SUM(items.total_price) as amount")
            ->groupBy('label')
            ->orderBy('label')
            ->limit(12)
            ->get();
    }

    private function closeRateGrouped($query, string $labelExpression, ?string $joinTable = null, ?string $joinLeft = null, ?string $joinRight = null): Collection
    {
        $grouped = clone $query;

        if ($joinTable && $joinLeft && $joinRight) {
            $grouped->leftJoin($joinTable, $joinLeft, '=', $joinRight);
        }

        return $grouped
            ->selectRaw("
                {$labelExpression} as label,
                COUNT(DISTINCT requests.id) as sent,
                COUNT(DISTINCT CASE WHEN requests.status in (?, ?, ?) THEN requests.id END) as approved
            ", [
                QuotationRequest::STATUS_APPROVED_TOTAL,
                QuotationRequest::STATUS_APPROVED_PARTIAL,
                QuotationRequest::STATUS_APPROVED_TOTAL,
            ])
            ->groupBy('label')
            ->orderByDesc('approved')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'label' => $row->label ?: 'Sin dato',
                'sent' => (int) $row->sent,
                'approved' => (int) $row->approved,
                'rate' => (int) $row->sent > 0 ? round(((int) $row->approved / (int) $row->sent) * 100, 2) : 0,
            ]);
    }

    private function canViewSensitiveCosts(User $user): bool
    {
        return $user->can('kpi.view_sales_margin')
            || $user->can('supplier_bid.view_costs')
            || $user->can('tracker.view_costs')
            || $user->can('finance.view');
    }

    private function hidden(string $message = 'No tienes permiso para ver este KPI.'): array
    {
        return ['available' => false, 'hidden' => true, 'message' => $message];
    }
}
