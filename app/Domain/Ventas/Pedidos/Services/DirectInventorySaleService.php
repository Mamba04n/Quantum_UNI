<?php

namespace App\Domain\Ventas\Pedidos\Services;

use App\Domain\Finanzas\ExchangeRates\Services\ExchangeRateService;
use App\Domain\Inventario\Services\InventoryService;
use App\Domain\Shared\Sequences\DocumentSequenceService;
use App\Enums\StockMovementType;
use App\Models\AccountReceivable;
use App\Models\ActivityLog;
use App\Models\Entity;
use App\Models\InventoryReservation;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mpdf\Mpdf;
use Throwable;

class DirectInventorySaleService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly ExchangeRateService $exchangeRates,
        private readonly DocumentSequenceService $sequences,
    ) {}

    public function create(array $data, User $user): SaleOrder
    {
        $existing = SaleOrder::query()->where('idempotency_key', $data['idempotency_key'])->first();
        if ($existing) {
            return $existing->load(['items.product', 'invoices', 'accountReceivable']);
        }

        $storedPath = null;

        try {
            return DB::transaction(function () use ($data, $user, &$storedPath): SaleOrder {
                $customer = Entity::query()->whereKey($data['customer_id'])->where('type', 'cliente')->firstOrFail();
                $currency = strtoupper($data['currency'] ?? 'NIO');
                $rateData = $this->exchangeRates->rateToNio($currency);
                $rate = (float) $rateData['rate'];
                $items = collect($data['items'])->filter(fn (array $item) => (int) ($item['quantity'] ?? 0) > 0)->values();

                if ($items->isEmpty()) {
                    throw ValidationException::withMessages(['items' => 'Agrega al menos un producto con cantidad válida.']);
                }

                $productIds = $items->pluck('product_id')->map(fn ($id) => (int) $id);
                if ($productIds->duplicates()->isNotEmpty()) {
                    throw ValidationException::withMessages(['items' => 'Un producto no puede aparecer dos veces en la misma venta.']);
                }

                $products = Product::query()->whereIn('id', $productIds)->lockForUpdate()->get()->keyBy('id');
                $subtotal = 0.0;
                foreach ($items as $index => $line) {
                    $product = $products->get((int) $line['product_id']);
                    if (! $product || ! $product->active) {
                        throw ValidationException::withMessages(["items.{$index}.product_id" => 'El producto seleccionado no está disponible.']);
                    }
                    $quantity = (int) $line['quantity'];
                    $reserved = (int) InventoryReservation::query()
                        ->where('product_id', $product->id)->where('status', 'reserved')->sum('quantity');
                    if (((int) $product->stock_on_hand - $reserved) < $quantity) {
                        throw ValidationException::withMessages(["items.{$index}.quantity" => "Stock insuficiente para {$product->name}. Disponible: ".max(0, (int) $product->stock_on_hand - $reserved).'.']);
                    }
                    if ((float) $line['unit_price'] <= 0) {
                        throw ValidationException::withMessages(["items.{$index}.unit_price" => "Ingresa un precio válido para {$product->name}. "]);
                    }
                    $subtotal += round($quantity * (float) $line['unit_price'], 2);
                }

                $taxPercent = ! empty($data['apply_tax']) ? 15.0 : 0.0;
                $taxAmount = round($subtotal * ($taxPercent / 100), 2);
                $total = round($subtotal + $taxAmount, 2);
                $totalNio = $currency === 'NIO' ? $total : round($total * $rate, 2);
                $reference = $this->sequences->formattedNext('sale_order', 'VD', 6);
                $invoiceNumber = $this->sequences->formattedNext('customer_invoice', 'FAC-', 6);
                $dueDate = $data['payment_type'] === 'Crédito' ? $data['due_date'] : now()->toDateString();

                $order = SaleOrder::create([
                    'reference' => $reference,
                    'idempotency_key' => $data['idempotency_key'],
                    'date' => now()->toDateString(),
                    'customer_id' => $customer->id,
                    'client_name' => $customer->legal_name ?: $customer->name,
                    'client_email' => $customer->email ?: $customer->contact_info,
                    'client_ruc' => $customer->rfc_ruc,
                    'shipping_address' => $customer->exact_address ?: $customer->address,
                    'payment_type' => $data['payment_type'],
                    'status' => 'entregado',
                    'source_type' => 'direct_inventory_sale',
                    'generated_by' => $user->id,
                    'generated_at' => now(),
                    'subtotal' => $subtotal,
                    'tax_percent' => $taxPercent,
                    'tax_amount' => $taxAmount,
                    'total_amount' => $total,
                    'currency' => $currency,
                    'exchange_rate' => $rate,
                    'notes' => $data['notes'] ?? null,
                    'invoice_number' => $invoiceNumber,
                    'invoice_date' => now()->toDateString(),
                ]);

                foreach ($items as $line) {
                    $product = $products->get((int) $line['product_id']);
                    $quantity = (int) $line['quantity'];
                    $item = SaleOrderItem::create([
                        'sale_order_id' => $order->id,
                        'product_id' => $product->id,
                        'part_number' => $product->part_number ?: $product->sku,
                        'description' => $product->name,
                        'quantity' => $quantity,
                        'unit_price' => round((float) $line['unit_price'], 2),
                        'total_price' => round($quantity * (float) $line['unit_price'], 2),
                        'source_type' => 'inventory',
                        'inventory_status' => 'dispatched',
                        'purchase_status' => 'not_required',
                        'item_status' => 'entregado',
                    ]);
                    $move = $this->inventory->recordMovement(
                        productId: $product->id,
                        type: StockMovementType::Out,
                        quantity: $quantity,
                        reference: $reference,
                        saleOrderId: $order->id,
                        saleOrderItemId: $item->id,
                        sourceModule: 'direct_inventory_sale',
                        sourceId: $order->id,
                    );
                    $item->forceFill(['kardex_output_id' => $move->id])->save();
                }

                $invoice = Invoice::create([
                    'invoice_number' => $invoiceNumber,
                    'invoice_type' => 'customer_invoice',
                    'entity_id' => $customer->id,
                    'customer_id' => $customer->id,
                    'sales_order_id' => $order->id,
                    'date' => now()->toDateString(),
                    'due_date' => $dueDate,
                    'cotizacion_ref' => $reference,
                    'description' => "Venta directa {$reference}",
                    'amount_nio' => $totalNio,
                    'amount_usd' => $currency === 'USD' ? $total : null,
                    'subtotal' => $currency === 'NIO' ? $subtotal : round($subtotal * $rate, 2),
                    'tax_amount' => $currency === 'NIO' ? $taxAmount : round($taxAmount * $rate, 2),
                    'total' => $totalNio,
                    'currency' => 'NIO',
                    'condition' => $data['payment_type'],
                    'classification' => 'Venta directa de inventario',
                    'status' => $data['payment_type'] === 'Contado' ? 'Pagado' : 'Pendiente',
                    'generated_by' => $user->id,
                ]);

                $storedPath = $this->storeInvoicePdf($invoice->load(['entity', 'saleOrder.items.product']));
                $invoice->forceFill(['invoice_file_path' => $storedPath])->save();

                $receivable = AccountReceivable::create([
                    'receivable_code' => $this->sequences->formattedNext('account_receivable', 'CXC-', 6),
                    'entity_id' => $customer->id,
                    'customer_id' => $customer->id,
                    'sales_order_id' => $order->id,
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoiceNumber,
                    'concept' => "Venta directa {$reference}",
                    'classification' => 'Venta directa de inventario',
                    'condition' => $data['payment_type'],
                    'invoice_date' => now()->toDateString(),
                    'total_amount' => $totalNio,
                    'amount_usd' => $currency === 'USD' ? $total : null,
                    'exchange_rate' => $rate,
                    'paid_amount' => $data['payment_type'] === 'Contado' ? $totalNio : 0,
                    'currency' => 'NIO',
                    'due_date' => $dueDate,
                    'support_document_path' => $storedPath,
                    'created_by' => $user->id,
                    'status' => $data['payment_type'] === 'Contado' ? 'pagado' : 'pendiente',
                    'collected_at' => $data['payment_type'] === 'Contado' ? now() : null,
                ]);

                if ($data['payment_type'] === 'Contado') {
                    Payment::create([
                        'payable_type' => AccountReceivable::class,
                        'payable_id' => $receivable->id,
                        'accounts_receivable_id' => $receivable->id,
                        'invoice_id' => $invoice->id,
                        'payment_code' => $this->sequences->formattedNext('payment', 'PAG-', 6),
                        'payment_type' => 'cxc',
                        'amount' => $totalNio,
                        'amount_original' => $total,
                        'currency' => $currency,
                        'exchange_rate' => $rate,
                        'payment_date' => now()->toDateString(),
                        'payment_method' => $data['payment_method'],
                        'payment_reference' => $data['payment_reference'] ?? $reference,
                        'receipt_reference' => $data['payment_reference'] ?? $reference,
                        'registered_by' => $user->id,
                        'notes' => 'Cobro registrado al confirmar la venta directa.',
                    ]);
                }

                ActivityLog::log('ventas', 'direct_sale.created', "Venta directa {$reference} registrada y facturada.", [
                    'sale_order_id' => $order->id,
                    'invoice_id' => $invoice->id,
                    'account_receivable_id' => $receivable->id,
                ]);

                return $order->load(['items.product', 'invoices', 'accountReceivable']);
            }, 3);
        } catch (Throwable $exception) {
            if ($storedPath) {
                Storage::disk('private')->delete($storedPath);
            }
            throw $exception;
        }
    }

    private function storeInvoicePdf(Invoice $invoice): string
    {
        $mpdf = new Mpdf(['format' => 'Letter', 'margin_left' => 15, 'margin_right' => 15, 'margin_top' => 15, 'margin_bottom' => 20]);
        $mpdf->WriteHTML(view('pdf.invoice_template', compact('invoice'))->render());
        $path = "direct-sales/{$invoice->sales_order_id}/Factura_{$invoice->invoice_number}.pdf";
        if (! Storage::disk('private')->put($path, $mpdf->Output('', 'S'))) {
            throw ValidationException::withMessages(['invoice' => 'No fue posible guardar la factura de la venta.']);
        }

        return $path;
    }
}
