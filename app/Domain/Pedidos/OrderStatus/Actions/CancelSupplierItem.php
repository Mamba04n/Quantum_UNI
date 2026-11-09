<?php

namespace App\Domain\Pedidos\OrderStatus\Actions;

use App\Domain\Pedidos\OrderStatus\Services\OrderStatusService;
use App\Domain\Pedidos\OrderStatus\Support\OrderStatusActivity;
use App\Models\OrderStatusItem;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CancelSupplierItem
{
    public function __construct(
        private readonly OrderStatusActivity $activity
    ) {
    }

    public function handle(OrderStatusItem $item, string $reason, ?User $user = null): OrderStatusItem
    {
        $item = OrderStatusItem::whereKey($item->id)->lockForUpdate()->firstOrFail();

        if ((float) $item->quantity_received > 0) {
            throw ValidationException::withMessages(['quantity' => 'No puedes cancelar una partida ya recibida sin reversa o ajuste autorizado.']);
        }

        $oldStatus = $item->status;
        $item->forceFill([
            'status' => OrderStatusService::STATUS_CANCELLED_SUPPLIER,
            'quantity_cancelled' => max(0, (float) $item->quantity_ordered - (float) $item->quantity_received),
            'quantity_pending' => 0,
            'cancellation_reason' => $reason,
        ])->save();

        $item->loadMissing(['orderStatus', 'supplier']);
        $supplierText = $item->supplier?->name ? "Proveedor [{$item->supplier->name}]" : "Proveedor";

        $this->activity->record(
            $item->orderStatus,
            $item,
            'item.cancelled_by_supplier',
            OrderStatusService::PHASE_PURCHASE,
            $oldStatus,
            OrderStatusService::STATUS_CANCELLED_SUPPLIER,
            "{$supplierText} canceló partida: {$reason}",
            $user
        );

        return $item;
    }
}
