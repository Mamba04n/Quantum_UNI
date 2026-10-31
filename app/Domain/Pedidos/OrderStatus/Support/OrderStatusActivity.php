<?php

namespace App\Domain\Pedidos\OrderStatus\Support;

use App\Domain\Pedidos\OrderStatus\Services\CustomerOrderProgressNotifier;
use App\Models\OrderStatus;
use App\Models\OrderStatusItem;
use App\Models\User;
use App\Services\OrderStatusSyncService;
use Illuminate\Support\Facades\DB;

class OrderStatusActivity
{
    public function record(
        OrderStatus $status,
        ?OrderStatusItem $item,
        string $eventType,
        string $phase,
        ?string $oldStatus,
        ?string $newStatus,
        string $description,
        ?User $user = null,
        ?string $notes = null
    ): void {
        $event = $status->events()->create([
            'order_status_item_id' => $item?->id,
            'event_type' => $eventType,
            'phase' => $phase,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'user_id' => $user?->id,
            'description' => $description,
            'notes' => $notes,
        ]);

        app(OrderStatusSyncService::class)->recordTrackerEvent($status, $eventType, [
            'description' => $description,
            'phase' => $phase,
            'source_module' => $item ? 'order_status_items' : 'order_statuses',
            'source_id' => $item?->id ?? $status->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
        ], $user);

        DB::afterCommit(fn () => app(CustomerOrderProgressNotifier::class)->notifyEvent($event->id));
    }
}
