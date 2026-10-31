<?php

namespace Tests\Feature;

use App\Livewire\Finanzas\AccountPayableDashboard;
use App\Livewire\Finanzas\AccountReceivableDashboard;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Entity;
use App\Models\PurchaseOrder;
use App\Models\SaleOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class FinanceAccountOrderColumnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_cxp_shows_purchase_order_supplier_and_single_explicit_account_holder(): void
    {
        $supplier = $this->entity('Proveedor Comercial', 'Proveedor Legal, S.A.', 'proveedor');
        $order = $this->purchaseOrder($supplier, 'OC-26-0101');
        $payable = $this->payable($supplier, [
            'purchase_order_id' => $order->id,
            'supplier_id' => $supplier->id,
            'invoice_number' => 'FACT-7788',
        ]);
        $this->bankAccount($supplier, 'Titular Bancario Autorizado');

        $payable->load($this->payableRelations());

        $this->assertSame('OC-26-0101', $payable->purchaseOrderNumber());
        $this->assertSame('Proveedor Comercial', $payable->supplierName());
        $this->assertSame('Titular Bancario Autorizado', $payable->paymentRecipientName());

        Livewire::test(AccountPayableDashboard::class)
            ->assertSee('# de orden')
            ->assertSee('Proveedor')
            ->assertSee('Pagar a')
            ->assertSee('OC-26-0101')
            ->assertSee('Proveedor Comercial')
            ->assertSee('Titular Bancario Autorizado');
    }

    public function test_cxp_supports_historical_reference_and_uses_supplier_fallback_for_multiple_bank_accounts(): void
    {
        $supplier = $this->entity('Proveedor Histórico', 'Proveedor Histórico Legal', 'proveedor');
        $this->purchaseOrder($supplier, 'OC-26-0005');
        $payable = $this->payable($supplier, [
            'purchase_order_id' => null,
            'supplier_id' => null,
            'invoice_number' => 'OC-26-0005',
        ]);
        $this->bankAccount($supplier, 'Beneficiario Uno', '0001');
        $this->bankAccount($supplier, 'Beneficiario Dos', '0002');

        $payable->load($this->payableRelations());

        $this->assertSame('OC-26-0005', $payable->purchaseOrderNumber());
        $this->assertSame('Proveedor Histórico', $payable->supplierName());
        $this->assertSame('Proveedor Histórico Legal', $payable->paymentRecipientName());
    }

    public function test_cxp_without_purchase_order_is_null_safe_and_financial_values_do_not_change_when_rendered(): void
    {
        $supplier = $this->entity('Acreedor sin orden', null, 'socio');
        $payable = $this->payable($supplier, [
            'invoice_number' => 'DOCUMENTO-SIN-ORDEN',
            'total_amount' => '1250.50',
            'paid_amount' => '250.25',
        ]);

        $payable->load($this->payableRelations());
        $this->assertNull($payable->purchaseOrderNumber());

        Livewire::test(AccountPayableDashboard::class)
            ->assertSee('Acreedor sin orden')
            ->assertSee('C$ 1,000.25');

        $this->assertSame('1250.50', $payable->fresh()->total_amount);
        $this->assertSame('250.25', $payable->fresh()->paid_amount);
    }

    public function test_cxp_searches_by_purchase_order_and_preserves_status_filtering(): void
    {
        $supplier = $this->entity('Proveedor de filtros', null, 'proveedor');
        $pendingOrder = $this->purchaseOrder($supplier, 'OC-FILTRO-001');
        $paidOrder = $this->purchaseOrder($supplier, 'OC-FILTRO-002');
        $this->payable($supplier, ['purchase_order_id' => $pendingOrder->id, 'invoice_number' => 'FAC-A', 'concept' => 'Cuenta visible']);
        $this->payable($supplier, ['purchase_order_id' => $paidOrder->id, 'invoice_number' => 'FAC-B', 'concept' => 'Cuenta pagada', 'status' => 'pagado']);

        Livewire::test(AccountPayableDashboard::class)
            ->set('filterStatus', 'pendiente')
            ->set('search', 'OC-FILTRO-001')
            ->assertSee('Cuenta visible')
            ->assertDontSee('Cuenta pagada');
    }

    public function test_cxc_shows_sale_order_keeps_client_and_invoice_and_handles_missing_order(): void
    {
        $customer = $this->entity('Cliente Correcto', null, 'cliente');
        $order = $this->saleOrder($customer, 'OV-26-0201');
        $linked = $this->receivable($customer, [
            'sales_order_id' => $order->id,
            'invoice_number' => 'FACT-CXC-01',
        ]);
        $unlinked = $this->receivable($customer, [
            'sales_order_id' => null,
            'invoice_number' => 'DOCUMENTO-SIN-ORDEN',
            'concept' => 'CXC sin orden',
        ]);

        $linked->load(['saleOrder', 'saleOrderByReference']);
        $unlinked->load(['saleOrder', 'saleOrderByReference']);

        $this->assertSame('OV-26-0201', $linked->saleOrderNumber());
        $this->assertSame('Cliente Correcto', $linked->entity->name);
        $this->assertNull($unlinked->saleOrderNumber());

        Livewire::test(AccountReceivableDashboard::class)
            ->assertSee('# de orden')
            ->assertSee('OV-26-0201')
            ->assertSee('FACT-CXC-01')
            ->assertSee('Cliente Correcto')
            ->assertSee('CXC sin orden');
    }

    public function test_cxc_historical_reference_is_resolved_and_searchable(): void
    {
        $customer = $this->entity('Cliente Histórico', null, 'cliente');
        $this->saleOrder($customer, 'OV-HIST-0007');
        $this->receivable($customer, [
            'sales_order_id' => null,
            'invoice_number' => 'OV-HIST-0007',
            'concept' => 'Cobro histórico visible',
        ]);
        $this->receivable($customer, [
            'sales_order_id' => null,
            'invoice_number' => 'OTRA-REFERENCIA',
            'concept' => 'Cobro fuera del filtro',
        ]);

        Livewire::test(AccountReceivableDashboard::class)
            ->set('search', 'OV-HIST-0007')
            ->assertSee('Cobro histórico visible')
            ->assertDontSee('Cobro fuera del filtro');
    }

    public function test_finance_lists_do_not_lazy_load_order_supplier_or_bank_relations(): void
    {
        $supplier = $this->entity('Proveedor sin N más uno', null, 'proveedor');
        foreach (range(1, 4) as $index) {
            $order = $this->purchaseOrder($supplier, sprintf('OC-N1-%04d', $index));
            $this->payable($supplier, ['purchase_order_id' => $order->id, 'invoice_number' => "FAC-N1-{$index}"]);
        }

        $previous = Model::preventsLazyLoading();
        Model::preventLazyLoading();

        try {
            Livewire::test(AccountPayableDashboard::class)->assertSee('OC-N1-0004');
            $this->addToAssertionCount(1);
        } catch (LazyLoadingViolationException $exception) {
            $this->fail($exception->getMessage());
        } finally {
            Model::preventLazyLoading($previous);
        }
    }

    /** @return array<int, string> */
    private function payableRelations(): array
    {
        return [
            'entity.bankAccounts',
            'supplier.bankAccounts',
            'purchaseOrder.supplier.bankAccounts',
            'purchaseOrderByReference.supplier.bankAccounts',
        ];
    }

    private function entity(string $name, ?string $legalName, string $type): Entity
    {
        return Entity::query()->create(['name' => $name, 'legal_name' => $legalName, 'type' => $type]);
    }

    private function purchaseOrder(Entity $supplier, string $reference): PurchaseOrder
    {
        return PurchaseOrder::query()->create([
            'supplier_id' => $supplier->id,
            'reference_number' => $reference,
            'status' => 'ordered',
            'total_fob' => '100.00',
            'currency' => 'USD',
            'order_date' => now()->toDateString(),
        ]);
    }

    private function saleOrder(Entity $customer, string $reference): SaleOrder
    {
        return SaleOrder::withoutEvents(fn () => SaleOrder::query()->create([
            'customer_id' => $customer->id,
            'reference' => $reference,
            'date' => now()->toDateString(),
            'client_name' => $customer->name,
            'payment_type' => 'Contado',
            'status' => 'confirmed',
            'subtotal' => '100.00',
            'tax_percent' => '0.00',
            'tax_amount' => '0.00',
            'total_amount' => '100.00',
            'currency' => 'USD',
        ]));
    }

    /** @param array<string, mixed> $overrides */
    private function payable(Entity $entity, array $overrides = []): AccountPayable
    {
        return AccountPayable::query()->create(array_merge([
            'entity_id' => $entity->id,
            'invoice_number' => 'FAC-CXP-DEFAULT',
            'concept' => 'Cuenta por pagar de prueba',
            'total_amount' => '100.00',
            'paid_amount' => '0.00',
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => 'pendiente',
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function receivable(Entity $entity, array $overrides = []): AccountReceivable
    {
        return AccountReceivable::query()->create(array_merge([
            'entity_id' => $entity->id,
            'customer_id' => $entity->id,
            'invoice_number' => 'FAC-CXC-DEFAULT',
            'concept' => 'Cuenta por cobrar de prueba',
            'total_amount' => '100.00',
            'paid_amount' => '0.00',
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => 'pendiente',
        ], $overrides));
    }

    private function bankAccount(Entity $entity, string $holder, string $number = '0000'): void
    {
        DB::table('entity_bank_accounts')->insert([
            'entity_id' => $entity->id,
            'bank_name' => 'Banco de prueba',
            'account_holder' => $holder,
            'account_number' => $number,
            'currency' => 'USD',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
