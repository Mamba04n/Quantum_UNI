<?php

namespace Tests\Feature;

use App\Domain\Ventas\Pedidos\Services\DirectInventorySaleService;
use App\Livewire\Ventas\OrderForm;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Entity;
use App\Models\ExchangeRate;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\SaleOrder;
use App\Models\StockMove;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class DirectInventorySaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_sale_atomically_generates_kardex_invoice_receivable_and_payment(): void
    {
        Storage::fake('private');
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = Entity::create(['name' => 'Cliente contado', 'type' => 'cliente', 'status' => 'active']);
        $product = Product::factory()->create(['name' => 'Filtro', 'sku' => 'FLT-1', 'stock_on_hand' => 10, 'cost' => 40, 'total_cost' => 400, 'sales_price' => 100]);

        $order = app(DirectInventorySaleService::class)->create($this->payload($customer->id, $product->id), $user);

        $this->assertSame('direct_inventory_sale', $order->source_type);
        $this->assertSame(8, $product->fresh()->stock_on_hand);
        $this->assertDatabaseHas('stock_moves', ['sale_order_id' => $order->id, 'product_id' => $product->id, 'quantity' => -2]);
        $invoice = Invoice::where('sales_order_id', $order->id)->firstOrFail();
        $receivable = AccountReceivable::where('sales_order_id', $order->id)->firstOrFail();
        $this->assertSame('Pagado', $invoice->status);
        $this->assertSame('pagado', $receivable->status);
        $this->assertEquals(230, $receivable->paid_amount);
        $this->assertDatabaseHas('payments', ['accounts_receivable_id' => $receivable->id, 'amount' => 230]);
        $this->assertSame(0, AccountPayable::count(), 'Una venta de inventario propio no debe crear CXP.');
        Storage::disk('private')->assertExists($invoice->invoice_file_path);
    }

    public function test_credit_sale_creates_pending_receivable_without_payment(): void
    {
        Storage::fake('private');
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = Entity::create(['name' => 'Cliente crédito', 'type' => 'cliente', 'status' => 'active']);
        $product = Product::factory()->create(['stock_on_hand' => 5, 'cost' => 30, 'total_cost' => 150]);
        $payload = $this->payload($customer->id, $product->id);
        $payload['payment_type'] = 'Crédito';
        $payload['due_date'] = now()->addDays(30)->toDateString();

        $order = app(DirectInventorySaleService::class)->create($payload, $user);
        $receivable = $order->accountReceivable()->firstOrFail();

        $this->assertSame('pendiente', $receivable->status);
        $this->assertEquals(0, $receivable->paid_amount);
        $this->assertSame(0, Payment::count());
    }

    public function test_insufficient_stock_rolls_back_every_document(): void
    {
        Storage::fake('private');
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = Entity::create(['name' => 'Cliente', 'type' => 'cliente']);
        $product = Product::factory()->create(['stock_on_hand' => 1]);
        $payload = $this->payload($customer->id, $product->id);
        $payload['items'][0]['quantity'] = 2;

        try {
            app(DirectInventorySaleService::class)->create($payload, $user);
            $this->fail('Debió rechazar la venta sin existencias.');
        } catch (ValidationException) {
            $this->assertSame(1, $product->fresh()->stock_on_hand);
            $this->assertSame(0, SaleOrder::count());
            $this->assertSame(0, Invoice::count());
            $this->assertSame(0, AccountReceivable::count());
            $this->assertSame(0, StockMove::count());
        }
    }

    public function test_idempotency_key_prevents_duplicate_sale(): void
    {
        Storage::fake('private');
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = Entity::create(['name' => 'Cliente', 'type' => 'cliente']);
        $product = Product::factory()->create(['stock_on_hand' => 10, 'cost' => 10, 'total_cost' => 100]);
        $payload = $this->payload($customer->id, $product->id);
        $service = app(DirectInventorySaleService::class);

        $first = $service->create($payload, $user);
        $second = $service->create($payload, $user);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, SaleOrder::count());
        $this->assertSame(8, $product->fresh()->stock_on_hand);
    }

    public function test_usd_sale_uses_current_manually_approved_exchange_rate_for_finance(): void
    {
        Storage::fake('private');
        $user = User::factory()->create();
        $this->actingAs($user);
        ExchangeRate::create([
            'base_currency' => 'USD', 'quote_currency' => 'NIO', 'rate' => 37,
            'rate_date' => now()->toDateString(), 'expires_at' => now()->addDay()->toDateString(),
            'source' => 'manual-approved', 'source_type' => 'manual_approved',
            'approved_by' => $user->id, 'approved_at' => now(), 'is_fallback' => false,
        ]);
        $customer = Entity::create(['name' => 'Cliente USD', 'type' => 'cliente']);
        $product = Product::factory()->create(['stock_on_hand' => 5, 'cost' => 10, 'total_cost' => 50]);
        $payload = $this->payload($customer->id, $product->id);
        $payload['currency'] = 'USD';

        $order = app(DirectInventorySaleService::class)->create($payload, $user);
        $receivable = $order->accountReceivable()->firstOrFail();

        $this->assertEquals(37, $order->exchange_rate);
        $this->assertEquals(230, $receivable->amount_usd);
        $this->assertEquals(8510, $receivable->total_amount);
        $this->assertEquals(37, $receivable->exchange_rate);
    }

    public function test_direct_sale_can_create_and_immediately_select_a_customer(): void
    {
        $permission = Permission::firstOrCreate(
            ['slug' => 'direct_order.create'],
            ['name' => 'Crear venta directa', 'module' => 'direct_order'],
        );
        $role = Role::firstOrCreate(['slug' => 'ventas-directas'], ['name' => 'Ventas directas']);
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user = User::factory()->create(['role' => 'ventas-directas']);
        $user->roles()->sync([$role->id]);

        Livewire::actingAs($user)->test(OrderForm::class)
            ->call('openCustomerForm')
            ->set('newCustomerName', 'Cliente creado en venta')
            ->set('newCustomerRuc', 'J0310000000999')
            ->set('newCustomerEmail', 'ventas@example.com')
            ->set('newCustomerPhone', '8888-9999')
            ->call('saveCustomer')
            ->assertHasNoErrors()
            ->assertSet('showCustomerForm', false);

        $customer = Entity::where('rfc_ruc', 'J0310000000999')->firstOrFail();
        $this->assertSame('cliente', $customer->type);
        $this->assertSame('ventas@example.com', $customer->email);
    }

    private function payload(int $customerId, int $productId): array
    {
        return [
            'customer_id' => $customerId, 'currency' => 'NIO', 'payment_type' => 'Contado',
            'payment_method' => 'Efectivo', 'payment_reference' => 'CAJA-1',
            'due_date' => now()->toDateString(), 'notes' => null, 'apply_tax' => true,
            'idempotency_key' => (string) Str::uuid(),
            'items' => [['product_id' => $productId, 'quantity' => 2, 'unit_price' => 100]],
        ];
    }
}
