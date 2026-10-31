<?php

namespace App\Domain\Pedidos\OrderStatus\Actions;

use App\Domain\Pedidos\OrderStatus\Support\OrderStatusActivity;
use App\Domain\Pedidos\OrderStatus\Support\OrderStatusDocumentRequirement;
use App\Domain\Pedidos\OrderStatus\Support\OrderStatusStateCatalog;
use App\Models\OrderStatusItem;
use App\Models\User;

class MarkOrderStatusItemStatus
{
    public function __construct(
        private readonly OrderStatusActivity $activity,
        private readonly OrderStatusDocumentRequirement $documents,
        private readonly OrderStatusStateCatalog $states
    ) {
    }

    public function handle(OrderStatusItem $item, string $newStatus, ?User $user = null, ?string $notes = null): OrderStatusItem
    {
        $item = OrderStatusItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
        $phase = $this->states->phaseForItemStatus($newStatus);
        $documentType = $this->states->requiredDocumentForStatus($newStatus);

        if ($documentType) {
            $this->documents->assertExists($item, $documentType);
        }

        $oldStatus = $item->status;
        $item->forceFill(['status' => $newStatus])->save();
        $item->loadMissing('orderStatus');

        $this->activity->record(
            $item->orderStatus,
            $item,
            "item.{$newStatus}",
            $phase,
            $oldStatus,
            $newStatus,
            "Partida {$this->states->itemLabel($item)} cambio a {$this->states->humanStatus($newStatus)}.",
            $user,
            $notes
        );

        return $item;
    }
}
