<?php

namespace Tests\Feature;

use App\Livewire\Explorador\ExplorerQuickSearch;
use App\Models\User;
use App\Support\WorkspaceNavigation;
use App\Support\WorkspaceOverview;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class WorkspaceNavigationTest extends TestCase
{
    private function employee(array $permissions = [], bool $warehouse = false): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')->with(User::ROLE_BODEGA)->andReturn($warehouse);
        $user->shouldReceive('canAccess')->with('dashboard')->andReturn(true);
        $user->shouldReceive('hasAnyPermission')->andReturnUsing(fn ($required) => count(array_intersect($required, $permissions)) > 0);

        return $user;
    }

    public function test_all_registered_destinations_exist_and_need_no_required_parameters(): void
    {
        foreach (config('workspaces') as $area) {
            foreach ($area['items'] as $name => $item) {
                $this->assertNotNull(Route::getRoutes()->getByName($name), $name);
                $this->assertNotEmpty(route($name));
            }
        }
    }

    public function test_finance_links_are_hidden_without_finance_permission(): void
    {
        $navigation = new WorkspaceNavigation;
        $this->assertArrayNotHasKey('finanzas', $navigation->areas($this->employee()));
        $this->assertArrayHasKey('finanzas', $navigation->areas($this->employee(['finance.view'])));
    }

    public function test_incoming_orders_requires_both_middleware_permission_groups(): void
    {
        $navigation = new WorkspaceNavigation;
        $this->assertFalse($navigation->allows($this->employee(['incoming_orders.view']), 'warehouse.incoming-orders'));
        $this->assertFalse($navigation->allows($this->employee(['warehouse.products.manage']), 'warehouse.incoming-orders'));
        $this->assertTrue($navigation->allows($this->employee(['warehouse.inventory.view']), 'warehouse.incoming-orders'));
    }

    public function test_warehouse_navigation_restriction_is_preserved(): void
    {
        $navigation = new WorkspaceNavigation;
        $user = $this->employee(['tracker.view', 'purchases.order.create'], true);
        $this->assertFalse($navigation->allows($user, 'sales.tracker'));
        $this->assertFalse($navigation->allows($user, 'purchases.create'));
    }

    public function test_details_keep_the_parent_area(): void
    {
        $navigation = new WorkspaceNavigation;
        $areas = $navigation->areas($this->employee(['crm.clients.manage']));
        $this->assertSame('clientes', $navigation->current($areas, 'clients.profile'));
        $this->assertNull($navigation->current($areas, 'unknown'));
    }

    public function test_receiving_keeps_warehouse_context_despite_its_purchasing_shortcut(): void
    {
        $navigation = new WorkspaceNavigation;
        $areas = $navigation->areas($this->employee(['warehouse.inventory.view']));
        $this->assertSame('bodega', $navigation->current($areas, 'warehouse.incoming-orders'));
    }

    public function test_overview_does_not_query_business_data_without_permissions(): void
    {
        $queries = [];
        DB::listen(function ($query) use (&$queries) { $queries[] = $query->sql; });
        $this->assertSame([], (new WorkspaceOverview)->metrics($this->employee(), null, new WorkspaceNavigation));
        $this->assertSame([], $queries);
    }

    public function test_area_overview_does_not_load_metrics_from_other_modules(): void
    {
        $queries = [];
        DB::listen(function ($query) use (&$queries) { $queries[] = $query->sql; });
        $user = $this->employee(['finance.view', 'supplier_bid.view', 'tickets.view']);

        $this->assertSame([], (new WorkspaceOverview)->metrics($user, 'reportes', new WorkspaceNavigation));
        $this->assertSame([], $queries);
    }

    public function test_request_summary_counts_only_unconverted_requests_in_review(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('quotation_requests', function (Blueprint $table) {
            $table->id();
            $table->string('status');
            $table->unsignedBigInteger('sale_order_id')->nullable();
        });
        DB::table('quotation_requests')->insert([
            ['status' => 'recibida', 'sale_order_id' => null],
            ['status' => 'en_revision', 'sale_order_id' => null],
            ['status' => 'cancelada', 'sale_order_id' => null],
            ['status' => 'recibida', 'sale_order_id' => 4],
        ]);
        $metrics = (new WorkspaceOverview)->metrics($this->employee(['supplier_bid.view']), 'ventas', new WorkspaceNavigation);
        $this->assertCount(1, $metrics);
        $this->assertSame(2, $metrics[0]['value']);
        $this->assertSame('sales.quotations.licitacion', $metrics[0]['route']);
    }

    public function test_overdue_counts_exclude_paid_future_and_due_today_accounts(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        foreach (['account_receivables', 'account_payables'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->string('status');
                $table->date('due_date');
                $table->decimal('total_amount');
                $table->decimal('paid_amount');
            });
            DB::table($tableName)->insert([
                ['status' => 'pendiente', 'due_date' => today()->subDay(), 'total_amount' => 100, 'paid_amount' => 20],
                ['status' => 'pagado', 'due_date' => today()->subDay(), 'total_amount' => 100, 'paid_amount' => 100],
                ['status' => 'pendiente', 'due_date' => today(), 'total_amount' => 100, 'paid_amount' => 0],
                ['status' => 'pendiente', 'due_date' => today()->addDay(), 'total_amount' => 100, 'paid_amount' => 0],
            ]);
        }
        $metrics = (new WorkspaceOverview)->metrics($this->employee(['finance.view']), 'finanzas', new WorkspaceNavigation);
        $this->assertCount(2, $metrics);
        $this->assertSame([1, 1], array_column($metrics, 'value'));
    }

    public function test_current_module_resolves_route_and_aliases(): void
    {
        $navigation = new WorkspaceNavigation;
        $this->assertSame('sales.quotations.phase', $navigation->currentModule('sales.quotations.licitacion'));
        $this->assertSame('sales.quotations.phase', $navigation->currentModule('sales.quotations.oferta'));
        $this->assertSame('sales.quotations.phase', $navigation->currentModule('sales.quotations.phase'));
        $this->assertSame('sales.quotations.solicitud', $navigation->currentModule('sales.quotations.solicitud'));
        $this->assertSame('sales.customer-request.create', $navigation->currentModule('sales.customer-request.create'));
        $this->assertSame('orders.status.index', $navigation->currentModule('orders.status.index'));
    }

    public function test_explorer_quick_search_adapts_to_area(): void
    {
        $user = $this->employee(['customer_request.view', 'client_offer.view']);
        $this->actingAs($user);

        Livewire::test(ExplorerQuickSearch::class, ['area' => 'ventas'])
            ->assertSee('Buscar en Ventas...')
            ->assertSet('area', 'ventas')
            ->assertSet('scope', 'area');

        Livewire::test(ExplorerQuickSearch::class, ['area' => 'compras'])
            ->assertSee('Buscar en Compras...')
            ->assertSet('area', 'compras');

        Livewire::test(ExplorerQuickSearch::class)
            ->assertSee('Buscar en Quantum...')
            ->assertSet('area', null);
    }

    public function test_area_modules_navigation_view_renders_safely(): void
    {
        $user = $this->employee(['customer_request.view', 'client_offer.view']);
        $this->actingAs($user);

        $html = view('livewire.layout.area-modules-navigation')->render();
        $this->assertIsString($html);
    }
}
