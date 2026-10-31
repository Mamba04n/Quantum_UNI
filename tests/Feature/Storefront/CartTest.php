<?php

namespace Tests\Feature\Storefront;

use App\Livewire\Storefront\Cart;
use App\Models\Product;
use App\Services\Storefront\CartService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class CartTest extends TestCase
{
    use DatabaseTransactions;

    public function test_cart_adds_updates_and_removes_products(): void
    {
        $product = Product::factory()->create();
        app(CartService::class)->add($product, 2);

        Livewire::test(Cart::class)
            ->set("quantities.{$product->id}", 4)
            ->call('updateQuantity', $product->id);
        $this->assertSame(4, session('storefront.cart')[$product->id]);

        Livewire::test(Cart::class)->call('remove', $product->id);
        $this->assertArrayNotHasKey($product->id, session('storefront.cart', []));
    }

    public function test_cart_submits_quote_and_creates_erp_quotation_in_licitacion_and_user_account(): void
    {
        $product = Product::factory()->create([
            'name' => 'Filtro de Aceite 1R-1808',
            'sku' => '1R-1808',
            'part_number' => '1R-1808',
            'sales_price' => 45.00,
        ]);
        app(CartService::class)->add($product, 3);

        $test = Livewire::test(Cart::class)
            ->set('name', 'Constructora Alfa')
            ->set('contact_name', 'Carlos Mendoza')
            ->set('email', 'alfa@example.com')
            ->set('phone', '+505 8888-9999')
            ->set('company', 'Alfa S.A.')
            ->set('ruc', 'J0310000000001')
            ->set('machinery', 'Excavadora 320D')
            ->set('machine_brand', 'Caterpillar')
            ->set('machine_model', '320D')
            ->set('equipment_serial', 'CAT0320D12345')
            ->set('urgency', 'alta')
            ->set('notes', 'Entrega urgente en plantel')
            ->call('requestQuote');

        $test->assertHasNoErrors();

        // 1. Verificar creación en quotation_requests (ERP) con estado 'recibida' (Licitación Proveedor)
        $this->assertDatabaseHas('quotation_requests', [
            'quote_to_name' => 'Alfa S.A.',
            'customer_contact_name' => 'Carlos Mendoza',
            'quote_to_email' => 'alfa@example.com',
            'quote_to_ruc' => 'J0310000000001',
            'machinery' => 'Excavadora 320D',
            'machine_brand' => 'Caterpillar',
            'machine_model' => '320D',
            'equipment_serial' => 'CAT0320D12345',
            'request_urgency' => 'alta',
            'status' => \App\Models\QuotationRequest::STATUS_RECEIVED,
            'source_channel' => 'web',
        ]);

        // 2. Verificar partidas
        $this->assertDatabaseHas('quotation_request_items', [
            'product_id' => $product->id,
            'quantity' => 3,
            'part_number' => '1R-1808',
        ]);

        // 3. Verificar cuenta de usuario creada
        $this->assertDatabaseHas('users', [
            'email' => 'alfa@example.com',
        ]);

        // 4. Verificar trazabilidad en storefront_orders
        $this->assertDatabaseHas('storefront_orders', [
            'email_cliente' => 'alfa@example.com',
            'estado' => 'cotizacion_erp',
        ]);

        // 5. Verificar que el componente tiene el código de cotización asignado
        $submitted = $test->get('submittedQuote');
        $this->assertNotNull($submitted);
        $this->assertStringStartsWith('SC-', $submitted['code']);
        $this->assertTrue($submitted['account_created']);
    }
}
