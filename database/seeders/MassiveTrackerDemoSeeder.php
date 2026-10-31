<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Entity;
use App\Models\OrderStatus;
use App\Models\OrderStatusItem;
use App\Models\OrderTracker;
use App\Models\OrderTrackerReference;
use App\Models\Product;
use App\Models\QuotationRequest;
use App\Models\QuotationRequestItem;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\Supplier;
use App\Services\OrderStatusService;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MassiveTrackerDemoSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('es_ES');
        $this->command->info('Iniciando inserciones masivas para el Tracker...');

        // Asegurar clientes y proveedores base
        $customers = Entity::where('type', 'cliente')->take(20)->get();
        if ($customers->isEmpty()) {
            for ($i = 1; $i <= 10; $i++) {
                Entity::create([
                    'type' => 'cliente',
                    'name' => $faker->company,
                    'legal_name' => $faker->company . ' S.A.',
                    'rfc_ruc' => 'J03' . rand(1000000000, 9999999999),
                    'country' => 'Nicaragua',
                    'city' => 'Managua',
                    'address' => $faker->address,
                    'status' => 'activo',
                ]);
            }
            $customers = Entity::where('type', 'cliente')->get();
        }

        $suppliers = Entity::where('type', 'proveedor')->take(10)->get();
        if ($suppliers->isEmpty()) {
            for ($i = 1; $i <= 5; $i++) {
                Entity::create([
                    'type' => 'proveedor',
                    'name' => $faker->company . ' Suppliers Inc',
                    'country' => 'USA',
                    'city' => 'Miami',
                    'status' => 'activo',
                ]);
            }
            $suppliers = Entity::where('type', 'proveedor')->get();
        }

        $products = Product::take(30)->get();
        if ($products->isEmpty()) {
            for ($i = 1; $i <= 15; $i++) {
                Product::create([
                    'name' => 'Repuesto Industrial ' . $faker->word,
                    'part_number' => 'PN-' . strtoupper(Str::random(6)),
                    'sku' => 'SKU-' . rand(10000, 99999),
                    'brand' => $faker->randomElement(['Caterpillar', 'Komatsu', 'Volvo', 'Cummins']),
                    'sales_price' => rand(150, 4500),
                    'cost' => rand(80, 2500),
                    'active' => true,
                ]);
            }
            $products = Product::all();
        }

        $categories = [
            // 1. Solicitud Cliente
            ['stage' => 'quote_pending', 'type' => 'quote', 'status' => QuotationRequest::STATUS_RECEIVED, 'count' => 15],
            // 2. Licitacion Proveedor
            ['stage' => 'quote_processing', 'type' => 'quote', 'status' => QuotationRequest::STATUS_PENDING_BID, 'count' => 15],
            // 3. Oferta Cliente
            ['stage' => 'quote_sent', 'type' => 'quote', 'status' => QuotationRequest::STATUS_CLIENT_OFFER_SENT, 'count' => 15],
            // 4. Aprobado
            ['stage' => 'quote_approved', 'type' => 'quote', 'status' => QuotationRequest::STATUS_APPROVED_TOTAL, 'count' => 15],
            // 5. Pedido Extranjero
            ['stage' => 'order_purchased', 'type' => 'order', 'status' => OrderStatusService::STATUS_WAITING_SUPPLIER, 'count' => 15],
            // 6. Transito / Aduanas
            ['stage' => 'order_transit', 'type' => 'order', 'status' => OrderStatusService::STATUS_IN_TRANSIT, 'count' => 15],
            // 7. En Bodega Local
            ['stage' => 'order_warehouse', 'type' => 'order', 'status' => OrderStatusService::STATUS_RECEIVED_WAREHOUSE, 'count' => 15],
            // 8. Facturacion / Salida
            ['stage' => 'order_delivery', 'type' => 'order', 'status' => OrderStatusService::STATUS_AVAILABLE_FOR_DISPATCH, 'count' => 15],
            // 9. Entregado
            ['stage' => 'order_delivered', 'type' => 'order', 'status' => OrderStatusService::STATUS_DELIVERED, 'count' => 25],
            // 10. Anulados / Cancelados
            ['stage' => 'order_cancelled', 'type' => 'both', 'status' => 'cancelado', 'count' => 15],
        ];

        $orderIndex = 5000;
        $batchKey = strtoupper(Str::random(3));

        foreach ($categories as $cat) {
            for ($k = 1; $k <= $cat['count']; $k++) {
                $orderIndex++;
                $customer = $customers->random();
                $supplier = $suppliers->random();
                $product = $products->random();
                $refCode = 'ORD-' . $batchKey . '-' . $orderIndex;
                $quoteCode = 'COT-' . $batchKey . '-' . $orderIndex;
                $trackCode = 'TRK-' . date('ym') . '-' . $batchKey . '-' . str_pad($orderIndex, 4, '0', STR_PAD_LEFT);

                if ($cat['type'] === 'quote' || ($cat['type'] === 'both' && $k % 2 === 0)) {
                    // Create Quotation
                    $quote = QuotationRequest::create([
                        'reference' => $quoteCode,
                        'date' => now()->subDays(rand(1, 45))->toDateString(),
                        'quote_to_name' => $customer->name,
                        'quote_to_ruc' => $customer->rfc_ruc,
                        'quote_to_email' => $customer->email ?: 'contacto@example.com',
                        'status' => $cat['status'],
                        'total_amount' => rand(500, 25000),
                        'created_at' => now()->subDays(rand(1, 45)),
                        'updated_at' => now()->subHours(rand(1, 120)),
                    ]);

                    QuotationRequestItem::create([
                        'quotation_request_id' => $quote->id,
                        'product_id' => $product->id,
                        'part_name' => $product->name,
                        'description' => $product->name,
                        'part_number' => $product->part_number,
                        'quantity' => rand(1, 10),
                        'unit_price' => $product->sales_price ?: 250.0,
                        'line_total' => ($product->sales_price ?: 250.0) * rand(1, 10),
                    ]);

                    $tracker = OrderTracker::create([
                        'operational_tracking_code' => $trackCode,
                        'main_reference' => $quote->reference,
                        'operational_phase' => $cat['stage'],
                        'operational_status' => $cat['status'],
                    ]);

                    OrderTrackerReference::create([
                        'tracker_id' => $tracker->id,
                        'reference_type' => 'cotizacion',
                        'source_module' => 'quotation_requests',
                        'source_id' => $quote->id,
                        'reference_code' => $quote->reference,
                    ]);
                } else {
                    // Create Sale Order & Order Status
                    $qty = rand(1, 8);
                    $price = $product->sales_price ?: 350.0;
                    $total = $qty * $price;

                    $order = SaleOrder::create([
                        'reference' => $refCode,
                        'date' => now()->subDays(rand(1, 30))->toDateString(),
                        'client_name' => $customer->name,
                        'client_ruc' => $customer->rfc_ruc,
                        'customer_id' => $customer->id,
                        'status' => $cat['status'] === 'cancelado' ? 'cancelado' : ($cat['stage'] === 'order_delivered' ? 'entregado' : 'processing'),
                        'payment_type' => rand(0, 1) ? 'Contado' : 'Crédito',
                        'total_amount' => $total,
                        'created_at' => now()->subDays(rand(1, 30)),
                        'updated_at' => now()->subHours(rand(1, 72)),
                    ]);

                    $orderItem = SaleOrderItem::create([
                        'sale_order_id' => $order->id,
                        'product_id' => $product->id,
                        'description' => $product->name,
                        'part_number' => $product->part_number,
                        'quantity' => $qty,
                        'unit_price' => $price,
                        'total_price' => $total,
                    ]);

                    $statusRecord = OrderStatus::create([
                        'sale_order_id' => $order->id,
                        'administrative_status' => $cat['status'],
                        'administrative_phase' => $cat['stage'],
                        'progress_percentage' => $cat['stage'] === 'order_delivered' ? 100 : rand(20, 85),
                    ]);

                    $isDelivered = $cat['stage'] === 'order_delivered';
                    $isWarehouseOrLater = in_array($cat['stage'], ['order_warehouse', 'order_delivery', 'order_delivered']);

                    OrderStatusItem::create([
                        'order_status_id' => $statusRecord->id,
                        'sale_order_item_id' => $orderItem->id,
                        'product_id' => $product->id,
                        'supplier_id' => $supplier->id,
                        'part_name' => $product->name,
                        'part_number' => $product->part_number,
                        'brand' => $product->brand,
                        'quantity_ordered' => $qty,
                        'quantity_confirmed' => $qty,
                        'quantity_shipped' => $qty,
                        'quantity_received' => $isWarehouseOrLater ? $qty : 0,
                        'quantity_kardex_entry' => $isWarehouseOrLater ? $qty : 0,
                        'quantity_kardex_output' => $isDelivered ? $qty : 0,
                        'quantity_dispatched' => $isDelivered ? $qty : 0,
                        'quantity_delivered' => $isDelivered ? $qty : 0,
                        'delivered_at' => $isDelivered ? now() : null,
                        'status' => $cat['status'],
                    ]);

                    $tracker = OrderTracker::create([
                        'operational_tracking_code' => $trackCode,
                        'main_reference' => $order->reference,
                        'operational_phase' => $cat['stage'],
                        'operational_status' => $cat['status'],
                    ]);

                    OrderTrackerReference::create([
                        'tracker_id' => $tracker->id,
                        'reference_type' => 'pedido',
                        'source_module' => 'sale_orders',
                        'source_id' => $order->id,
                        'reference_code' => $order->reference,
                    ]);
                }
            }
        }

        $this->command->info('Inserciones masivas completadas exitosamente con más de 160 registros distribuidos en todas las 10 etapas.');
    }
}
