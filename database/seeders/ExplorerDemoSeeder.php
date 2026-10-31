<?php

namespace Database\Seeders;

use App\Domain\Pedidos\Tracking\Services\OrderTrackerService;
use App\Models\Entity;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\QuotationRequest;
use App\Models\QuotationRequestItem;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\StockMove;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExplorerDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! App::environment('local')) {
            throw new RuntimeException('ExplorerDemoSeeder solo puede ejecutarse en el entorno local.');
        }

        $user = User::query()->where('is_active', true)->firstOrFail();
        $trackerService = app(OrderTrackerService::class);

        DB::transaction(function () use ($user, $trackerService): void {
            $client = Entity::updateOrCreate(
                ['email' => 'compras.explorador@demo.local'],
                [
                    'name' => 'Cliente Demostración Explorador',
                    'legal_name' => 'Cliente Demostración Explorador, S.A.',
                    'type' => 'cliente',
                    'contact_name' => 'Ana Martínez',
                    'contact_info' => 'Ana Martínez - Responsable de compras',
                    'phone_mobile' => '+505 8888 0101',
                    'rfc_ruc' => 'J0310000000101',
                    'country' => 'Nicaragua',
                    'city' => 'Managua',
                    'address' => 'Managua, Nicaragua',
                    'status' => 'activo',
                ],
            );

            $supplier = Entity::updateOrCreate(
                ['email' => 'ventas.explorador@demo.local'],
                [
                    'name' => 'Proveedor Industrial Demo',
                    'legal_name' => 'Proveedor Industrial Demo, S.A.',
                    'type' => 'proveedor',
                    'contact_name' => 'Carlos Ruiz',
                    'contact_info' => 'Carlos Ruiz - Ejecutivo comercial',
                    'phone_mobile' => '+505 8888 0202',
                    'rfc_ruc' => 'J0310000000202',
                    'country' => 'Nicaragua',
                    'city' => 'Managua',
                    'address' => 'Managua, Nicaragua',
                    'status' => 'activo',
                ],
            );

            $products = collect(range(1, 8))->map(function (int $number): Product {
                $part = 'EXP-PN-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT);

                return Product::updateOrCreate(
                    ['part_number' => $part],
                    [
                        'sku' => 'EXP-SKU-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
                        'name' => 'Repuesto demostración '.$number,
                        'description' => 'Producto de prueba para validar la búsqueda transversal del ERP.',
                        'brand' => ['Caterpillar', 'Komatsu', 'JCB', 'Donaldson'][$number % 4],
                        'type' => 'pieza',
                        'piece_rotation_type' => $number % 2 === 0 ? 'alta_rotacion' : 'baja_rotacion',
                        'technical_category' => 'mecanico',
                        'cost' => 35 + ($number * 12),
                        'sales_price' => 55 + ($number * 18),
                        'stock_on_hand' => 20 + $number,
                        'total_cost' => (20 + $number) * (35 + ($number * 12)),
                        'active' => true,
                    ],
                );
            });

            foreach (range(1, 15) as $sequence) {
                $suffix = str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
                $product = $products[($sequence - 1) % $products->count()];
                $quantity = ($sequence % 5) + 1;
                $unitPrice = (float) $product->sales_price;
                $subtotal = $quantity * $unitPrice;
                $status = [
                    QuotationRequest::STATUS_RECEIVED,
                    QuotationRequest::STATUS_SUPPLIER_RESPONSE_RECEIVED,
                    QuotationRequest::STATUS_CLIENT_OFFER_GENERATED,
                    QuotationRequest::STATUS_APPROVED_TOTAL,
                    QuotationRequest::STATUS_CLOSED,
                ][($sequence - 1) % 5];

                $quotation = QuotationRequest::updateOrCreate(
                    ['reference' => 'EXP-SC-2026-'.$suffix],
                    [
                        'date' => now()->subDays(16 - $sequence)->toDateString(),
                        'quote_to_name' => $client->legal_name,
                        'quote_to_email' => $client->email,
                        'quote_to_ruc' => $client->rfc_ruc,
                        'customer_contact_name' => $client->contact_name,
                        'requested_by' => $client->contact_name,
                        'department' => 'Mantenimiento',
                        'source_channel' => 'demo_explorer',
                        'request_quantity' => $quantity,
                        'subtotal' => $subtotal,
                        'tax_percent' => 15,
                        'total_amount' => round($subtotal * 1.15, 2),
                        'currency' => $sequence % 3 === 0 ? 'NIO' : 'USD',
                        'status' => $status,
                        'commercial_phase' => $this->commercialPhase($status),
                        'notes' => 'Registro local para demostrar relaciones en el Explorador.',
                        'user_id' => $user->id,
                    ],
                );

                $quotationItem = QuotationRequestItem::updateOrCreate(
                    ['quotation_request_id' => $quotation->id, 'part_number' => $product->part_number],
                    [
                        'product_id' => $product->id,
                        'part_name' => $product->name,
                        'description' => $product->description,
                        'brand' => $product->brand,
                        'quantity' => $quantity,
                        'unit' => 'unidad',
                        'unit_price' => $unitPrice,
                        'line_total' => $subtotal,
                        'status' => 'approved',
                        'is_approved' => true,
                    ],
                );

                $order = SaleOrder::withoutEvents(fn () => SaleOrder::updateOrCreate(
                    ['reference' => 'EXP-OV-2026-'.$suffix],
                    [
                        'date' => now()->subDays(15 - $sequence)->toDateString(),
                        'customer_id' => $client->id,
                        'client_name' => $client->legal_name,
                        'client_email' => $client->email,
                        'client_ruc' => $client->rfc_ruc,
                        'shipping_address' => $client->address,
                        'provider_name' => $supplier->legal_name,
                        'provider_ruc' => $supplier->rfc_ruc,
                        'payment_type' => $sequence % 2 === 0 ? 'Crédito' : 'Contado',
                        'status' => ['generated', 'confirmed', 'processing', 'completed'][$sequence % 4],
                        'subtotal' => $subtotal,
                        'tax_percent' => 15,
                        'tax_amount' => round($subtotal * .15, 2),
                        'total_amount' => round($subtotal * 1.15, 2),
                        'currency' => $sequence % 3 === 0 ? 'NIO' : 'USD',
                        'generated_by' => $user->id,
                        'generated_at' => now(),
                        'notes' => 'Orden relacionada con '.$quotation->reference.'.',
                    ],
                ));

                $saleItem = SaleOrderItem::updateOrCreate(
                    ['sale_order_id' => $order->id, 'quotation_request_item_id' => $quotationItem->id],
                    [
                        'product_id' => $product->id,
                        'part_number' => $product->part_number,
                        'description' => $product->name,
                        'brand' => $product->brand,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'total_price' => $subtotal,
                        'item_status' => 'En proceso',
                        'inventory_status' => 'available',
                        'purchase_status' => 'ordered',
                    ],
                );

                $quotation->update(['sale_order_id' => $order->id]);

                $purchaseOrder = PurchaseOrder::updateOrCreate(
                    ['reference_number' => 'EXP-OC-2026-'.$suffix],
                    [
                        'supplier_id' => $supplier->id,
                        'quotation_request_id' => $quotation->id,
                        'sale_order_id' => $order->id,
                        'status' => ['ordered', 'shipped', 'customs', 'received'][$sequence % 4],
                        'source_type' => 'demo_explorer',
                        'total_fob' => round($subtotal * .72, 2),
                        'currency' => 'USD',
                        'order_date' => now()->subDays(14 - $sequence)->toDateString(),
                        'expected_date' => now()->addDays($sequence)->toDateString(),
                        'generated_by' => $user->id,
                        'generated_at' => now(),
                        'notes' => 'Compra de demostración vinculada a '.$order->reference.'.',
                    ],
                );

                $purchaseItem = PurchaseOrderItem::updateOrCreate(
                    ['purchase_order_id' => $purchaseOrder->id, 'product_id' => $product->id],
                    [
                        'quotation_request_item_id' => $quotationItem->id,
                        'sale_order_item_id' => $saleItem->id,
                        'part_name' => $product->name,
                        'part_number' => $product->part_number,
                        'brand' => $product->brand,
                        'quantity' => $quantity,
                        'received_quantity' => $sequence % 4 === 0 ? $quantity : 0,
                        'unit_price' => round($unitPrice * .72, 2),
                        'total_price' => round($subtotal * .72, 2),
                        'currency' => 'USD',
                        'expected_date' => $purchaseOrder->expected_date,
                        'status' => $purchaseOrder->status,
                    ],
                );

                $move = StockMove::updateOrCreate(
                    ['product_id' => $product->id, 'reference' => 'EXP-KDX-2026-'.$suffix],
                    [
                        'purchase_order_id' => $purchaseOrder->id,
                        'purchase_order_item_id' => $purchaseItem->id,
                        'sale_order_id' => $order->id,
                        'sale_order_item_id' => $saleItem->id,
                        'movement_type' => $sequence % 3 === 0 ? 'out' : 'in',
                        'movement_date' => now()->subDays(15 - $sequence)->toDateString(),
                        'quantity' => $sequence % 3 === 0 ? -$quantity : $quantity,
                        'cost_in' => $sequence % 3 === 0 ? 0 : round($unitPrice * .72, 2),
                        'cost_out' => $sequence % 3 === 0 ? round($unitPrice * .72, 2) : 0,
                        'unit_cost' => round($unitPrice * .72, 2),
                        'balance_after' => 20 + $sequence,
                        'total_cost_after' => round((20 + $sequence) * $unitPrice * .72, 2),
                        'state' => 'done',
                        'source_module' => 'explorer_demo',
                        'source_id' => $order->id,
                        'user_id' => $user->id,
                    ],
                );

                $tracker = $trackerService->trackerForQuotation($quotation);
                $trackerService->addReference($tracker, 'foreign_order', $order->reference, 'sale_orders', $order->id);
                $trackerService->addReference($tracker, 'purchase_order', $purchaseOrder->reference_number, 'purchase_orders', $purchaseOrder->id);
                $trackerService->addReference($tracker, 'kardex', $move->reference, 'stock_moves', $move->id);

                Invoice::updateOrCreate(
                    ['invoice_number' => 'EXP-FAC-2026-'.$suffix],
                    [
                        'entity_id' => $client->id,
                        'date' => now()->subDays(15 - $sequence)->toDateString(),
                        'cotizacion_ref' => $quotation->reference,
                        'description' => 'Factura demostrativa correspondiente a '.$order->reference.'.',
                        'amount_nio' => round($subtotal * 1.15 * 36.6243, 2),
                        'retention_amount' => 0,
                        'amount_usd' => round($subtotal * 1.15, 2),
                        'due_date' => now()->addDays(30 + $sequence)->toDateString(),
                        'status' => $sequence % 4 === 0 ? 'Pagado' : 'Pendiente',
                        'classification' => 'Venta de producto',
                        'condition' => $sequence % 2 === 0 ? 'Crédito 30 días' : 'Contado',
                        'currency' => 'USD',
                        'subtotal' => $subtotal,
                        'tax_amount' => round($subtotal * .15, 2),
                        'discount_amount' => 0,
                        'total' => round($subtotal * 1.15, 2),
                    ],
                );
            }
        });

        $this->command?->info('Muestra del Explorador creada: 15 cadenas operativas y 8 productos.');
    }

    private function commercialPhase(string $status): string
    {
        return match ($status) {
            QuotationRequest::STATUS_RECEIVED => 'customer_request',
            QuotationRequest::STATUS_SUPPLIER_RESPONSE_RECEIVED => 'supplier_bid',
            QuotationRequest::STATUS_CLIENT_OFFER_GENERATED => 'client_offer',
            default => 'approval',
        };
    }
}
