<?php

namespace Database\Seeders;

use App\Domain\Pedidos\OrderStatus\Actions\EnsureOrderStatusForSaleOrder;
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
use App\Models\SupplierRfqDocument;
use App\Models\User;
use App\Services\OrderTrackerService;
use App\Services\PackingListService;
use App\Services\PdfService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExplorerMassiveWorkflowSeeder extends Seeder
{
    private const WORKFLOWS = 40;

    private const PRODUCTS = 60;

    public function run(): void
    {
        if (! App::environment('local')) {
            throw new RuntimeException('Este seeder masivo solo puede ejecutarse en APP_ENV=local.');
        }

        $this->repairInvoiceLinks();

        $existingWorkflows = QuotationRequest::query()
            ->where('reference', 'like', 'MASS-SC-2026-%')
            ->count();

        if ($existingWorkflows >= self::WORKFLOWS) {
            $this->command?->info('La muestra masiva ya contiene los 40 expedientes. No se realizaron duplicados.');

            return;
        }

        if ($existingWorkflows > 0) {
            throw new RuntimeException("Existe una carga parcial de {$existingWorkflows} expedientes MASS. Revísala antes de volver a ejecutar el seeder.");
        }

        $user = User::query()->where('is_active', true)->firstOrFail();
        Auth::login($user);

        [$clients, $suppliers, $products] = DB::transaction(fn () => [
            $this->clients(),
            $this->suppliers(),
            $this->products(),
        ]);

        $generated = ['rfq' => 0, 'offer' => 0, 'packing' => 0];

        foreach (range(1, self::WORKFLOWS) as $number) {
            $this->command?->info("Expediente {$number}/".self::WORKFLOWS.'...');

            $models = DB::transaction(fn () => $this->createWorkflow($number, $user, $clients, $suppliers, $products));
            $this->generateDocuments($models, $user, $generated);
        }

        $this->command?->info(sprintf(
            'Carga completa: %d solicitudes, %d ordenes de venta, %d ordenes de compra, %d trackers, %d facturas y %d PDFs.',
            self::WORKFLOWS,
            self::WORKFLOWS,
            self::WORKFLOWS * 2,
            self::WORKFLOWS,
            self::WORKFLOWS,
            array_sum($generated),
        ));
    }

    private function createWorkflow(int $number, User $user, $clients, $suppliers, $products): array
    {
        $suffix = str_pad((string) $number, 3, '0', STR_PAD_LEFT);
        $client = $clients[($number - 1) % $clients->count()];
        $itemCount = 40 + (($number - 1) % 11);
        $currency = $number % 4 === 0 ? 'NIO' : 'USD';

        $quotation = QuotationRequest::updateOrCreate(
            ['reference' => "MASS-SC-2026-{$suffix}"],
            [
                'date' => now()->subDays(45 - $number)->toDateString(),
                'quote_to_name' => $client->legal_name,
                'quote_to_email' => $client->email,
                'quote_to_ruc' => $client->rfc_ruc,
                'customer_contact_name' => $client->contact_name,
                'requested_by' => $client->contact_name,
                'department' => 'Mantenimiento y operaciones',
                'source_channel' => 'massive_local_demo',
                'request_quantity' => $itemCount,
                'tax_percent' => 15,
                'currency' => $currency,
                'status' => QuotationRequest::STATUS_APPROVED_TO_SEND,
                'commercial_phase' => 'client_offer',
                'notes' => "Expediente masivo local {$suffix} con {$itemCount} partidas.",
                'user_id' => $user->id,
            ],
        );

        $quotation->items()->delete();
        $subtotal = 0.0;
        $quotationItems = collect();

        foreach (range(1, $itemCount) as $line) {
            $product = $products[($number + $line - 2) % $products->count()];
            $quantity = ($line % 8) + 1;
            $unitPrice = round((float) $product->sales_price * (1 + (($number % 5) * .015)), 2);
            $lineTotal = round($quantity * $unitPrice, 2);
            $subtotal += $lineTotal;

            $quotationItems->push(QuotationRequestItem::create([
                'quotation_request_id' => $quotation->id,
                'product_id' => $product->id,
                'part_name' => $product->name,
                'part_number' => $product->part_number,
                'description' => $product->description,
                'brand' => $product->brand,
                'model' => 'MASS-'.$suffix,
                'quantity' => $quantity,
                'unit' => 'unidad',
                'unit_cost' => $product->cost,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'status' => 'approved',
                'is_approved' => true,
                'warranty_type' => '6 meses contra defectos de fabricación',
            ]));
        }

        $quotation->update(['subtotal' => $subtotal, 'total_amount' => round($subtotal * 1.15, 2)]);

        $order = SaleOrder::withoutEvents(fn () => SaleOrder::updateOrCreate(
            ['reference' => "MASS-OV-2026-{$suffix}"],
            [
                'date' => now()->subDays(44 - $number)->toDateString(),
                'customer_id' => $client->id,
                'client_name' => $client->legal_name,
                'client_email' => null,
                'client_ruc' => $client->rfc_ruc,
                'shipping_address' => $client->address,
                'provider_name' => $suppliers[0]->legal_name,
                'provider_ruc' => $suppliers[0]->rfc_ruc,
                'payment_type' => $number % 2 ? 'Contado' : 'Crédito',
                'status' => ['generated', 'confirmed', 'processing', 'completed'][$number % 4],
                'subtotal' => $subtotal,
                'tax_percent' => 15,
                'tax_amount' => round($subtotal * .15, 2),
                'total_amount' => round($subtotal * 1.15, 2),
                'currency' => $currency,
                'approved_by' => $user->id,
                'approved_at' => now(),
                'generated_by' => $user->id,
                'generated_at' => now(),
                'notes' => "Orden masiva vinculada con {$quotation->reference}.",
            ],
        ));

        $order->items()->delete();
        $saleItems = collect();
        foreach ($quotationItems as $item) {
            $saleItems->push(SaleOrderItem::create([
                'sale_order_id' => $order->id,
                'quotation_request_item_id' => $item->id,
                'product_id' => $item->product_id,
                'part_number' => $item->part_number,
                'description' => $item->part_name,
                'brand' => $item->brand,
                'model' => $item->model,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'total_price' => $item->line_total,
                'item_status' => 'En proceso',
                'inventory_status' => 'received',
                'purchase_status' => 'ordered',
            ]));
        }
        $quotation->update(['sale_order_id' => $order->id]);

        $tracker = app(OrderTrackerService::class)->trackerForQuotation($quotation->fresh());
        app(OrderTrackerService::class)->addReference($tracker, 'foreign_order', $order->reference, 'sale_orders', $order->id);

        $purchaseOrders = collect();
        foreach ([0, 1] as $supplierIndex) {
            $supplier = $suppliers[$supplierIndex];
            $po = PurchaseOrder::updateOrCreate(
                ['reference_number' => "MASS-OC-2026-{$suffix}-".($supplierIndex + 1)],
                [
                    'supplier_id' => $supplier->id,
                    'quotation_request_id' => $quotation->id,
                    'sale_order_id' => $order->id,
                    'status' => ['ordered', 'shipped', 'customs', 'received'][$number % 4],
                    'source_type' => 'massive_local_demo',
                    'total_fob' => 0,
                    'currency' => 'USD',
                    'order_date' => now()->subDays(40 - $number)->toDateString(),
                    'expected_date' => now()->addDays(10 + $number)->toDateString(),
                    'generated_by' => $user->id,
                    'generated_at' => now(),
                ],
            );
            $po->items()->delete();
            $poTotal = 0.0;

            foreach ($quotationItems->values() as $index => $item) {
                if ($index % 2 !== $supplierIndex) {
                    continue;
                }
                $saleItem = $saleItems[$index];
                $cost = round((float) $item->unit_cost, 2);
                $total = round((float) $item->quantity * $cost, 2);
                $poTotal += $total;
                $poItem = PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'product_id' => $item->product_id,
                    'quotation_request_item_id' => $item->id,
                    'sale_order_item_id' => $saleItem->id,
                    'part_name' => $item->part_name,
                    'part_number' => $item->part_number,
                    'brand' => $item->brand,
                    'quantity' => $item->quantity,
                    'received_quantity' => $item->quantity,
                    'unit_price' => $cost,
                    'total_price' => $total,
                    'currency' => 'USD',
                    'expected_date' => $po->expected_date,
                    'status' => $po->status,
                ]);

                StockMove::updateOrCreate(
                    ['product_id' => $item->product_id, 'reference' => "MASS-KDX-{$suffix}-".str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT)],
                    [
                        'purchase_order_id' => $po->id,
                        'purchase_order_item_id' => $poItem->id,
                        'sale_order_id' => $order->id,
                        'sale_order_item_id' => $saleItem->id,
                        'movement_type' => 'in',
                        'movement_date' => now()->subDays(5)->toDateString(),
                        'quantity' => $item->quantity,
                        'cost_in' => $cost,
                        'cost_out' => 0,
                        'unit_cost' => $cost,
                        'balance_after' => 50 + $index,
                        'total_cost_after' => round((50 + $index) * $cost, 2),
                        'state' => 'done',
                        'source_module' => 'massive_local_demo',
                        'source_id' => $order->id,
                        'user_id' => $user->id,
                    ],
                );
            }
            $po->update(['total_fob' => $poTotal]);
            $purchaseOrders->push($po);
            app(OrderTrackerService::class)->addReference($tracker, 'purchase_order', $po->reference_number, 'purchase_orders', $po->id);
        }

        $rfq = SupplierRfqDocument::updateOrCreate(
            ['quotation_request_id' => $quotation->id, 'supplier_id' => $suppliers[0]->id],
            [
                'rfq_number' => "MASS-RFQ-2026-{$suffix}",
                'quote_to_name' => SupplierRfqDocument::DEFAULT_QUOTE_TO_NAME,
                'rfq_response_requirements' => SupplierRfqDocument::DEFAULT_RESPONSE_REQUIREMENTS,
                'selected_item_ids' => $quotationItems->pluck('id')->all(),
                'status' => SupplierRfqDocument::STATUS_ADMIN_APPROVED,
                'admin_reviewed_by' => $user->id,
                'admin_reviewed_at' => now(),
            ],
        );

        $orderStatus = app(EnsureOrderStatusForSaleOrder::class)->handle($order->fresh('items'), $user);
        $orderStatus->items()->update([
            'quantity_confirmed' => DB::raw('quantity_ordered'),
            'quantity_shipped' => DB::raw('quantity_ordered'),
            'quantity_received' => DB::raw('quantity_ordered'),
            'quantity_kardex_entry' => DB::raw('quantity_ordered'),
            'quantity_kardex_output' => DB::raw('quantity_ordered'),
            'quantity_dispatched' => DB::raw('quantity_ordered'),
            'quantity_pending' => 0,
            'status' => 'dispatched',
        ]);

        Invoice::updateOrCreate(
            ['invoice_number' => "MASS-FAC-2026-{$suffix}"],
            [
                'entity_id' => $client->id,
                'customer_id' => $client->id,
                'sales_order_id' => $order->id,
                'order_status_id' => $orderStatus->id,
                'date' => now()->toDateString(),
                'cotizacion_ref' => $quotation->reference,
                'description' => "Factura de {$order->reference} con {$itemCount} partidas.",
                'amount_nio' => $currency === 'NIO'
                    ? round($subtotal * 1.15, 2)
                    : round($subtotal * 1.15 * 36.6243, 2),
                'retention_amount' => 0,
                'amount_usd' => $currency === 'USD'
                    ? round($subtotal * 1.15, 2)
                    : round($subtotal * 1.15 / 36.6243, 2),
                'due_date' => now()->addDays(30)->toDateString(),
                'status' => $number % 3 === 0 ? 'Pagado' : 'Pendiente',
                'classification' => 'Venta de producto',
                'condition' => $number % 2 ? 'Contado' : 'Crédito 30 días',
                'currency' => $currency,
                'subtotal' => $subtotal,
                'tax_amount' => round($subtotal * .15, 2),
                'discount_amount' => 0,
                'total' => round($subtotal * 1.15, 2),
            ],
        );

        return compact('quotation', 'rfq', 'orderStatus');
    }

    private function repairInvoiceLinks(): void
    {
        Invoice::query()
            ->where('invoice_number', 'like', 'MASS-FAC-2026-%')
            ->orderBy('id')
            ->each(function (Invoice $invoice): void {
                if (blank($invoice->cotizacion_ref)) {
                    return;
                }

                $quotation = QuotationRequest::with('saleOrder.orderStatus')
                    ->where('reference', $invoice->cotizacion_ref)
                    ->first();
                $saleOrder = $quotation?->saleOrder;

                if (! $saleOrder) {
                    return;
                }

                $invoice->forceFill([
                    'customer_id' => $saleOrder->customer_id ?: $invoice->entity_id,
                    'entity_id' => $saleOrder->customer_id ?: $invoice->entity_id,
                    'sales_order_id' => $saleOrder->id,
                    'order_status_id' => $saleOrder->orderStatus?->id,
                    'amount_nio' => strtoupper((string) $invoice->currency) === 'NIO'
                        ? round((float) $invoice->total, 2)
                        : round((float) $invoice->total * 36.6243, 2),
                    'amount_usd' => strtoupper((string) $invoice->currency) === 'USD'
                        ? round((float) $invoice->total, 2)
                        : round((float) $invoice->total / 36.6243, 2),
                ])->save();
            });
    }

    private function generateDocuments(array $models, User $user, array &$generated): void
    {
        $pdf = app(PdfService::class);
        $pdf->generateSupplierRfqPdf($models['rfq']->fresh(), $user->id);
        $generated['rfq']++;

        $quotation = $models['quotation']->fresh('items');
        $quotation->update(['status' => QuotationRequest::STATUS_APPROVED_TO_SEND, 'commercial_phase' => 'client_offer']);
        $pdf->generateClientOfferPdf($quotation->fresh('items'), $user->id);
        $generated['offer']++;

        if (app(PackingListService::class)->generateAndNotify($models['orderStatus']->fresh(['items', 'saleOrder']), $user->id)) {
            $generated['packing']++;
        }
    }

    private function clients()
    {
        return collect(range(1, 10))->map(fn (int $number) => Entity::updateOrCreate(
            ['email' => "mass-client-{$number}@demo.local"],
            ['name' => "Cliente Masivo {$number}", 'legal_name' => "Cliente Masivo {$number}, S.A.", 'type' => 'cliente', 'contact_name' => "Contacto Cliente {$number}", 'rfc_ruc' => 'MASS-CLI-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT), 'country' => 'Nicaragua', 'city' => 'Managua', 'address' => "Dirección operativa cliente {$number}, Managua", 'status' => 'activo'],
        ));
    }

    private function suppliers()
    {
        return collect(range(1, 5))->map(fn (int $number) => Entity::updateOrCreate(
            ['email' => "mass-supplier-{$number}@demo.local"],
            ['name' => "Proveedor Masivo {$number}", 'legal_name' => "Proveedor Masivo {$number}, S.A.", 'type' => 'proveedor', 'contact_name' => "Ejecutivo Proveedor {$number}", 'rfc_ruc' => 'MASS-PRV-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT), 'country' => 'Nicaragua', 'city' => 'Managua', 'address' => "Centro industrial proveedor {$number}", 'status' => 'activo'],
        ));
    }

    private function products()
    {
        return collect(range(1, self::PRODUCTS))->map(function (int $number) {
            $cost = 25 + ($number * 3.75);

            return Product::updateOrCreate(
                ['part_number' => 'MASS-PN-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT)],
                ['sku' => 'MASS-SKU-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT), 'name' => "Repuesto industrial masivo {$number}", 'description' => "Repuesto de prueba de volumen número {$number}.", 'brand' => ['Caterpillar', 'Komatsu', 'JCB', 'Donaldson', 'Fleetguard'][$number % 5], 'type' => 'pieza', 'piece_rotation_type' => $number % 2 ? 'alta_rotacion' : 'baja_rotacion', 'technical_category' => 'mecanico', 'cost' => $cost, 'sales_price' => round($cost * 1.55, 2), 'stock_on_hand' => 100 + $number, 'total_cost' => round((100 + $number) * $cost, 2), 'active' => true],
            );
        });
    }
}
