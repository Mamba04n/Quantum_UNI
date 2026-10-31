<?php

namespace App\Domain\Pedidos\OrderStatus\Services;

use App\Domain\Pedidos\OrderStatus\Support\OrderStatusGeneralStatusResolver;
use App\Domain\Pedidos\OrderStatus\Support\OrderStatusStateCatalog;
use App\Models\OrderStatus;
use App\Models\OrderStatusItem;
use App\Services\OrderStatusSyncService;

class OrderStatusRecalculator
{
    public function __construct(
        private readonly OrderStatusGeneralStatusResolver $generalStatusResolver,
        private readonly OrderStatusStateCatalog $states
    ) {
    }

    public function recalculate(OrderStatus $status): OrderStatus
    {
        $status->loadMissing('items');
        $items = $status->items;

        if ($items->isEmpty()) {
            return $status;
        }

        $statuses = $items->pluck('status');
        $generalStatus = $this->generalStatusResolver->resolve($statuses);
        $progress = (int) round($items->avg(fn (OrderStatusItem $item) => $this->states->progressForStatus($item->status)));

        $status->forceFill([
            'administrative_status' => $generalStatus,
            'administrative_phase' => $this->states->phaseForGeneralStatus($generalStatus),
            'progress_percentage' => $progress,
        ])->save();

        app(OrderStatusSyncService::class)->syncTrackerFromOrderStatus($status);

        return $status->fresh(['items', 'documents', 'events']);
    }
}
