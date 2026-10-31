<?php

namespace Tests\Feature;

use App\Domain\Finanzas\Services\FinanceService;
use App\Livewire\Finanzas\AccountPayableDashboard;
use App\Models\AccountPayable;
use App\Models\ActivityLog;
use App\Models\Entity;
use App\Models\FinanceChangeRequest;
use App\Models\Permission;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class AccountPayableWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_payable_form_renders_fields_and_identifies_unlinked_origin(): void
    {
        $this->payable();

        Livewire::test(AccountPayableDashboard::class)
            ->assertSet('showForm', false)
            ->assertSee('Registrar CXP manual')
            ->call('togglePayableForm')
            ->assertSet('showForm', true)
            ->assertSee('Importe C$')
            ->assertSee('Sin orden vinculada')
            ->assertSee('Registrar CXP');
    }

    public function test_finance_user_can_submit_payable_change_request_and_audit_trail(): void
    {
        $user = $this->financeUser();
        $payable = $this->payable([
            'total_amount' => '150.00',
            'amount_usd' => '4.11',
            'paid_amount' => '1.00',
            'status' => 'parcial',
        ]);

        Livewire::actingAs($user)
            ->test(AccountPayableDashboard::class)
            ->call('editPayable', $payable->id)
            ->assertSet('editing_origin', 'Sin orden de compra vinculada')
            ->set('total_amount', '250.00')
            ->set('concept', 'Concepto corregido y autorizado')
            ->set('change_justification', 'Ajuste de precio acordado con el proveedor según adenda')
            ->call('confirmSavePayable')
            ->assertHasNoErrors()
            ->assertSet('editing_payable_id', null);

        // La cuenta original se mantiene intacta hasta que el admin la aprueba
        $payable->refresh();
        $this->assertSame('150.00', $payable->total_amount);

        // Se crea la solicitud pendiente para el admin
        $request = FinanceChangeRequest::query()->where('account_payable_id', $payable->id)->first();
        $this->assertNotNull($request);
        $this->assertSame(FinanceChangeRequest::STATUS_PENDING, $request->status);
        $this->assertSame('Ajuste de precio acordado con el proveedor según adenda', $request->justification);

        // Verificar registro de auditoría de creación de solicitud
        $log = ActivityLog::query()
            ->where('module', 'finanzas')
            ->where('action', 'finance_change_request.created')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($user->id, $log->user_id);
    }

    public function test_payable_edit_requires_justification_for_sensitive_changes(): void
    {
        $user = $this->financeUser();
        $payable = $this->payable();

        Livewire::actingAs($user)
            ->test(AccountPayableDashboard::class)
            ->call('editPayable', $payable->id)
            ->set('total_amount', '300.00')
            ->set('change_justification', '')
            ->call('confirmSavePayable')
            ->assertHasErrors(['change_justification']);

        $this->assertSame('100.00', $payable->fresh()->total_amount);
    }

    public function test_service_rejects_reducing_payable_below_paid_amount(): void
    {
        $service = app(FinanceService::class);
        $payable = $this->payable([
            'total_amount' => '100.00',
            'paid_amount' => '60.00',
            'status' => 'parcial',
        ]);

        $this->expectException(ValidationException::class);
        $service->updatePayable($payable, [
            'total_amount' => '59.99',
            'due_date' => now()->addDays(10)->toDateString(),
        ], 0, 'Intento inválido');
    }

    public function test_service_detects_concurrent_lock_collision(): void
    {
        $service = app(FinanceService::class);
        $payable = $this->payable();

        $payable->forceFill(['lock_version' => 2])->save();

        $this->expectException(ValidationException::class);
        $service->updatePayable($payable, [
            'total_amount' => '200.00',
            'due_date' => now()->addDays(10)->toDateString(),
        ], 0, 'Intento desactualizado');
    }

    public function test_order_linked_payable_preserves_purchase_order_origin(): void
    {
        $user = $this->financeUser();
        $supplier = Entity::create(['name' => 'Proveedor Original', 'type' => 'proveedor']);
        $otherSupplier = Entity::create(['name' => 'Proveedor Otro', 'type' => 'proveedor']);

        $purchaseOrder = PurchaseOrder::create([
            'reference_number' => 'OC-2026-999',
            'supplier_id' => $supplier->id,
            'status' => 'approved',
            'total_fob' => '500.00',
        ]);

        $payable = $this->payable([
            'purchase_order_id' => $purchaseOrder->id,
            'entity_id' => $supplier->id,
            'supplier_id' => $supplier->id,
            'total_amount' => '500.00',
        ]);

        Livewire::actingAs($user)
            ->test(AccountPayableDashboard::class)
            ->call('editPayable', $payable->id)
            ->assertSet('editing_has_order', true)
            ->assertSet('editing_origin', 'OC-2026-999 · Proveedor Original');
    }

    private function financeUser(): User
    {
        $user = User::factory()->create();
        $permission = Permission::query()->firstOrCreate(
            ['slug' => 'finance.view'],
            ['name' => 'Ver finanzas', 'module' => 'finance'],
        );
        $user->permissions()->sync([$permission->id]);

        return $user->fresh();
    }

    /** @param array<string, mixed> $overrides */
    private function payable(array $overrides = []): AccountPayable
    {
        $entity = Entity::query()->create([
            'name' => 'Proveedor de prueba',
            'type' => 'proveedor',
        ]);

        return AccountPayable::query()->create(array_merge([
            'payable_code' => 'CXP-TEST-'.str()->random(6),
            'entity_id' => $entity->id,
            'supplier_id' => $entity->id,
            'invoice_number' => 'FAC-9901',
            'concept' => 'Cuenta por pagar de prueba',
            'classification' => 'Compra de piezas',
            'condition' => '30 Dias',
            'invoice_date' => now()->toDateString(),
            'total_amount' => '100.00',
            'paid_amount' => '0.00',
            'currency' => 'NIO',
            'due_date' => now()->addDays(30)->toDateString(),
            'support_document_path' => 'finance/cxp/support.pdf',
            'status' => 'pendiente',
        ], $overrides));
    }
}
