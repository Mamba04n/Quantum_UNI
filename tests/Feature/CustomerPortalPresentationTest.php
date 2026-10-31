<?php

namespace Tests\Feature;

use App\Livewire\Clientes\Portal;
use App\Livewire\Clientes\TicketCenter;
use App\Models\User;
use App\Models\SaleOrder;
use App\Models\OrderStatus;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerPortalPresentationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_customer_can_navigate_all_portal_sections_and_search_without_losing_the_view(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $portal = Livewire::actingAs($user)->test(Portal::class)->assertSee('Cotizar repuestos');
        foreach (['quotations' => 'Mis cotizaciones', 'orders' => 'Mis pedidos', 'order_detail' => 'Seguimiento', 'profile' => 'Mi cuenta', 'tickets' => 'Centro de soporte', 'dashboard' => 'Hola,'] as $view => $title) {
            $portal->call('setView', $view)->assertSet('view', $view)->assertSee($title)->assertHasNoErrors();
        }
        $portal->set('trackingCode', 'NOT-MY-ORDER-PORTAL-TEST')->call('searchTrackingCode')
            ->assertSet('view', 'dashboard')->assertSet('trackingError', 'Ese codigo no esta asociado a tu cuenta.');
        $this->actingAs($user)->get(route('customer.portal'))->assertOk()->assertSee('css/customer-portal.css', false);
    }

    public function test_empty_support_can_open_create_validate_and_return_without_creating_a_ticket(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        Livewire::actingAs($user)->test(TicketCenter::class, ['embedded' => true])
            ->assertSee('Nuevo ticket')->call('startCreate')->assertSet('view', 'create')
            ->assertSee('Cuéntanos cómo podemos ayudarte')->call('createTicket')
            ->assertHasErrors(['subject', 'description'])->call('backToList')->assertSet('view', 'list');
    }

    public function test_tracking_uses_the_current_code_column_and_keeps_other_customers_orders_private(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $own = SaleOrder::withoutEvents(fn () => SaleOrder::factory()->create(['client_email' => $user->email, 'status' => 'confirmed']));
        $other = SaleOrder::withoutEvents(fn () => SaleOrder::factory()->create(['client_email' => 'another-customer-portal@example.test', 'status' => 'confirmed']));
        OrderStatus::withoutEvents(fn () => OrderStatus::create(['sale_order_id' => $own->id, 'administrative_tracking_code' => 'ADMIN-PORTAL-OWN-TEST']));
        OrderStatus::withoutEvents(fn () => OrderStatus::create(['sale_order_id' => $other->id, 'administrative_tracking_code' => 'ADMIN-PORTAL-OTHER-TEST']));
        Livewire::actingAs($user)->test(Portal::class)
            ->set('trackingCode', 'ADMIN-PORTAL-OWN-TEST')->call('searchTrackingCode')
            ->assertSet('view', 'order_detail')->assertSet('selectedOrderId', $own->id)
            ->assertSee('ADMIN-PORTAL-OWN-TEST')->assertDontSee($other->reference)
            ->set('trackingCode', 'ADMIN-PORTAL-OTHER-TEST')->call('searchTrackingCode')
            ->assertSet('selectedOrderId', $own->id)->assertSet('trackingError', 'Ese codigo no esta asociado a tu cuenta.');
    }

    public function test_support_conversation_preserves_escaping_and_customer_access(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $ticket = Ticket::create(['code' => 'TKT-PORTAL-REDESIGN-TEST', 'customer_id' => $user->id, 'type' => Ticket::TYPE_SUPPORT, 'subject' => 'Consulta de un repuesto', 'description' => 'Necesito revisar compatibilidad.', 'priority' => Ticket::PRIORITY_MEDIUM, 'status' => Ticket::STATUS_OPEN]);
        TicketMessage::create(['ticket_id' => $ticket->id, 'sender_id' => $user->id, 'sender_type' => 'customer', 'message' => '<script>alert("test")</script> Mi consulta técnica']);
        Livewire::actingAs($user)->test(TicketCenter::class, ['embedded' => true])
            ->call('openTicket', $ticket->id)->assertSee('Mi consulta técnica')
            ->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert("test")</script>', false)
            ->assertSee('Mensaje para soporte');
        $other = User::factory()->create(['email_verified_at' => now()]);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        Livewire::actingAs($other)->test(TicketCenter::class, ['embedded' => true])->call('openTicket', $ticket->id);
    }
}
