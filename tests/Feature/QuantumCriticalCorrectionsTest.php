<?php

namespace Tests\Feature;

use App\Models\Entity;
use App\Models\Permission;
use App\Models\Product;
use App\Models\QuotationRequest;
use App\Models\QuotationRequestItem;
use App\Models\Role;
use App\Models\User;
use App\Services\CustomerService;
use App\Services\OrderTrackerService;
use App\Services\SalesWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuantumCriticalCorrectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_customer_is_hidden_from_new_customer_requests(): void
    {
        $active = Entity::create(['name' => 'Cliente Activo', 'type' => 'cliente', 'status' => 'active']);
        $inactive = Entity::create(['name' => 'Cliente Inactivo', 'type' => 'cliente', 'status' => 'inactive']);

        $ids = app(CustomerService::class)->activeCustomerQuery()->pluck('id')->all();

        $this->assertContains($active->id, $ids);
        $this->assertNotContains($inactive->id, $ids);
    }

    public function test_authenticated_user_is_redirected_away_from_login(): void
    {
        $role = $this->role('ventas', ['dashboard.sales.view']);
        $user = User::factory()->create(['role' => 'ventas', 'is_active' => true]);
        $user->roles()->sync([$role->id]);

        $this->actingAs($user)
            ->get(route('login'))
            ->assertRedirect('/dashboard');
    }

    public function test_generated_offer_can_move_to_pending_approval(): void
    {
        $role = $this->role('aprobador-test', ['order_approval.view']);
        $user = User::factory()->create(['role' => 'aprobador-test']);
        $user->roles()->sync([$role->id]);

        $product = Product::factory()->create(['stock_on_hand' => 10, 'sales_price' => 100, 'cost' => 50]);
        $quotation = QuotationRequest::create([
            'reference' => 'SC-APPROVAL-001',
            'date' => now()->toDateString(),
            'quote_to_name' => 'Cliente Test',
            'status' => QuotationRequest::STATUS_CLIENT_OFFER_GENERATED,
            'client_offer_pdf_path' => 'commercial/client-offers/test.pdf',
            'subtotal' => 100,
            'total_amount' => 115,
        ]);
        QuotationRequestItem::create([
            'quotation_request_id' => $quotation->id,
            'product_id' => $product->id,
            'part_name' => 'Filtro',
            'part_number' => 'FLT-1',
            'description' => 'Filtro hidraulico',
            'quantity' => 1,
            'unit' => 'unidad',
            'unit_price' => 100,
            'line_total' => 100,
            'status' => 'pending_review',
        ]);

        $updated = app(SalesWorkflowService::class)->transition($quotation, OrderTrackerService::QUOTE_PENDING_APPROVAL, $user);

        $this->assertSame(OrderTrackerService::QUOTE_PENDING_APPROVAL, $updated->status);
        $this->assertSame(SalesWorkflowService::PHASE_APPROVAL, $updated->commercial_phase);
    }

    public function test_purchases_and_support_have_expected_permissions(): void
    {
        $purchasePermissions = config('rbac.roles.compras.permissions');
        $supportPermissions = config('rbac.roles.soporte.permissions');

        $this->assertContains('purchase_order.view', $purchasePermissions);
        $this->assertContains('purchase_order.create', $purchasePermissions);
        $this->assertContains('purchase_order.receive', $purchasePermissions);
        $this->assertContains('blog.manage', $supportPermissions);
        $this->assertContains('catalog.manage', $supportPermissions);
    }

    private function role(string $slug, array $permissions): Role
    {
        $role = Role::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug)]);

        $permissionIds = collect($permissions)->map(function (string $permission) {
            return Permission::firstOrCreate(
                ['slug' => $permission],
                ['name' => $permission, 'module' => str($permission)->before('.')->toString()]
            )->id;
        });

        $role->permissions()->sync($permissionIds);

        return $role;
    }
}
