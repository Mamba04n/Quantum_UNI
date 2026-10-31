<?php

namespace Tests\Feature;

use App\Domain\Finanzas\Services\FinanceService;
use App\Livewire\Finanzas\AccountReceivableDashboard;
use App\Models\AccountReceivable;
use App\Models\ActivityLog;
use App\Models\Entity;
use App\Models\FinanceChangeRequest;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class AccountReceivableWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_form_uses_one_nio_amount_and_identifies_unlinked_origin(): void
    {
        $this->receivable();

        Livewire::test(AccountReceivableDashboard::class)
            ->assertSet('showForm', false)
            ->assertSee('Registrar CXC manual')
            ->call('toggleReceivableForm')
            ->assertSet('showForm', true)
            ->assertSee('Importe total en córdobas (C$)')
            ->assertSee('Este es el único importe contable de la CXC.')
            ->assertSee('Sin orden vinculada')
            ->assertDontSeeHtml('wire:model="amount_usd"');
    }

    public function test_finance_user_can_submit_receivable_change_request(): void
    {
        $user = $this->financeUser();
        $receivable = $this->receivable([
            'total_amount' => '154.45',
            'amount_usd' => '4.17',
            'paid_amount' => '1.00',
            'status' => 'parcial',
        ]);

        Livewire::actingAs($user)
            ->test(AccountReceivableDashboard::class)
            ->call('editReceivable', $receivable->id)
            ->assertSet('editing_origin', 'Sin orden vinculada')
            ->set('total_amount', '200.00')
            ->set('change_justification', 'Ajuste de cotización final autorizada por ventas')
            ->call('confirmSaveReceivable')
            ->assertHasNoErrors()
            ->assertSet('editing_receivable_id', null);

        // La cuenta original se mantiene intacta hasta aprobación admin
        $receivable->refresh();
        $this->assertSame('154.45', $receivable->total_amount);

        // Se crea la solicitud pendiente
        $request = FinanceChangeRequest::query()->where('account_receivable_id', $receivable->id)->first();
        $this->assertNotNull($request);
        $this->assertSame(FinanceChangeRequest::STATUS_PENDING, $request->status);
        $this->assertSame('Ajuste de cotización final autorizada por ventas', $request->justification);
    }

    public function test_receivable_edit_requires_justification(): void
    {
        $user = $this->financeUser();
        $receivable = $this->receivable();

        Livewire::actingAs($user)
            ->test(AccountReceivableDashboard::class)
            ->call('editReceivable', $receivable->id)
            ->set('total_amount', '200.00')
            ->set('change_justification', '')
            ->call('confirmSaveReceivable')
            ->assertHasErrors(['change_justification']);

        $this->assertSame('100.00', $receivable->fresh()->total_amount);
    }

    public function test_service_rejects_reducing_receivable_below_collected_amount(): void
    {
        $service = app(FinanceService::class);
        $receivable = $this->receivable([
            'total_amount' => '100.00',
            'paid_amount' => '50.00',
            'status' => 'parcial',
        ]);

        $this->expectException(ValidationException::class);
        $service->updateReceivable($receivable, [
            'total_amount' => '49.99',
            'due_date' => now()->addDays(10)->toDateString(),
        ], 0, 'Rebaja inválida');
    }

    public function test_service_detects_concurrent_change_collision(): void
    {
        $service = app(FinanceService::class);
        $receivable = $this->receivable();

        $receivable->forceFill(['lock_version' => 1])->save();

        $this->expectException(ValidationException::class);
        $service->updateReceivable($receivable, [
            'total_amount' => '250.00',
            'due_date' => now()->addDays(10)->toDateString(),
        ], 0, 'Modificación concurrente');
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
            'invoice_number' => 'DOC-001',
            'concept' => 'Cuenta manual de prueba',
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
