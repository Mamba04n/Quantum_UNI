<?php

namespace Tests\Feature;

use App\Livewire\Administracion\ApprovalManager;
use App\Models\AccountReceivable;
use App\Models\ActivityLog;
use App\Models\ApprovalRequest;
use App\Models\Entity;
use App\Models\OrderTracker;
use App\Models\Permission;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderDocument;
use App\Models\QuotationRequest;
use App\Models\QuotationRequestItem;
use App\Models\Role;
use App\Models\SupplierBidResponse;
use App\Models\SupplierBidResponseItem;
use App\Models\SupplierBidSelection;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\OrderStatusService;
use App\Services\PurchaseOrderDocumentService;
use App\Services\SalesWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminApprovalManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_see_pending_internal_approvals(): void
    {
        $requester = $this->userWithPermissions(['approval.request', 'client_approval.register_total'], 'ventas');
        $admin = $this->userWithPermissions(['internal_approval.view'], 'compras');
        $approval = $this->approvalWithSupplierSelection($requester);
        $this->assertDatabaseCount('account_receivables', 0);

        Livewire::actingAs($admin)
            ->test(ApprovalManager::class)
            ->call('showPurchaseApprovals')
            ->assertSee($approval->approval_code)
            ->assertSee($approval->tracker->tracking_code)
            ->assertSee('Cliente Admin Approval')
            ->assertSee($requester->name);
    }

    public function test_admin_selects_approval_and_sees_customer_requester_items_and_supplier(): void
    {
        $requester = $this->userWithPermissions(['approval.request', 'client_approval.register_total'], 'ventas');
        $admin = $this->userWithPermissions(['internal_approval.view', 'internal_approval.view_costs', 'internal_approval.view_margin'], 'compras');
        $approval = $this->approvalWithSupplierSelection($requester);

        Livewire::actingAs($admin)
            ->test(ApprovalManager::class)
            ->call('selectApproval', $approval->id)
            ->assertSee('Cliente Admin Approval')
            ->assertSee($requester->name)
            ->call('setTab', 'products')
            ->assertSee('Filtro hidraulico')
            ->assertSee('Proveedor Formal');
    }

    public function test_user_without_cost_permission_cannot_see_supplier_costs_or_margin(): void
    {
        $requester = $this->userWithPermissions(['approval.request', 'client_approval.register_total'], 'ventas');
        $viewer = $this->userWithPermissions(['internal_approval.view'], 'ventas');
        $approval = $this->approvalWithSupplierSelection($requester);

        Livewire::actingAs($viewer)
            ->test(ApprovalManager::class)
            ->call('selectApproval', $approval->id)
            ->call('setTab', 'products')
            ->assertSee('Filtro hidraulico')
            ->assertDontSee('40.00')
            ->assertDontSee('60.00');
    }

    public function test_admin_approves_and_generates_single_purchase_order(): void
    {
        $requester = $this->userWithPermissions(['approval.request', 'client_approval.register_total'], 'ventas');
        $admin = $this->userWithPermissions([
            'internal_approval.view',
            'internal_approval.approve',
            'order_approval.approve',
            'purchase_order.generate',
        ], 'compras');
        $approval = $this->approvalWithSupplierSelection($requester);

        Livewire::actingAs($admin)
            ->test(ApprovalManager::class)
            ->call('selectApproval', $approval->id)
            ->assertSet('selectedApprovalId', $approval->id)
            ->call('approveSelected')
            ->assertHasNoErrors();

        $this->assertSame(ApprovalRequest::STATUS_APPROVED, $approval->fresh()->status);
        $this->assertSame(1, PurchaseOrder::where('approval_request_id', $approval->id)->count());
        $saleOrderId = QuotationRequest::findOrFail($approval->source_id)->sale_order_id;
        $receivable = AccountReceivable::where('sales_order_id', $saleOrderId)->firstOrFail();
        $this->assertSame('Cliente Admin Approval', $receivable->entity->name);
        $this->assertSame('4197.50', $receivable->total_amount);
        $this->assertSame('115.00', $receivable->amount_usd);
        $this->assertSame('offers/admin-approval.pdf', $receivable->support_document_path);
    }

    public function test_purchase_order_pdf_is_available_but_employee_marks_provider_send_later(): void
    {
        $requester = $this->userWithPermissions(['approval.request', 'client_approval.register_total'], 'ventas');
        $admin = $this->userWithPermissions([
            'internal_approval.view',
            'internal_approval.approve',
            'order_approval.approve',
            'purchase_order.generate',
        ], 'compras');
        $employee = $this->userWithPermissions([
            'order_status.view',
            'order_status.manage',
            'purchase_order_provider.view',
            'purchase_order_document.view',
            'purchase_order_document.edit',
        ], 'operaciones');
        $approval = $this->approvalWithSupplierSelection($requester);

        Livewire::actingAs($admin)
            ->test(ApprovalManager::class)
            ->call('selectApproval', $approval->id)
            ->call('approveSelected')
            ->assertHasNoErrors();

        $document = PurchaseOrderDocument::where('approval_request_id', $approval->id)->firstOrFail();
        $this->assertNotNull($document->pdf_path);

        $orderStatus = $document->orderStatus()->with('items')->firstOrFail();
        $this->assertSame(OrderStatusService::STATUS_PO_GENERATED, $orderStatus->items->first()->status);
        $this->assertNull($document->sent_to_provider_at);

        app(PurchaseOrderDocumentService::class)->markSentToProvider($document, $employee);

        $this->assertSame('sent_to_provider', $document->fresh()->status);
        $this->assertSame(OrderStatusService::STATUS_WAITING_SUPPLIER, $orderStatus->fresh('items')->items->first()->status);
    }

    public function test_double_click_approve_does_not_duplicate_order_or_tracker(): void
    {
        $requester = $this->userWithPermissions(['approval.request', 'client_approval.register_total'], 'ventas');
        $admin = $this->userWithPermissions([
            'internal_approval.view',
            'internal_approval.approve',
            'order_approval.approve',
            'purchase_order.generate',
        ], 'compras');
        $approval = $this->approvalWithSupplierSelection($requester);
        $trackerId = $approval->tracker_id;

        Livewire::actingAs($admin)
            ->test(ApprovalManager::class)
            ->call('selectApproval', $approval->id)
            ->call('approveSelected')
            ->call('approveSelected')
            ->assertHasNoErrors();

        $this->assertSame(1, PurchaseOrder::where('approval_request_id', $approval->id)->count());
        $this->assertSame(1, OrderTracker::whereKey($trackerId)->count());
        $this->assertSame(1, AccountReceivable::whereNotNull('sales_order_id')->count());
    }

    public function test_reject_requires_reason(): void
    {
        $requester = $this->userWithPermissions(['approval.request', 'client_approval.register_total'], 'ventas');
        $admin = $this->userWithPermissions(['internal_approval.view', 'internal_approval.reject'], 'compras');
        $approval = $this->approvalWithSupplierSelection($requester);

        Livewire::actingAs($admin)
            ->test(ApprovalManager::class)
            ->call('selectApproval', $approval->id)
            ->call('openReject')
            ->set('rejectionReason', '')
            ->call('submitDecision')
            ->assertHasErrors(['rejectionReason' => 'required']);
    }

    public function test_request_changes_requires_reason(): void
    {
        $requester = $this->userWithPermissions(['approval.request', 'client_approval.register_total'], 'ventas');
        $admin = $this->userWithPermissions(['internal_approval.view', 'internal_approval.request_changes'], 'compras');
        $approval = $this->approvalWithSupplierSelection($requester);

        Livewire::actingAs($admin)
            ->test(ApprovalManager::class)
            ->call('selectApproval', $approval->id)
            ->call('openChanges')
            ->set('rejectionReason', '')
            ->call('submitDecision')
            ->assertHasErrors(['rejectionReason' => 'required']);
    }

    public function test_tracker_and_activity_are_updated_on_approval(): void
    {
        $requester = $this->userWithPermissions(['approval.request', 'client_approval.register_total'], 'ventas');
        $admin = $this->userWithPermissions([
            'internal_approval.view',
            'internal_approval.approve',
            'order_approval.approve',
            'purchase_order.generate',
        ], 'compras');
        $approval = $this->approvalWithSupplierSelection($requester);

        Livewire::actingAs($admin)
            ->test(ApprovalManager::class)
            ->call('selectApproval', $approval->id)
            ->call('approveSelected');

        $this->assertDatabaseHas('order_tracker_activities', [
            'tracker_id' => $approval->tracker_id,
            'action' => 'internal_approval.approved',
        ]);

        $this->assertTrue(ActivityLog::where('action', 'internal_approval.approved')
            ->where('user_id', $admin->id)
            ->exists());
    }

    private function approvalWithSupplierSelection(User $requester): ApprovalRequest
    {
        $supplier = Entity::create([
            'name' => 'Proveedor Formal',
            'type' => 'proveedor',
            'contact_info' => 'Compras',
            'rfc_ruc' => 'SUP-001',
            'email' => 'supplier@example.com',
            'phone_mobile' => '8888-0000',
            'country' => 'US',
            'website' => 'https://supplier.example',
            'contact_name' => 'Supplier Contact',
        ]);

        Entity::create([
            'name' => 'Cliente Admin Approval',
            'type' => 'cliente',
            'contact_info' => 'Cliente',
            'rfc_ruc' => 'J031000000001',
            'email' => 'cliente@example.com',
            'phone_mobile' => '7777-0000',
            'address' => 'Managua',
            'contact_name' => 'Contacto Cliente',
        ]);

        $quotation = QuotationRequest::create([
            'reference' => 'SC-ADMIN-'.str()->random(6),
            'date' => now()->toDateString(),
            'quote_to_name' => 'Cliente Admin Approval',
            'quote_to_email' => 'cliente@example.com',
            'requested_by' => 'Comprador Cliente',
            'department' => 'ventas',
            'status' => QuotationRequest::STATUS_CLIENT_RESPONSE_PENDING,
            'commercial_phase' => SalesWorkflowService::PHASE_APPROVAL,
            'client_offer_pdf_path' => 'offers/admin-approval.pdf',
            'subtotal' => 100,
            'total_amount' => 115,
        ]);

        $item = QuotationRequestItem::create([
            'quotation_request_id' => $quotation->id,
            'part_name' => 'Filtro hidraulico',
            'part_number' => 'FH-100',
            'brand' => 'Fleetguard',
            'model' => 'F100',
            'description' => 'Filtro hidraulico',
            'quantity' => 1,
            'unit' => 'unidad',
            'unit_cost' => 40,
            'unit_price' => 100,
            'line_total' => 100,
            'is_approved' => true,
            'status' => 'quoted',
        ]);

        $response = SupplierBidResponse::create([
            'quotation_request_id' => $quotation->id,
            'supplier_id' => $supplier->id,
            'contact_name' => 'Supplier Contact',
            'currency' => 'USD',
            'payment_terms' => '50% anticipo',
            'general_warranty' => '90 dias',
            'general_delivery_time' => '15 dias',
            'status' => 'received',
            'created_by' => $requester->id,
        ]);

        $responseItem = SupplierBidResponseItem::create([
            'supplier_bid_response_id' => $response->id,
            'quotation_request_item_id' => $item->id,
            'quoted_part_number' => 'FH-100',
            'quoted_description' => 'Filtro hidraulico',
            'quoted_model' => 'F100',
            'quoted_brand' => 'Fleetguard',
            'quoted_quantity' => 1,
            'unit_cost' => 40,
            'currency' => 'USD',
            'estimated_arrival_date' => now()->addDays(20)->toDateString(),
            'item_status' => 'quoted',
        ]);

        SupplierBidSelection::create([
            'quotation_request_id' => $quotation->id,
            'quotation_request_item_id' => $item->id,
            'supplier_bid_response_item_id' => $responseItem->id,
            'selected_supplier_id' => $supplier->id,
            'selected_quantity' => 1,
            'selected_unit_cost' => 40,
            'currency' => 'USD',
            'estimated_arrival_date' => now()->addDays(20)->toDateString(),
            'delivery_time' => '15 dias',
            'warranty' => '90 dias',
            'selected_by' => $requester->id,
            'selected_at' => now(),
        ]);

        $service = app(ApprovalService::class);
        $service->registerClientApproval($quotation, $requester, 'total');

        return $service->requestApproval($quotation->fresh('items'), $requester)->fresh(['tracker', 'requester']);
    }

    private function userWithPermissions(array $permissions, string $baseRole): User
    {
        $role = Role::firstOrCreate(
            ['slug' => 'admin-approval-manager-'.$baseRole.'-'.md5(implode('|', $permissions))],
            ['name' => 'Admin approval manager '.$baseRole]
        );

        $permissionIds = collect($permissions)->map(function (string $permission) {
            return Permission::firstOrCreate(
                ['slug' => $permission],
                ['name' => $permission, 'module' => str($permission)->before('.')->toString()]
            )->id;
        });

        $role->permissions()->sync($permissionIds);

        $user = User::factory()->create(['role' => $baseRole]);
        $user->roles()->sync([$role->id]);

        return $user;
    }
}
