<?php

namespace App\Domain\Pedidos\OrderStatus\Actions;

use App\Domain\Pedidos\OrderStatus\Services\OrderStatusService;
use App\Domain\Pedidos\OrderStatus\Support\OrderStatusActivity;
use App\Domain\Pedidos\OrderStatus\Support\OrderStatusDocumentRequirement;
use App\Models\OrderStatusItem;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class RegisterCustomsEntry
{
    public function __construct(
        private readonly OrderStatusActivity $activity,
        private readonly OrderStatusDocumentRequirement $documents
    ) {
    }

    public function handle(OrderStatusItem $item, float $quantity, ?User $user = null, ?string $notes = null): OrderStatusItem
    {
        $item = OrderStatusItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
        $this->documents->assertExists($item, 'shipping_document');
        $quantity = $this->positiveQuantity($quantity);
        $maxAllowed = max(0, (float) $item->quantity_shipped - (float) $item->quantity_in_customs);

        if ($quantity > $maxAllowed) {
            throw ValidationException::withMessages(['quantity' => 'No puedes pasar a aduana mas cantidad que la enviada.']);
        }

        $oldStatus = $item->status;
        $item->forceFill([
            'quantity_in_customs' => (float) $item->quantity_in_customs + $quantity,
            'customs_entry_date' => now()->toDateString(),
            'status' => OrderStatusService::STATUS_IN_CUSTOMS,
        ])->save();

        $item->loadMissing('orderStatus');

        $this->activity->record(
            $item->orderStatus,
            $item,
            'customs.entry_registered',
            OrderStatusService::PHASE_CUSTOMS,
            $oldStatus,
            OrderStatusService::STATUS_IN_CUSTOMS,
            "Entrada a aduana registrada: {$quantity} unidad(es).",
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
