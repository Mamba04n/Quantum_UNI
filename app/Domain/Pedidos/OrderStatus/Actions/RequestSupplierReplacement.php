<?php

namespace App\Domain\Pedidos\OrderStatus\Actions;

use App\Domain\Pedidos\OrderStatus\Services\OrderStatusService;
use App\Domain\Pedidos\OrderStatus\Support\OrderStatusActivity;
use App\Models\OrderStatusItem;
use App\Models\ReplacementRequest;
use App\Models\User;

class RequestSupplierReplacement
{
    public function __construct(
        private readonly OrderStatusActivity $activity
    ) {
    }

    public function handle(OrderStatusItem $item, string $reason, ?User $user = null): ReplacementRequest
    {
        $item = OrderStatusItem::whereKey($item->id)->lockForUpdate()->firstOrFail();

        $replacement = ReplacementRequest::firstOrCreate(
            [
                'order_status_item_id' => $item->id,
                'status' => 'requested',
            ],
            [
                'original_supplier_id' => $item->supplier_id,
                'reason' => $reason,
                'created_by' => $user?->id,
            ]
        );

        $oldStatus = $item->status;
        $item->forceFill([
            'status' => OrderStatusService::STATUS_REPLACEMENT_REQUIRED,
            'replacement_required' => true,
        ])->save();

        $item->loadMissing('orderStatus');

        $this->activity->record(
            $item->orderStatus,
            $item,
            'item.replacement_requested',
            OrderStatusService::PHASE_PURCHASE,
            $oldStatus,
            OrderStatusService::STATUS_REPLACEMENT_REQUIRED,
            "Reemplazo solicitado: {$reason}",
            $user
        );

        return $replacement;
    }
}
