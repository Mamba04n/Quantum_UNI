<?php

namespace Tests\Feature;

use App\Livewire\Explorador\ExploradorGlobal;
use App\Models\Entity;
use App\Models\QuotationRequest;
use App\Models\Role;
use App\Models\SaleOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GlobalExplorerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_find_a_request_and_open_its_context(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(
            ['slug' => User::ROLE_ADMIN],
            ['name' => 'Administrador'],
        ));

        $quotation = QuotationRequest::factory()->create([
            'reference' => 'SC-EXP-0042',
            'quote_to_name' => 'Cliente Explorador',
        ]);

        Livewire::actingAs($admin)
            ->test(ExploradorGlobal::class)
            ->set('search', 'EXP-0042')
            ->assertSee('SC-EXP-0042')
            ->assertSee('Cliente Explorador')
            ->call('selectResult', 'quotation', $quotation->id)
            ->assertSee('Registros relacionados')
            ->assertSee('No generada');
    }

    public function test_search_requires_two_characters(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(
            ['slug' => User::ROLE_ADMIN],
            ['name' => 'Administrador'],
        ));

        Livewire::actingAs($admin)
            ->test(ExploradorGlobal::class)
            ->set('search', 'O')
            ->assertSee('Escribe al menos dos caracteres');
    }

    public function test_admin_can_open_a_customer_profile_with_all_linked_orders(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(
            ['slug' => User::ROLE_ADMIN],
            ['name' => 'Administrador'],
        ));

        $customer = Entity::create([
            'name' => 'Transportes del Norte, S.A.',
            'legal_name' => 'Transportes del Norte, Sociedad Anónima',
            'type' => 'cliente',
            'email' => 'compras@transportes.test',
            'contact_name' => 'María López',
            'rfc_ruc' => 'J0310000000012',
            'status' => 'activo',
        ]);

        SaleOrder::factory()->create([
            'customer_id' => $customer->id,
            'reference' => 'OV-CLIENTE-0042',
            'client_name' => $customer->name,
        ]);

        Livewire::actingAs($admin)
            ->test(ExploradorGlobal::class)
            ->set('type', 'customer')
            ->set('search', 'Transportes del Norte')
            ->assertSee('Transportes del Norte, S.A.')
            ->call('selectResult', 'customer', $customer->id)
            ->assertSee('Información del cliente')
            ->assertSee('María López')
            ->assertSee('Expedientes del cliente')
            ->assertSee('OV-CLIENTE-0042');
    }
}
