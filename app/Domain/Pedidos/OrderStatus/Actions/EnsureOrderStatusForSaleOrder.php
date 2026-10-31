<?php

namespace App\Domain\Pedidos\OrderStatus\Actions;

use App\Domain\Pedidos\OrderStatus\Services\OrderStatusService;
use App\Domain\Pedidos\OrderStatus\Support\OrderStatusActivity;
use App\Models\ActivityLog;
use App\Models\OrderStatus;
use App\Models\OrderStatusItem;
use App\Models\SaleOrder;
use App\Models\User;
use App\Services\OrderTrackerService;

class EnsureOrderStatusForSaleOrder
{
    public function __construct(
        private readonly OrderTrackerService $trackerService,
        private readonly OrderStatusActivity $activity
    ) {
    }

    public function handle(SaleOrder $order, ?User $user = null): OrderStatus
    {
        $order->loadMissing('items.product', 'customer');
        $tracker = $this->trackerService->trackerForOrder($order);

        $status = OrderStatus::firstOrCreate(
            ['sale_order_id' => $order->id],
            [
                'order_tracker_id' => $tracker?->id,
                'customer_id' => $order->customer_id,
                'responsible_id' => $order->generated_by ?: $order->approved_by,
                'administrative_phase' => OrderStatusService::PHASE_PURCHASE,
                'administrative_status' => OrderStatusService::STATUS_PENDING_PO,
                'currency' => 'USD',
                'total_amount' => $order->total_amount ?: 0,
                'approved_at' => optional($order->approved_at ?: $order->generated_at)->toDateString(),
                'observations' => $order->notes,
            ]
        );

        if ($status->wasRecentlyCreated) {
            $this->activity->record(
                $status,
                null,
                'order_status.created',
                OrderStatusService::PHASE_PURCHASE,
                null,
                OrderStatusService::STATUS_PENDING_PO,
                "Seguimiento de pedido creado para {$order->reference}.",
                $user
            );

            ActivityLog::log('order_status', 'order_status.created', "Seguimiento de pedido creado para {$order->reference}.", [
                'order_id' => $order->id,
                'order_status_id' => $status->id,
            ]);
        }

        foreach ($order->items as $line) {
            OrderStatusItem::firstOrCreate(
                ['sale_order_item_id' => $line->id],
                [
                    'order_status_id' => $status->id,
                    'product_id' => $line->product_id,
                    'part_name' => $line->description,
                    'part_number' => $line->part_number,
                    'brand' => $line->brand,
                    'model' => $line->model,
                    'quantity_ordered' => $line->quantity ?: 0,
                    'quantity_confirmed' => 0,
                    'quantity_shipped' => 0,
                    'quantity_in_customs' => 0,
                    'quantity_received' => 0,
                    'quantity_kardex_entry' => 0,
                    'quantity_kardex_output' => 0,
                    'quantity_dispatched' => 0,
                    'quantity_delivered' => 0,
                    'quantity_cancelled' => 0,
                    'quantity_pending' => $line->quantity ?: 0,
                    'status' => OrderStatusService::STATUS_PENDING_PO,
                ]
            );
        }

        return $status->fresh(['items', 'documents']);
    }
}
