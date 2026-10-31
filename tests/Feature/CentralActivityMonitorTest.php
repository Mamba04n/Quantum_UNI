<?php

namespace Tests\Feature;

use App\Domain\Administracion\Auditoria\Queries\UnifiedActivityQuery;
use App\Models\Product;
use App\Models\StockMove;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CentralActivityMonitorTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_audit_page_redirects_to_the_protected_central_monitor(): void
    {
        $this->get('/audit-logs')->assertRedirect(route('login'));
        $admin = User::factory()->create();
        $role = \App\Models\Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrador']);
        $admin->roles()->sync([$role->id]);
        $this->actingAs($admin)
            ->get('/audit-logs')->assertRedirect(route('admin.activity'));
        $this->get(route('admin.activity'))->assertOk()->assertSee('Todos los orígenes')
            ->assertDontSee('Sin movimientos.')->assertDontSee('Sin tickets recientes.');
    }

    public function test_historical_inventory_and_tickets_share_timeline_and_filters(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Bomba centralizada']);
        StockMove::withoutEvents(fn () => StockMove::create([
            'product_id' => $product->id, 'user_id' => $user->id, 'quantity' => 2,
            'movement_type' => 'in', 'movement_date' => today(), 'reference' => 'CENTRAL-IN-01',
        ]));
        Ticket::withoutEvents(fn () => Ticket::create([
            'code' => 'CENTRAL-TICKET-01', 'customer_id' => $user->id, 'type' => 'consulta',
            'subject' => 'Consulta centralizada', 'description' => 'Solo en base de pruebas',
        ]));
        $feed = app(UnifiedActivityQuery::class);
        $inventory = $feed->paginate((string) $user->id, null, null, 'Bomba centralizada', 50, null, null, 'inventario', 'in');
        $this->assertSame(1, $inventory->total());
        $this->assertSame('CENTRAL-IN-01', $inventory->sole()->metadata['reference']);
        $this->assertSame(0, $feed->paginate(null, null, null, null, 50, null, null, 'inventario', 'out')->total());
        $tickets = $feed->paginate(null, 'soporte', null, 'CENTRAL-TICKET-01', 50, null, null, 'soporte');
        $this->assertSame(1, $tickets->total());
        $this->assertNull($tickets->sole()->user_id);
        $this->assertSame(0, $feed->paginate(null, null, null, null, 50, today()->addDay()->toDateString(), null, 'soporte')->total());
    }
}
