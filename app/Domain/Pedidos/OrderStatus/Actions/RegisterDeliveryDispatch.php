<?php

namespace App\Domain\Pedidos\OrderStatus\Actions;

use App\Domain\Pedidos\OrderStatus\Services\OrderStatusService;
use App\Domain\Pedidos\OrderStatus\Support\OrderStatusActivity;
use App\Domain\Pedidos\OrderStatus\Support\OrderStatusDocumentRequirement;
use App\Models\OrderStatusItem;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class RegisterDeliveryDispatch
{
    public function __construct(
        private readonly OrderStatusActivity $activity,
        private readonly OrderStatusDocumentRequirement $documents
    ) {
    }

    public function handle(OrderStatusItem $item, float $quantity, ?User $user = null, ?string $notes = null): OrderStatusItem
    {
        $item = OrderStatusItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
        $this->documents->assertExists($item, 'delivery_logistics_document');
        $quantity = $this->positiveQuantity($quantity);
        $available = max(0, (float) $item->quantity_kardex_output - (float) $item->quantity_dispatched);

        if ($quantity > $available) {
            throw ValidationException::withMessages(['quantity' => 'No puedes despachar mas cantidad que la salida de Kardex disponible.']);
        }

        $oldStatus = $item->status;
        $item->forceFill([
            'quantity_dispatched' => (float) $item->quantity_dispatched + $quantity,
            'delivery_dispatch_date' => now()->toDateString(),
            'status' => OrderStatusService::STATUS_SENT_CUSTOMER,
        ])->save();

        $item->loadMissing('orderStatus');

        $this->activity->record(
            $item->orderStatus,
            $item,
            'delivery.dispatch_registered',
            OrderStatusService::PHASE_LOGISTICS,
            $oldStatus,
            OrderStatusService::STATUS_SENT_CUSTOMER,
            "Despacho al cliente registrado: {$quantity} unidad(es).",
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
