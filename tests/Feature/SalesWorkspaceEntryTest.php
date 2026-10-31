<?php

namespace Tests\Feature;

use App\Http\Controllers\WorkspaceController;
use App\Models\User;
use App\Support\WorkspaceNavigation;
use App\Support\WorkspaceOverview;
use Illuminate\Http\Request;
use Tests\TestCase;

class SalesWorkspaceEntryTest extends TestCase
{
    private function enterSales(array $permissions)
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')->andReturn(false);
        $user->shouldReceive('canAccess')->andReturn(false);
        $user->shouldReceive('hasAnyPermission')->andReturnUsing(fn ($required) => count(array_intersect($required, $permissions)) > 0);
        $request = Request::create('/inicio/ventas');
        $request->setUserResolver(fn () => $user);

        return (new WorkspaceController)($request, new WorkspaceNavigation, new WorkspaceOverview, 'ventas');
    }

    public function test_sales_dashboard_shows_only_authorized_commercial_modules(): void
    {
        $view = $this->enterSales(['supplier_bid.view']);
        $this->assertSame('workspaces.index', $view->name());
        $this->assertSame('ventas', $view->getData()['area']);
        $this->assertArrayHasKey('sales.quotations.phase', $view->getData()['areas']['ventas']['items']);
    }

    public function test_creation_only_user_is_not_sent_to_an_unauthorized_board(): void
    {
        $view = $this->enterSales(['customer_request.create']);
        $this->assertSame(['sales.customer-request.create'], array_keys($view->getData()['areas']['ventas']['items']));
    }
}
