<?php

namespace Tests\Feature;

use App\Livewire\Finanzas\AccountPayableDashboard;
use App\Livewire\Finanzas\AccountReceivableDashboard;
use App\Models\AccountPayable;
use App\Models\Entity;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class FinancePaymentDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_payable_payment_is_registered_from_the_livewire_form(): void
    {
        Storage::fake('private');
        $user = $this->financeUser();
        $payable = $this->payable('100.00');

        Livewire::actingAs($user)
            ->test(AccountPayableDashboard::class)
            ->call('openPaymentModal', $payable->id)
            ->set('payment_amount', '35.25')
            ->set('payment_method', 'Transferencia')
            ->set('payment_date', '2026-08-13')
            ->set('payment_reference', 'TRANSFERENCIA-001')
            ->set('payment_evidence', UploadedFile::fake()->create('comprobante.pdf', 20, 'application/pdf'))
            ->call('savePayment')
            ->assertHasNoErrors()
            ->assertSet('showPaymentModal', false);

        $this->assertDatabaseHas('payments', [
            'accounts_payable_id' => $payable->id,
            'amount' => '35.25',
            'payment_reference' => 'TRANSFERENCIA-001',
        ]);
        $this->assertSame('35.25', $payable->fresh()->paid_amount);
    }

    public function test_service_validation_errors_are_mapped_to_visible_modal_fields(): void
    {
        Storage::fake('private');
        $user = $this->financeUser();
        $payable = $this->payable('50.00');

        Livewire::actingAs($user)
            ->test(AccountPayableDashboard::class)
            ->call('openPaymentModal', $payable->id)
            ->set('payment_amount', '50.01')
            ->set('payment_reference', 'TRANSFERENCIA-EXCESIVA')
            ->set('payment_evidence', UploadedFile::fake()->create('comprobante.pdf', 20, 'application/pdf'))
            ->call('savePayment')
            ->assertHasErrors(['payment_amount'])
            ->assertSee('El monto excede el saldo pendiente.')
            ->assertSet('showPaymentModal', true);

        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('0.00', $payable->fresh()->paid_amount);
    }

    public function test_both_payment_modals_use_direct_actions_and_expose_progress_feedback(): void
    {
        Livewire::test(AccountPayableDashboard::class)
            ->set('showPaymentModal', true)
            ->assertSeeHtml('wire:click.prevent="savePayment"')
            ->assertSeeHtml('data-testid="apply-payment-button"')
            ->assertSee('Procesando...');

        Livewire::test(AccountReceivableDashboard::class)
            ->set('showPaymentModal', true)
            ->assertSeeHtml('wire:click.prevent="savePayment"')
            ->assertSeeHtml('data-testid="apply-receivable-payment-button"')
            ->assertSee('Procesando...');
    }

    private function financeUser(): User
    {
        $user = User::factory()->create();
        $permission = Permission::query()->firstOrCreate(
            ['slug' => 'finance.payment.register'],
            ['name' => 'Registrar pago', 'module' => 'finance'],
        );
        $user->permissions()->sync([$permission->id]);

        return $user->fresh();
    }

    private function payable(string $total): AccountPayable
    {
        $entity = Entity::query()->create([
            'name' => 'Proveedor prueba',
            'type' => 'proveedor',
        ]);

        return AccountPayable::query()->create([
            'entity_id' => $entity->id,
            'concept' => 'Cuenta de prueba',
            'total_amount' => $total,
            'paid_amount' => '0.00',
            'due_date' => now()->addMonth()->toDateString(),
            'status' => 'pendiente',
        ]);
    }
}
