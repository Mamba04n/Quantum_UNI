<?php

namespace App\Domain\Pedidos\OrderStatus\Actions;

use App\Domain\Pedidos\OrderStatus\Services\OrderStatusService;
use App\Domain\Pedidos\OrderStatus\Support\OrderStatusActivity;
use App\Models\OrderStatus;
use App\Models\OrderStatusItem;
use App\Models\PurchaseOrder;
use App\Models\User;

class SyncPurchaseOrderToOrderStatus
{
    public function __construct(
        private readonly EnsureOrderStatusForSaleOrder $ensureOrderStatus,
        private readonly OrderStatusActivity $activity
    ) {
    }

    public function handle(PurchaseOrder $purchaseOrder, ?User $user = null): ?OrderStatus
    {
        $purchaseOrder->loadMissing('items', 'saleOrder.items');

        // Las OC nacidas de una cotizacion comercial ya se controlan por sus
        // partidas en el Kanban. Mientras no exista venta asociada no deben
        // abrir un seguimiento adicional etiquetado como compra interna.
        if ($purchaseOrder->quotation_request_id && ! $purchaseOrder->sale_order_id) {
            return null;
        }

        if (! $purchaseOrder->sale_order_id || ! $purchaseOrder->saleOrder) {
            return $this->syncInternalPurchase($purchaseOrder, $user);
        }

        $status = $this->ensureOrderStatus->handle($purchaseOrder->saleOrder, $user);

        foreach ($purchaseOrder->items as $purchaseItem) {
            // Las OC antiguas pueden haber sido generadas antes de que este
            // vínculo se persistiera. Se recupera por la partida de cotización
            // dentro de la misma venta, sin cruzar pedidos de otros clientes.
            $saleOrderItemId = $purchaseItem->sale_order_item_id
                ?: $purchaseOrder->saleOrder->items
                    ->firstWhere('quotation_request_item_id', $purchaseItem->quotation_request_item_id)
                    ?->id;

            if (! $saleOrderItemId) {
                continue;
            }

            if (! $purchaseItem->sale_order_item_id) {
                $purchaseItem->forceFill(['sale_order_item_id' => $saleOrderItemId])->save();
            }

            $statusItem = OrderStatusItem::where('order_status_id', $status->id)
                ->where('sale_order_item_id', $saleOrderItemId)
                ->lockForUpdate()
                ->first();

            if (! $statusItem) {
                continue;
            }

            $oldStatus = $statusItem->status;
            $newStatus = in_array($oldStatus, [OrderStatusService::STATUS_PENDING_PO, OrderStatusService::STATUS_WAITING_SUPPLIER], true)
                ? OrderStatusService::STATUS_PO_GENERATED
                : $oldStatus;

            $statusItem->forceFill([
                'purchase_order_item_id' => $purchaseItem->id,
                'product_id' => $purchaseItem->product_id ?: $statusItem->product_id,
                'supplier_id' => $purchaseOrder->supplier_id,
                'part_name' => $purchaseItem->part_name ?: $statusItem->part_name,
                'part_number' => $purchaseItem->part_number ?: $statusItem->part_number,
                'brand' => $purchaseItem->brand ?: $statusItem->brand,
                'model' => $purchaseItem->model ?: $statusItem->model,
                'quantity_ordered' => $purchaseItem->quantity ?: $statusItem->quantity_ordered,
                'estimated_arrival_date' => $purchaseItem->expected_date ?: $purchaseOrder->expected_date,
                'status' => $newStatus,
            ])->save();

            if ($oldStatus !== $newStatus) {
                $this->activity->record(
                    $status,
                    $statusItem,
                    'purchase_order.synced_to_order_status',
                    OrderStatusService::PHASE_PURCHASE,
                    $oldStatus,
                    $newStatus,
                    "Partida vinculada a OC {$purchaseOrder->reference_number}; pendiente de envio al proveedor.",
                    $user
                );
            }
        }

        return $status;
    }

    private function syncInternalPurchase(PurchaseOrder $purchaseOrder, ?User $user): OrderStatus
    {
        $status = OrderStatus::firstOrCreate(
            ['purchase_order_id' => $purchaseOrder->id],
            [
                'order_code' => $purchaseOrder->reference_number,
                'administrative_tracking_code' => 'INT-'.$purchaseOrder->reference_number,
                'responsible_id' => $purchaseOrder->generated_by,
                'administrative_phase' => OrderStatusService::PHASE_PURCHASE,
                'administrative_status' => OrderStatusService::STATUS_PO_GENERATED,
                'currency' => $purchaseOrder->currency ?: 'USD',
                'total_amount' => $purchaseOrder->total_fob ?: 0,
                'estimated_arrival_date' => $purchaseOrder->expected_date,
                'observations' => 'Compra interna para inventario.',
            ]
        );

        foreach ($purchaseOrder->items as $line) {
            OrderStatusItem::updateOrCreate(
                ['purchase_order_item_id' => $line->id],
                [
                    'order_status_id' => $status->id,
                    'product_id' => $line->product_id,
                    'supplier_id' => $purchaseOrder->supplier_id,
                    'part_name' => $line->part_name ?: $line->product?->name,
                    'part_number' => $line->part_number ?: $line->product?->part_number,
                    'brand' => $line->brand ?: $line->product?->brand,
                    'model' => $line->model,
                    'quantity_ordered' => $line->quantity ?: 0,
                    'quantity_confirmed' => $line->quantity ?: 0,
                    'quantity_shipped' => $line->quantity ?: 0,
                    'quantity_received' => 0,
                    'quantity_kardex_entry' => 0,
                    'quantity_kardex_output' => 0,
                    'quantity_dispatched' => 0,
                    'quantity_delivered' => 0,
                    'quantity_cancelled' => 0,
                    'quantity_pending' => $line->quantity ?: 0,
                    'estimated_arrival_date' => $line->expected_date ?: $purchaseOrder->expected_date,
                    'status' => OrderStatusService::STATUS_PO_GENERATED,
                ]
            );
        }

        if ($status->wasRecentlyCreated) {
            $this->activity->record(
                $status,
                null,
                'purchase_order.internal_tracking_created',
                OrderStatusService::PHASE_PURCHASE,
                null,
                OrderStatusService::STATUS_PO_GENERATED,
                "Compra interna {$purchaseOrder->reference_number} enviada a recepción de bodega.",
                $user
            );
        }

        return $status->fresh('items');
    }
}
