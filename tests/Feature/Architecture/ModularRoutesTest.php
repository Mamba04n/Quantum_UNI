<?php

namespace Tests\Feature\Architecture;

use App\Http\Controllers\DashboardController;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ModularRoutesTest extends TestCase
{
    #[DataProvider('routeContracts')]
    public function test_module_routes_preserve_their_public_contract(string $name, string $uri, string $method): void
    {
        $route = RouteFacade::getRoutes()->getByName($name);

        $this->assertInstanceOf(Route::class, $route);
        $this->assertSame($uri, $route->uri());
        $this->assertContains($method, $route->methods());
    }

    public function test_dashboard_uses_a_controller_and_keeps_employee_security_middleware(): void
    {
        $route = RouteFacade::getRoutes()->getByName('dashboard');

        $this->assertInstanceOf(Route::class, $route);
        $this->assertSame(DashboardController::class, $route->getActionName());
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains('erp.employee', $route->gatherMiddleware());
        $this->assertContains('no-cache', $route->gatherMiddleware());
    }

    /** @return array<string, array{string, string, string}> */
    public static function routeContracts(): array
    {
        return [
            'storefront' => ['storefront.products.index', 'productos', 'GET'],
            'admin' => ['admin.users', 'admin/users', 'GET'],
            'crm' => ['clients.profile', 'clients/{id}/profile', 'GET'],
            'ventas' => ['sales.quotations.phase', 'sales/quotations/fase-cotizacion', 'GET'],
            'compras' => ['purchases.create', 'purchases/create', 'GET'],
            'inventario' => ['inventory.products', 'inventory/products', 'GET'],
            'finanzas' => ['finance.cxp', 'finance/cxp', 'GET'],
            'documentos privados' => ['private.client-machine-image', 'private/client-machines/{machine}/image', 'GET'],
        ];
    }
}
