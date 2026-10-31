<?php

namespace Tests\Feature;

use App\Livewire\Clientes\Portal;
use App\Models\QuotationRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerPortalQuotationsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_customer_portal_displays_client_quotations(): void
    {
        $role = Role::query()->firstOrCreate(
            ['slug' => User::ROLE_CLIENTE_TIENDA],
            ['name' => 'Cliente tienda']
        );

        $user = User::factory()->create([
            'email' => 'cliente.portal@example.com',
            'name' => 'Comprador Industrial',
        ]);
        $user->syncRoles([$role->id]);

        $quotation = QuotationRequest::create([
            'reference' => 'SC-00099',
            'submission_token' => 'token-test-123',
            'date' => now()->toDateString(),
            'quote_to_name' => 'Empresa Cliente',
            'quote_to_email' => 'cliente.portal@example.com',
            'requested_by' => 'Comprador Industrial',
            'status' => QuotationRequest::STATUS_RECEIVED,
            'source_channel' => 'web',
            'machinery' => 'Excavadora Cat 320D',
            'user_id' => $user->id,
        ]);

        $this->actingAs($user);

        Livewire::test(Portal::class)
            ->call('setView', 'quotations')
            ->assertSee('SC-00099')
            ->assertSee('Licitación con proveedores')
            ->assertSee('Excavadora Cat 320D');
    }
}
