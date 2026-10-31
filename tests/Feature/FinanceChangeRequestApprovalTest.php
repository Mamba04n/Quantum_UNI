<?php

namespace Tests\Feature;

use App\Livewire\Administracion\Finanzas\GestorRevisionFinanzas;
use App\Livewire\Finanzas\AccountPayableDashboard;
use App\Livewire\Finanzas\AccountReceivableDashboard;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\ActivityLog;
use App\Models\Entity;
use App\Models\FinanceChangeRequest;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FinanceChangeRequestApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_list_renders_empty_state_when_no_requests(): void
    {
        $admin = $this->adminUser();

        Livewire::actingAs($admin)
            ->test(GestorRevisionFinanzas::class)
            ->assertSee('No hay modificaciones pendientes')
            ->assertSee('Todo esta al dia, buen trabajo.')
            ->assertSee('Revision Admin de Modificaciones');
    }

    public function test_agent_submits_payable_change_request_and_it_is_pending(): void
    {
        $agent = $this->financeUser();
        $payable = $this->payable([
            'total_amount' => '100.00',
            'concept' => 'Concepto original',
        ]);

        Livewire::actingAs($agent)
            ->test(AccountPayableDashboard::class)
            ->call('editPayable', $payable->id)
            ->set('total_amount', '200.00')
            ->set('concept', 'Concepto nuevo solicitado')
            ->set('change_justification', 'Ajuste de precio solicitado por el proveedor')
            ->call('confirmSavePayable')
            ->assertHasNoErrors();

        // Verificar que la cuenta NO ha cambiado todavía
        $payable->refresh();
        $this->assertSame('100.00', $payable->total_amount);
        $this->assertSame('Concepto original', $payable->concept);

        // Verificar que se creó la solicitud pendiente
        $changeRequest = FinanceChangeRequest::query()
            ->where('account_payable_id', $payable->id)
            ->where('status', FinanceChangeRequest::STATUS_PENDING)
            ->first();

        $this->assertNotNull($changeRequest);
        $this->assertSame('cxp', $changeRequest->type);
        $this->assertSame($agent->id, $changeRequest->requested_by);
        $this->assertSame('Ajuste de precio solicitado por el proveedor', $changeRequest->justification);
        $this->assertSame('200.00', (string) $changeRequest->requested_data['total_amount']);
    }

    public function test_admin_can_view_and_approve_payable_change_request(): void
    {
        $agent = $this->financeUser();
        $admin = $this->adminUser();
        $payable = $this->payable([
            'total_amount' => '150.00',
            'concept' => 'Compra inicial',
        ]);

        // Crear la solicitud de cambio
        $changeRequest = FinanceChangeRequest::create([
            'type' => FinanceChangeRequest::TYPE_CXP,
            'account_payable_id' => $payable->id,
            'requested_by' => $agent->id,
            'status' => FinanceChangeRequest::STATUS_PENDING,
            'original_data' => $payable->toArray(),
            'requested_data' => array_merge($payable->toArray(), [
                'total_amount' => '250.00',
                'concept' => 'Compra corregida y autorizada',
            ]),
            'changes_diff' => [
                ['field' => 'Importe Total C$', 'before' => 'C$ 150.00', 'after' => 'C$ 250.00'],
            ],
            'justification' => 'Ajuste contractual aprobado por gerencia',
        ]);

        // El admin abre la revisión y autoriza el cambio
        Livewire::actingAs($admin)
            ->test(GestorRevisionFinanzas::class)
            ->call('selectRequest', $changeRequest->id)
            ->assertSee('Ajuste contractual aprobado por gerencia')
            ->assertSee('C$ 250.00')
            ->set('adminNotes', 'Revisado y conforme con adenda')
            ->call('approve')
            ->assertHasNoErrors();

        // Verificar que la solicitud quedó aprobada
        $changeRequest->refresh();
        $this->assertSame(FinanceChangeRequest::STATUS_APPROVED, $changeRequest->status);
        $this->assertSame($admin->id, $changeRequest->approved_by);
        $this->assertNotNull($changeRequest->reviewed_at);

        // Verificar que la cuenta se actualizó en la base de datos
        $payable->refresh();
        $this->assertSame('250.00', $payable->total_amount);
        $this->assertSame('Compra corregida y autorizada', $payable->concept);
        $this->assertSame(1, (int) $payable->lock_version);

        // Verificar registro formal en auditoría
        $log = ActivityLog::query()
            ->where('module', 'finanzas')
            ->where('action', 'cxp.updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString('Ajuste contractual', $log->description);
    }

    public function test_admin_can_reject_payable_change_request_with_notes(): void
    {
        $agent = $this->financeUser();
        $admin = $this->adminUser();
        $payable = $this->payable([
            'total_amount' => '300.00',
            'concept' => 'Factura original',
        ]);

        $changeRequest = FinanceChangeRequest::create([
            'type' => FinanceChangeRequest::TYPE_CXP,
            'account_payable_id' => $payable->id,
            'requested_by' => $agent->id,
            'status' => FinanceChangeRequest::STATUS_PENDING,
            'original_data' => $payable->toArray(),
            'requested_data' => array_merge($payable->toArray(), [
                'total_amount' => '500.00',
            ]),
            'changes_diff' => [
                ['field' => 'Importe Total C$', 'before' => 'C$ 300.00', 'after' => 'C$ 500.00'],
            ],
            'justification' => 'Incremento de costo imprevisto',
        ]);

        Livewire::actingAs($admin)
            ->test(GestorRevisionFinanzas::class)
            ->call('selectRequest', $changeRequest->id)
            ->set('adminNotes', 'Rechazado: no se adjuntó adenda firmada')
            ->call('reject')
            ->assertHasNoErrors();

        // Verificar que la solicitud quedó rechazada
        $changeRequest->refresh();
        $this->assertSame(FinanceChangeRequest::STATUS_REJECTED, $changeRequest->status);
        $this->assertSame($admin->id, $changeRequest->approved_by);
        $this->assertSame('Rechazado: no se adjuntó adenda firmada', $changeRequest->admin_notes);

        // Verificar que la cuenta permanece con los valores originales
        $payable->refresh();
        $this->assertSame('300.00', $payable->total_amount);
        $this->assertSame('Factura original', $payable->concept);
    }

    public function test_receivable_change_request_full_workflow(): void
    {
        $agent = $this->financeUser();
        $admin = $this->adminUser();
        $receivable = $this->receivable([
            'total_amount' => '500.00',
            'concept' => 'Venta a crédito',
        ]);

        // 1. Agente solicita modificación en CXC
        Livewire::actingAs($agent)
            ->test(AccountReceivableDashboard::class)
            ->call('editReceivable', $receivable->id)
            ->set('total_amount', '600.00')
            ->set('change_justification', 'Ajuste de flete acordado con el cliente')
            ->call('confirmSaveReceivable')
            ->assertHasNoErrors();

        $changeRequest = FinanceChangeRequest::query()
            ->where('account_receivable_id', $receivable->id)
            ->where('status', FinanceChangeRequest::STATUS_PENDING)
            ->first();

        $this->assertNotNull($changeRequest);
        $this->assertSame('cxc', $changeRequest->type);

        // 2. Admin aprueba la modificación
        Livewire::actingAs($admin)
            ->test(GestorRevisionFinanzas::class)
            ->call('selectRequest', $changeRequest->id)
            ->call('approve')
            ->assertHasNoErrors();

        $receivable->refresh();
        $this->assertSame('600.00', $receivable->total_amount);
        $this->assertSame(1, (int) $receivable->lock_version);
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

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $p1 = Permission::query()->firstOrCreate(
            ['slug' => 'client_offer_review.view'],
            ['name' => 'Revisar ofertas', 'module' => 'commercial'],
        );
        $p2 = Permission::query()->firstOrCreate(
            ['slug' => 'finance.view'],
            ['name' => 'Ver finanzas', 'module' => 'finance'],
        );
        $user->permissions()->sync([$p1->id, $p2->id]);

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
            'invoice_number' => 'FAC-8899',
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

    /** @param array<string, mixed> $overrides */
    private function receivable(array $overrides = []): AccountReceivable
    {
        $entity = Entity::query()->create([
            'name' => 'Cliente de prueba',
            'type' => 'cliente',
        ]);

        return AccountReceivable::query()->create(array_merge([
            'receivable_code' => 'CXC-TEST-'.str()->random(6),
            'entity_id' => $entity->id,
            'customer_id' => $entity->id,
            'invoice_number' => 'DOC-8899',
            'concept' => 'Cuenta por cobrar de prueba',
            'classification' => 'Venta de producto',
            'condition' => '30 Días',
            'invoice_date' => now()->toDateString(),
            'total_amount' => '100.00',
            'paid_amount' => '0.00',
            'currency' => 'NIO',
            'due_date' => now()->addDays(30)->toDateString(),
            'support_document_path' => 'finance/cxc/support.pdf',
            'status' => 'pendiente',
        ], $overrides));
    }
}
