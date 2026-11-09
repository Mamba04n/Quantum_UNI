<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

final class WorkspaceNavigation
{
    public function allows(User $user, string $name): bool
    {
        if (in_array($name, config('academic.hidden_modules', []), true)) {
            return false;
        }
        $route = Route::getRoutes()->getByName($name);
        if (! $route || ($name === 'dashboard' && ! $user->canAccess('dashboard'))) {
            return false;
        }
        // Keep the warehouse navigation restriction used by the existing menu.
        if (in_array($name, ['purchases.create', 'sales.tracker'], true) && $user->hasRole(User::ROLE_BODEGA)) {
            return false;
        }
        // Each middleware group is required; permissions within a group are alternatives.
        foreach ($route->gatherMiddleware() as $middleware) {
            if (str_starts_with($middleware, 'permission:') && ! $user->hasAnyPermission(explode(',', substr($middleware, 11)))) {
                return false;
            }
        }

        return true;
    }

    public function areas(User $user): array
    {
        $areas = [];
        foreach (config('workspaces') as $key => $area) {
            $area['items'] = array_filter($area['items'], fn ($name) => $this->allows($user, $name), ARRAY_FILTER_USE_KEY);
            if ($area['items']) {
                $areas[$key] = $area;
            }
        }

        return $areas;
    }

    public function current(array $areas, ?string $route): ?string
    {
        if (request()->routeIs('workspace') && request()->route('area')) {
            $routeArea = (string) request()->route('area');
            if (isset($areas[$routeArea])) {
                return $routeArea;
            }
        }

        // Receiving belongs to Warehouse even when reached from the purchasing flow.
        if ($route === 'warehouse.incoming-orders' && isset($areas['bodega']['items'][$route])) {
            return 'bodega';
        }
        $aliases = [
            'sales.quotations.licitacion' => 'sales.quotations.phase',
            'sales.quotations.oferta' => 'sales.quotations.phase',
            'sales.quotations.aprobacion' => 'sales.quotations.phase',
            'clients.profile' => 'clients.index',
            'orders.status.show' => 'orders.status.index',
            'sales.quotations.kanban' => 'sales.quotations.phase',
            'sales.quotations.phase.customer-request' => 'sales.customer-request.create',
            'sales.quotations.phase.customer-request.alias' => 'sales.customer-request.create',
            'sales.quotations.phase.order-corrections' => 'sales.quotations.corrections',
            'admin.approvals.purchase-document' => 'admin.approvals',
        ];
        $targetRoute = $aliases[$route] ?? $route;
        foreach ($areas as $key => $area) {
            if (isset($area['items'][$targetRoute])) {
                return $key;
            }
        }

        if ($route) {
            $prefixMap = [
                'sales.' => 'ventas',
                'orders.' => 'ventas',
                'purchases.' => 'proveedores',
                'inventory.' => 'bodega',
                'warehouse.' => 'bodega',
                'finance.' => 'finanzas',
                'clients.' => 'clientes',
                'admin.suppliers' => 'proveedores',
                'admin.users' => 'equipo',
                'admin.chat' => 'equipo',
                'dashboard' => 'reportes',
                'admin.kpis' => 'reportes',
                'admin.exports' => 'reportes',
                'admin.activity' => 'reportes',
                'web.' => 'configuracion',
                'profile' => 'configuracion',
            ];
            foreach ($prefixMap as $prefix => $areaKey) {
                if (str_starts_with($route, $prefix) && isset($areas[$areaKey])) {
                    return $areaKey;
                }
            }
        }

        return null;
    }

    public function currentModule(?string $route): ?string
    {
        $aliases = [
            'sales.quotations.licitacion' => 'sales.quotations.phase',
            'sales.quotations.oferta' => 'sales.quotations.phase',
            'sales.quotations.aprobacion' => 'sales.quotations.phase',
            'clients.profile' => 'clients.index',
            'orders.status.show' => 'orders.status.index',
            'sales.quotations.kanban' => 'sales.quotations.phase',
            'sales.quotations.phase.customer-request' => 'sales.customer-request.create',
            'sales.quotations.phase.customer-request.alias' => 'sales.customer-request.create',
            'sales.quotations.phase.order-corrections' => 'sales.quotations.corrections',
            'admin.approvals.purchase-document' => 'admin.approvals',
        ];

        return $aliases[$route] ?? $route;
    }
}
