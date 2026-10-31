<?php

namespace Tests\Unit;

use App\Models\Entity;
use App\Models\OrderStatus;
use App\Models\OrderStatusItem;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Services\PreprintedInvoiceService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class PreprintedInvoiceServiceTest extends TestCase
{
    public function test_it_generates_the_preprinted_kardex_output_pdf_without_database_writes(): void
    {
        Carbon::setTestNow('2026-08-21 12:00:00');

        $customer = new Entity(['name' => 'Cliente de prueba', 'rfc_ruc' => 'J0000000000000']);
        $order = new SaleOrder(['client_name' => 'Cliente de prueba', 'payment_type' => 'Contado']);
        $order->setRelation('customer', $customer);

        $saleItem = new SaleOrderItem(['description' => 'Filtro de aceite', 'unit_price' => 450]);
        $product = new Product(['name' => 'Filtro de aceite', 'sku' => 'LF17483']);
        $item = new OrderStatusItem([
            'id' => 13,
            'part_name' => 'Filtro de aceite',
            'part_number' => 'LF17483',
            'quantity_kardex_entry' => 2,
            'quantity_kardex_output' => 0,
        ]);
        $item->setRelation('product', $product);
        $item->setRelation('saleOrderItem', $saleItem);

        $status = new OrderStatus(['id' => 6]);
        $status->setRelation('saleOrder', $order);
        $status->setRelation('items', new Collection([$item]));

        try {
            $pdf = app(PreprintedInvoiceService::class)->generate($status, [13 => 1], '0002', 'Jovan');
        } finally {
            Carbon::setTestNow();
        }

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(1000, strlen($pdf));
    }
}
