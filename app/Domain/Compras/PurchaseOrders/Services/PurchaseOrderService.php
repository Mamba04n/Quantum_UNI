<?php

namespace App\Domain\Compras\PurchaseOrders\Services;

use App\Models\AccountPayable;
use App\Models\ActivityLog;
use App\Models\ApprovalRequest;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\QuotationRequest;
use App\Models\SaleOrderItem;
use App\Models\SupplierBidSelection;
use App\Models\User;
use App\Services\ExchangeRateService;
use App\Services\OrderStatusService;
use App\Services\OrderTrackerService;
use App\Services\ProductSkuGenerator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PurchaseOrderService
{
    public function __construct(
        private readonly ProductSkuGenerator $skuGenerator,
        private readonly OrderTrackerService $trackerService,
        private readonly OrderStatusService $orderStatusService
    ) {}

    public function createOrderWithPayable(array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($data) {
            $orderData = [
                'supplier_id' => $data['supplier_id'],
                'quotation_request_id' => $data['quotation_request_id'] ?? null,
                'sale_order_id' => $data['sale_order_id'] ?? null,
                'supplier_bid_response_id' => $data['supplier_bid_response_id'] ?? null,
                'reference_number' => $data['reference_number'],
                'status' => 'ordered',
                'source_type' => $data['source_type'] ?? 'manual',
                'total_fob' => $data['total_fob'],
                'currency' => $data['currency'] ?? 'USD',
                'order_date' => $data['order_date'],
                'expected_date' => $data['expected_date'],
                'generated_by' => $data['generated_by'] ?? auth()->id(),
                'generated_at' => $data['generated_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ];

            if (Schema::hasColumn('purchase_orders', 'approval_request_id')) {
                $orderData['approval_request_id'] = $data['approval_request_id'] ?? null;
            }

            $order = PurchaseOrder::create($orderData);

            foreach ($data['items'] as $item) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $order->id,
                    'product_id' => $item['product_id'] ?? null,
                    'quotation_request_item_id' => $item['quotation_request_item_id'] ?? null,
                    'sale_order_item_id' => $item['sale_order_item_id'] ?? null,
                    'supplier_bid_selection_id' => $item['supplier_bid_selection_id'] ?? null,
                    'supplier_bid_response_item_id' => $item['supplier_bid_response_item_id'] ?? null,
                    'part_name' => $item['part_name'] ?? null,
                    'part_number' => $item['part_number'] ?? null,
                    'brand' => $item['brand'] ?? null,
                    'model' => $item['model'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['total_price'],
                    'currency' => $item['currency'] ?? $data['currency'] ?? 'USD',
                    'expected_date' => $item['expected_date'] ?? $data['expected_date'] ?? null,
                    'status' => $item['status'] ?? 'ordered',
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            $this->createPayable($order, $data);
            $this->orderStatusService->syncPurchaseOrder($order->fresh('items'), auth()->user());

            return $order->fresh('items');
        });
    }

    public function createOrdersFromQuotationSelections(QuotationRequest $request, User $user, ?ApprovalRequest $approval = null): Collection
    {
        if (! $user->can('purchases.order.create') && ! $user->can('purchase_order.create') && ! $user->can('purchase_order.generate')) {
            throw ValidationException::withMessages(['purchase' => 'No tienes permiso para crear ordenes de compra.']);
        }

        $request->loadMissing([
            'items.product',
            'supplierBidSelections.responseItem.response.supplier',
            'purchasePreparationItems.finalSupplierQuote.response.supplier',
        ]);

        if (! in_array($request->status, [
            QuotationRequest::STATUS_APPROVED_TOTAL,
            QuotationRequest::STATUS_APPROVED_PARTIAL,
            OrderTrackerService::QUOTE_PENDING_APPROVAL,
        ], true) && ! $request->sale_order_id) {
            throw ValidationException::withMessages(['purchase' => 'No se puede crear compra sin oferta aprobada u orden generada.']);
        }

        $preparationByQuotationItem = $request->purchasePreparationItems
            ->keyBy('quotation_request_item_id');

        $selections = $request->supplierBidSelections
            ->map(function (SupplierBidSelection $selection) use ($preparationByQuotationItem) {
                $preparation = $preparationByQuotationItem->get($selection->quotation_request_item_id);
                $finalQuote = $preparation?->finalSupplierQuote;

                // La preparacion de compra es la fuente final cuando el proveedor
                // fue ajustado despues de la seleccion comercial inicial.
                if ($preparation?->final_supplier_id && $finalQuote) {
                    $selection->forceFill([
                        'selected_supplier_id' => $preparation->final_supplier_id,
                        'selected_quantity' => $preparation->quantity_to_purchase,
                        'selected_unit_cost' => $preparation->final_unit_cost,
                        'currency' => $finalQuote->currency ?: $selection->currency,
                        'estimated_arrival_date' => $finalQuote->estimated_arrival_date ?: $selection->estimated_arrival_date,
                    ]);
                    $selection->setRelation('responseItem', $finalQuote);
                }

                return $selection;
            })
            ->filter(fn (SupplierBidSelection $selection) => $selection->selected_supplier_id && (float) $selection->selected_unit_cost > 0)
            ->reject(fn (SupplierBidSelection $selection) => PurchaseOrderItem::where('supplier_bid_selection_id', $selection->id)->exists())
            ->values();

        if ($selections->isEmpty()) {
            throw ValidationException::withMessages(['purchase' => 'No hay partidas proveedor seleccionadas pendientes de compra.']);
        }

        return DB::transaction(function () use ($request, $user, $selections, $approval) {
            $orders = collect();

            foreach ($selections->groupBy('selected_supplier_id') as $supplierId => $supplierSelections) {
                $firstSelection = $supplierSelections->first();
                $response = $firstSelection->responseItem?->response;
                $expectedDate = $supplierSelections
                    ->pluck('estimated_arrival_date')
                    ->filter()
                    ->sort()
                    ->first();
                $total = $supplierSelections->sum(fn (SupplierBidSelection $selection) => (float) $selection->selected_quantity * (float) $selection->selected_unit_cost);

                $orderData = [
                    'supplier_id' => $supplierId,
                    'quotation_request_id' => $request->id,
                    'sale_order_id' => $request->sale_order_id,
                    'supplier_bid_response_id' => $response?->id,
                    'reference_number' => $this->nextReference(),
                    'status' => 'ordered',
                    'source_type' => 'supplier_selection',
                    'total_fob' => round($total, 2),
                    'currency' => $firstSelection->currency ?: ($response?->currency ?: 'USD'),
                    'order_date' => now()->toDateString(),
                    'expected_date' => $expectedDate?->toDateString(),
                    'generated_by' => $user->id,
                    'generated_at' => now(),
                    'notes' => "Generada desde seleccion de proveedor para {$request->reference}.",
                ];

                if (Schema::hasColumn('purchase_orders', 'approval_request_id')) {
                    $orderData['approval_request_id'] = $approval?->id;
                }

                $order = PurchaseOrder::create($orderData);

                foreach ($supplierSelections as $selection) {
                    $selection->loadMissing('quotationItem.product', 'responseItem');
                    $quoteItem = $selection->quotationItem;
                    $responseItem = $selection->responseItem;
                    $product = $this->productForSelection($selection);
                    // La OC pertenece a una venta concreta. Restringir la búsqueda a
                    // esa venta evita perder (o tomar de otra venta) el vínculo que
                    // usa el seguimiento del pedido para mostrar proveedor y estado.
                    $saleOrderItem = $request->sale_order_id
                        ? SaleOrderItem::query()
                            ->where('sale_order_id', $request->sale_order_id)
                            ->where('quotation_request_item_id', $quoteItem->id)
                            ->first()
                        : null;
                    $quantity = (float) $selection->selected_quantity;
                    $unitCost = (float) $selection->selected_unit_cost;

                    PurchaseOrderItem::create([
                        'purchase_order_id' => $order->id,
                        'product_id' => $product?->id,
                        'quotation_request_item_id' => $quoteItem->id,
                        'sale_order_item_id' => $saleOrderItem?->id,
                        'supplier_bid_selection_id' => $selection->id,
                        'supplier_bid_response_item_id' => $responseItem?->id,
                        'part_name' => $responseItem?->quoted_description ?: $quoteItem->part_name,
                        'part_number' => $responseItem?->quoted_part_number ?: $quoteItem->part_number,
                        'brand' => $responseItem?->quoted_brand ?: $quoteItem->brand,
                        'model' => $responseItem?->quoted_model ?: $quoteItem->model,
                        'quantity' => $quantity,
                        'unit_price' => $unitCost,
                        'total_price' => round($quantity * $unitCost, 2),
                        'currency' => $selection->currency ?: 'USD',
                        'expected_date' => $selection->estimated_arrival_date,
                        'status' => 'ordered',
                        'notes' => $responseItem?->internal_comment,
                    ]);

                    $quoteItem->forceFill([
                        'product_id' => $product?->id ?: $quoteItem->product_id,
                        'status' => 'purchase_ordered',
                    ])->save();

                    $saleOrderItem?->forceFill([
                        'product_id' => $product?->id ?: $saleOrderItem->product_id,
                        'purchase_status' => 'ordered',
                        'inventory_status' => 'pending_entry',
                    ])->save();
                }

                $this->createPayable($order, [
                    'order_date' => $order->order_date,
                    'expected_date' => $order->expected_date,
                ]);
                $this->orderStatusService->syncPurchaseOrder($order->fresh('items'), $user);

                $orders->push($order->fresh('items'));
            }

            $tracker = $this->trackerService->trackerForQuotation($request);
            foreach ($orders as $order) {
                $this->trackerService->addReference($tracker, 'purchase_order', $order->reference_number, 'purchase_orders', $order->id);
                $this->trackerService->recordActivity(
                    $tracker,
                    $approval ? 'purchase_order.generated_from_approval' : 'purchase_order.created',
                    "Orden de compra {$order->reference_number} generada para proveedor {$order->supplier?->name}",
                    'purchase_order',
                    'purchase_orders',
                    $order->id,
                    null,
                    $order->status,
                    $user
                );
            }

            ActivityLog::log('compras', 'purchase_order.created_from_selection', "Ordenes de compra generadas desde {$request->reference}", [
                'quotation_request_id' => $request->id,
                'purchase_order_ids' => $orders->pluck('id')->all(),
            ]);

            return $orders;
        });
    }

    private function productForSelection(SupplierBidSelection $selection): ?Product
    {
        $selection->loadMissing('quotationItem.product', 'responseItem');
        $quoteItem = $selection->quotationItem;
        $responseItem = $selection->responseItem;

        if ($quoteItem->product) {
            return $quoteItem->product;
        }

        $partNumber = $responseItem?->quoted_part_number ?: $quoteItem->part_number;
        if (filled($partNumber)) {
            $product = Product::query()
                ->where('sku', $partNumber)
                ->orWhere('part_number', $partNumber)
                ->first();

            if ($product) {
                return $product;
            }
        }

        $product = Product::create([
            'name' => $responseItem?->quoted_description ?: ($quoteItem->part_name ?: $quoteItem->description),
            'description' => $quoteItem->notes ?: $quoteItem->description,
            'part_number' => $partNumber,
            'brand' => $responseItem?->quoted_brand ?: $quoteItem->brand,
            'type' => 'pieza',
            'piece_rotation_type' => 'baja_rotacion',
            'technical_category' => 'otro',
            'sales_price' => 0,
            'cost' => (float) $selection->selected_unit_cost,
            'total_cost' => 0,
            'stock_on_hand' => 0,
            'active' => true,
        ]);

        $product->forceFill(['sku' => $this->skuGenerator->generate($product)])->save();

        return $product;
    }

    private function createPayable(PurchaseOrder $order, array $data): void
    {
        $currency = strtoupper($order->currency ?: 'USD');
        $originalAmount = $order->total_fob;
        $conversion = app(ExchangeRateService::class)->convert($originalAmount, $currency, 'NIO');

        AccountPayable::updateOrCreate(
            [
                'entity_id' => $order->supplier_id,
                'invoice_number' => $order->reference_number,
            ],
            [
                'supplier_id' => $order->supplier_id,
                'purchase_order_id' => $order->id,
                'concept' => 'Orden de Compra '.$order->reference_number,
                'classification' => 'Compra de piezas',
                'condition' => '30 Dias',
                'invoice_date' => $data['order_date'] ?? now()->toDateString(),
                // Finance balances are always NIO. Keep the supplier's USD
                // amount separately so both values remain visible and auditable.
                'total_amount' => $conversion['amount'],
                'amount_usd' => $currency === 'USD' ? $originalAmount : null,
                'exchange_rate' => $conversion['rate'],
                'paid_amount' => 0,
                'currency' => 'NIO',
                'due_date' => $data['expected_date'] ?? now()->addDays(30)->toDateString(),
                'status' => 'pendiente',
            ]
        );
    }

    private function nextReference(): string
    {
        $next = PurchaseOrder::count() + 1;
        $year = now()->format('y');

        do {
            $reference = 'OC-'.$year.'-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            $next++;
        } while (PurchaseOrder::where('reference_number', $reference)->exists());

        return $reference;
    }
}
