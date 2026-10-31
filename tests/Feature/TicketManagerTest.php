<?php

namespace Tests\Feature;

use App\Livewire\Administracion\Tickets\GestorTickets;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TicketManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_filter_and_select_ticket(): void
    {
        $admin = $this->userWithPermissions(['support.ticket.reply']);
        $customer = User::factory()->create(['role' => User::ROLE_CLIENTE_TIENDA, 'name' => 'Cliente Ticket']);

        $ticket = Ticket::create([
            'code' => 'TKT-TEST-0001',
            'customer_id' => $customer->id,
            'type' => Ticket::TYPE_SUPPORT,
            'subject' => 'Falla hidraulica principal',
            'description' => 'La maquina presenta fuga en sistema hidraulico.',
            'priority' => Ticket::PRIORITY_HIGH,
            'status' => Ticket::STATUS_OPEN,
            'related_sku' => 'HYD-100',
            'last_message_at' => now(),
            'last_customer_message_at' => now(),
        ]);

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'sender_id' => $customer->id,
            'sender_type' => 'customer',
            'message' => 'Necesito soporte con la fuga.',
        ]);

        Livewire::actingAs($admin)
            ->test(GestorTickets::class)
            ->assertSee('Falla hidraulica principal')
            ->set('search', 'HYD-100')
            ->assertSee('Falla hidraulica principal')
            ->call('selectTicket', $ticket->id)
            ->assertSet('selectedTicketId', $ticket->id)
            ->assertSet('status', Ticket::STATUS_OPEN)
            ->assertSee('Necesito soporte con la fuga.');
    }

    public function test_admin_can_filter_unassigned_tickets(): void
    {
        $admin = $this->userWithPermissions(['support.ticket.reply']);
        $customer = User::factory()->create(['role' => User::ROLE_CLIENTE_TIENDA]);
        $agent = User::factory()->create(['role' => User::ROLE_SUPPORT]);

        Ticket::create([
            'code' => 'TKT-TEST-0002',
            'customer_id' => $customer->id,
            'type' => Ticket::TYPE_QUESTION,
            'subject' => 'Ticket sin responsable',
            'description' => 'Consulta pendiente.',
            'priority' => Ticket::PRIORITY_MEDIUM,
            'status' => Ticket::STATUS_OPEN,
            'last_message_at' => now(),
        ]);

        Ticket::create([
            'code' => 'TKT-TEST-0003',
            'customer_id' => $customer->id,
            'assigned_to' => $agent->id,
            'type' => Ticket::TYPE_QUESTION,
            'subject' => 'Ticket asignado',
            'description' => 'Consulta asignada.',
            'priority' => Ticket::PRIORITY_MEDIUM,
            'status' => Ticket::STATUS_OPEN,
            'last_message_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test(GestorTickets::class)
            ->set('filterAssignee', 'unassigned')
            ->assertSee('Ticket sin responsable')
            ->assertDontSee('Ticket asignado');
    }

    private function userWithPermissions(array $permissions): User
    {
        $role = Role::firstOrCreate(
            ['slug' => 'ticket-manager-test'],
            ['name' => 'Ticket manager test']
        );

        $permissionIds = collect($permissions)->map(function (string $permission) {
            return Permission::firstOrCreate(
                ['slug' => $permission],
                ['name' => $permission, 'module' => str($permission)->before('.')->toString()]
            )->id;
        });

        $role->permissions()->sync($permissionIds);

        $user = User::factory()->create(['role' => User::ROLE_SUPPORT]);
        $user->roles()->sync([$role->id]);

        return $user;
    }
}
