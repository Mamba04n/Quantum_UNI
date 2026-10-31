<?php

namespace App\Domain\Pedidos\OrderStatus\Actions;

use App\Domain\Pedidos\OrderStatus\Services\OrderStatusService;
use App\Domain\Pedidos\OrderStatus\Support\OrderStatusActivity;
use App\Models\OrderStatusItem;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ConfirmSupplierItem
{
    public function __construct(
        private readonly OrderStatusActivity $activity
    ) {
    }

    public function handle(OrderStatusItem $item, float $quantity, ?User $user = null, ?string $notes = null): OrderStatusItem
    {
        $item = OrderStatusItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
        $quantity = $this->positiveQuantity($quantity);

        if ($quantity > $this->remainingOrdered($item)) {
            throw ValidationException::withMessages(['quantity' => 'No puedes confirmar mas cantidad que la ordenada pendiente.']);
        }

        $oldStatus = $item->status;
        $confirmed = (float) $item->quantity_confirmed + $quantity;

        $item->forceFill([
            'quantity_confirmed' => $confirmed,
            'quantity_pending' => max(0, (float) $item->quantity_ordered - $confirmed - (float) $item->quantity_cancelled),
            'status' => OrderStatusService::STATUS_SUPPLIER_CONFIRMED,
        ])->save();

        $item->loadMissing('orderStatus');

        $this->activity->record(
            $item->orderStatus,
            $item,
            'supplier.item_confirmed',
            OrderStatusService::PHASE_SUPPLIER_CONFIRMATION,
            $oldStatus,
            OrderStatusService::STATUS_SUPPLIER_CONFIRMED,
            "Proveedor confirmo {$quantity} unidad(es).",
            $user,
            $notes
        );

        return $item;
    }

    private function positiveQuantity(float $quantity): float
    {
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'La cantidad debe ser mayor que cero.']);
        }

        return $quantity;
    }

    private function remainingOrdered(OrderStatusItem $item): float
    {
        return max(0, (float) $item->quantity_ordered - (float) $item->quantity_cancelled - (float) $item->quantity_confirmed);
    }
}
