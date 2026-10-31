<?php

namespace Tests\Feature;

use App\Livewire\Finanzas\FinanceHub;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Entity;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class FinanceHubTest extends TestCase
{
    use RefreshDatabase;

    protected function financeUser(): User
    {
        $user = User::factory()->create();
        foreach (['finance.view', 'finance.payment.register', 'finance.register_payment'] as $slug) {
            $perm = Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'module' => 'finance']);
            $user->permissions()->syncWithoutDetaching([$perm->id]);
        }
        return $user;
    }

    public function test_finance_hub_renders_and_shows_metrics(): void
    {
        $user = $this->financeUser();

        $this->actingAs($user)
            ->get(route('finance.hub'))
            ->assertOk()
            ->assertSee('Flujo financiero y balance')
            ->assertSee('Balance neto')
            ->assertSee('Por cobrar')
            ->assertSee('Por pagar');
    }

    public function test_cxc_abono_can_be_registered_from_finance_hub(): void
    {
        Storage::fake('private');
        $user = $this->financeUser();
        $entity = Entity::create(['name' => 'Cliente prueba', 'type' => 'cliente']);
        $cxc = AccountReceivable::create([
            'entity_id' => $entity->id,
            'receivable_code' => 'CXC-TEST-001',
            'concept' => 'Venta a crédito',
            'currency' => 'NIO',
            'total_amount' => 500.00,
            'paid_amount' => 0.00,
            'status' => 'pendiente',
            'due_date' => now()->addDays(30),
        ]);

        Livewire::actingAs($user)
            ->test(FinanceHub::class)
            ->set('tab', 'cxc')
            ->call('openCxcModal', $cxc->id)
            ->assertSet('showCxcModal', true)
            ->set('cxc_amount', '200.00')
            ->set('cxc_method', 'Transferencia')
            ->set('cxc_date', '2026-10-05')
            ->set('cxc_reference', 'TRF-CLI-001')
            ->set('cxc_evidence', UploadedFile::fake()->create('voucher.pdf', 20, 'application/pdf'))
            ->call('saveCxcPayment')
            ->assertHasNoErrors()
            ->assertSet('showCxcModal', false);

        $this->assertDatabaseHas('payments', [
            'accounts_receivable_id' => $cxc->id,
            'amount' => 200.00,
            'payment_reference' => 'TRF-CLI-001',
        ]);

        $this->assertEquals(200.00, (float) $cxc->fresh()->paid_amount);
    }

    public function test_cxp_abono_can_be_registered_from_finance_hub(): void
    {
        Storage::fake('private');
        $user = $this->financeUser();
        $entity = Entity::create(['name' => 'Proveedor prueba', 'type' => 'proveedor']);
        $cxp = AccountPayable::create([
            'entity_id' => $entity->id,
            'payable_code' => 'CXP-TEST-001',
            'concept' => 'Compra repuestos',
            'currency' => 'NIO',
            'total_amount' => 800.00,
            'paid_amount' => 0.00,
            'status' => 'pendiente',
            'due_date' => now()->addDays(15),
        ]);

        Livewire::actingAs($user)
            ->test(FinanceHub::class)
            ->set('tab', 'cxp')
            ->call('openCxpModal', $cxp->id)
            ->assertSet('showCxpModal', true)
            ->set('cxp_amount', '350.00')
            ->set('cxp_method', 'Transferencia')
            ->set('cxp_date', '2026-10-05')
            ->set('cxp_reference', 'TRF-PROV-001')
            ->set('cxp_evidence', UploadedFile::fake()->create('comprobante.pdf', 20, 'application/pdf'))
            ->call('saveCxpPayment')
            ->assertHasNoErrors()
            ->assertSet('showCxpModal', false);

        $this->assertDatabaseHas('payments', [
            'accounts_payable_id' => $cxp->id,
            'amount' => 350.00,
            'payment_reference' => 'TRF-PROV-001',
        ]);

        $this->assertEquals(350.00, (float) $cxp->fresh()->paid_amount);
    }

    public function test_direct_income_and_expense_registration(): void
    {
        Storage::fake('private');
        $user = $this->financeUser();

        Livewire::actingAs($user)
            ->test(FinanceHub::class)
            ->set('direct_income_concept', 'Venta mostrador accesorios')
            ->set('direct_income_amount', '150.00')
            ->set('direct_income_currency', 'NIO')
            ->set('direct_income_method', 'Efectivo')
            ->set('direct_income_date', '2026-10-05')
            ->set('direct_income_reference', 'REC-001')
            ->set('direct_income_party', 'Cliente Contado')
            ->call('saveDirectIncome')
            ->assertHasNoErrors()
            ->assertSet('showDirectIncomeModal', false);

        $this->assertDatabaseHas('payments', [
            'payment_type' => 'customer_payment',
            'amount' => 150.00,
            'payment_reference' => 'REC-001',
        ]);

        Livewire::actingAs($user)
            ->test(FinanceHub::class)
            ->set('direct_expense_concept', 'Pago servicio de agua')
            ->set('direct_expense_category', 'Servicios básicos')
            ->set('direct_expense_amount', '85.00')
            ->set('direct_expense_currency', 'NIO')
            ->set('direct_expense_method', 'Transferencia')
            ->set('direct_expense_date', '2026-10-05')
            ->set('direct_expense_reference', 'FAC-AGUA-99')
            ->set('direct_expense_party', 'Enacal')
            ->call('saveDirectExpense')
            ->assertHasNoErrors()
            ->assertSet('showDirectExpenseModal', false);

        $this->assertDatabaseHas('payments', [
            'payment_type' => 'supplier_payment',
            'amount' => 85.00,
            'payment_reference' => 'FAC-AGUA-99',
        ]);
    }
}
