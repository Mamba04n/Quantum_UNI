<?php

namespace App\Domain\Pedidos\OrderStatus\Actions;

use App\Domain\Pedidos\OrderStatus\Services\OrderStatusService;
use App\Domain\Pedidos\OrderStatus\Support\OrderStatusActivity;
use App\Models\OrderStatusItem;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class HoldItemForOtherItems
{
    public function __construct(
        private readonly OrderStatusActivity $activity
    ) {
    }

    public function handle(OrderStatusItem $item, array $waitingForItemIds = [], ?User $user = null, ?string $notes = null): OrderStatusItem
    {
        $item = OrderStatusItem::whereKey($item->id)->lockForUpdate()->firstOrFail();

        if ((float) $item->quantity_kardex_entry <= 0) {
            throw ValidationException::withMessages(['quantity' => 'Solo puedes retener una partida que ya esta disponible en bodega.']);
        }

        $waitingForItemIds = collect($waitingForItemIds)
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => $id === $item->id)
            ->unique()
            ->values();

        if ($waitingForItemIds->isEmpty()) {
            throw ValidationException::withMessages(['waitingForItemIds' => 'Selecciona al menos una partida que esta pendiente para esta entrega.']);
        }

        $validCount = OrderStatusItem::query()
            ->where('order_status_id', $item->order_status_id)
            ->whereIn('id', $waitingForItemIds)
            ->whereNotIn('status', [OrderStatusService::STATUS_CANCELLED_SUPPLIER, OrderStatusService::STATUS_DELIVERED, OrderStatusService::STATUS_CLOSED])
            ->count();

        if ($validCount !== $waitingForItemIds->count()) {
            throw ValidationException::withMessages(['waitingForItemIds' => 'Solo puedes seleccionar partidas activas de esta misma orden.']);
        }

        $oldStatus = $item->status;
        $item->forceFill([
            'status' => OrderStatusService::STATUS_WAITING_OTHER_ITEMS,
            'waiting_for_item_ids' => $waitingForItemIds->all(),
        ])->save();
        $item->loadMissing('orderStatus');

        $this->activity->record(
            $item->orderStatus,
            $item,
            'dispatch.waiting_other_items',
            OrderStatusService::PHASE_WAREHOUSE,
            $oldStatus,
            OrderStatusService::STATUS_WAITING_OTHER_ITEMS,
            'Partida retenida mientras llegan: '.implode(', ', $waitingForItemIds->all()).'.',
            $user,
            $notes
        );

        return $item;
    }
}
