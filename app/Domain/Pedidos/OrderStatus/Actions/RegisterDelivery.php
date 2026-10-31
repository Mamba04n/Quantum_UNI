<?php

namespace App\Domain\Pedidos\OrderStatus\Actions;

use App\Domain\Pedidos\OrderStatus\Services\OrderStatusService;
use App\Domain\Pedidos\OrderStatus\Support\OrderStatusActivity;
use App\Domain\Pedidos\OrderStatus\Support\OrderStatusDocumentRequirement;
use App\Models\OrderStatusItem;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class RegisterDelivery
{
    public function __construct(
        private readonly OrderStatusActivity $activity,
        private readonly OrderStatusDocumentRequirement $documents
    ) {}

    public function handle(OrderStatusItem $item, float $quantity, ?User $user = null, ?string $notes = null): OrderStatusItem
    {
        $item = OrderStatusItem::whereKey($item->id)->lockForUpdate()->firstOrFail();

        if (in_array($item->status, [OrderStatusService::STATUS_DELIVERED, OrderStatusService::STATUS_CLOSED], true)) {
            throw ValidationException::withMessages(['quantity' => 'Esta partida ya fue entregada y su proceso está culminado.']);
        }

        $this->documents->assertExists($item, 'delivery_confirmation');
        $quantity = $this->positiveQuantity($quantity);

        if ((float) $item->quantity_delivered + $quantity > (float) $item->quantity_dispatched) {
            throw ValidationException::withMessages(['quantity' => 'No puedes entregar mas cantidad que la despachada.']);
        }

        $newDelivered = (float) $item->quantity_delivered + $quantity;
        $newStatus = $newDelivered >= (float) $item->quantity_ordered
            ? OrderStatusService::STATUS_DELIVERED
            : OrderStatusService::STATUS_DELIVERED_PARTIAL;
        $oldStatus = $item->status;

        $item->forceFill([
            'quantity_delivered' => $newDelivered,
            'delivered_at' => now(),
            'status' => $newStatus,
        ])->save();

        $item->loadMissing('orderStatus');

        $this->activity->record(
            $item->orderStatus,
            $item,
            'delivery.delivered_registered',
            OrderStatusService::PHASE_DELIVERY,
            $oldStatus,
            $newStatus,
            "Entrega al cliente registrada: {$quantity} unidad(es).",
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
}
