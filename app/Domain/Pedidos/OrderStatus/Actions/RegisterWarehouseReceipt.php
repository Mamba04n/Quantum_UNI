<?php

namespace App\Domain\Pedidos\OrderStatus\Actions;

use App\Domain\Pedidos\OrderStatus\Services\OrderStatusService;
use App\Domain\Pedidos\OrderStatus\Support\OrderStatusActivity;
use App\Domain\Pedidos\OrderStatus\Support\OrderStatusDocumentRequirement;
use App\Models\ActivityLog;
use App\Models\OrderStatusItem;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class RegisterWarehouseReceipt
{
    public function __construct(
        private readonly OrderStatusActivity $activity,
        private readonly OrderStatusDocumentRequirement $documents
    ) {
    }

    public function handle(OrderStatusItem $item, float $quantity, ?User $user = null, ?string $notes = null): OrderStatusItem
    {
        $item = OrderStatusItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
        $this->documents->assertExists($item, 'warehouse_receipt');
        $quantity = $this->positiveQuantity($quantity);
        $maxReceived = (float) $item->quantity_shipped > 0 ? (float) $item->quantity_shipped : (float) $item->quantity_ordered;

        if ((float) $item->quantity_received + $quantity > $maxReceived) {
            throw ValidationException::withMessages(['quantity' => 'No puedes recibir mas cantidad que la enviada.']);
        }

        $newReceived = (float) $item->quantity_received + $quantity;
        $newStatus = $newReceived >= (float) $item->quantity_ordered
            ? OrderStatusService::STATUS_RECEIVED_WAREHOUSE
            : OrderStatusService::STATUS_RECEIVED_WAREHOUSE_PARTIAL;
        $oldStatus = $item->status;

        $item->forceFill([
            'quantity_received' => $newReceived,
            'actual_arrival_date' => now()->toDateString(),
            'warehouse_received_date' => now()->toDateString(),
            'status' => $newStatus,
        ])->save();

        $item->loadMissing('orderStatus');

        $this->activity->record(
            $item->orderStatus,
            $item,
            'warehouse.receipt_registered',
            OrderStatusService::PHASE_WAREHOUSE,
            $oldStatus,
            $newStatus,
            "Recepcion registrada: {$quantity} unidad(es).",
            $user,
            $notes
        );

        ActivityLog::log('warehouse', 'warehouse.receipt_registered', "Recepcion de partida {$this->itemLabel($item)} registrada.", ['order_status_item_id' => $item->id]);

        return $item;
    }

    private function positiveQuantity(float $quantity): float
    {
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'La cantidad debe ser mayor que cero.']);
        }

        return $quantity;
    }

    private function itemLabel(OrderStatusItem $item): string
    {
        return $item->part_number ?: ($item->part_name ?: '#'.$item->id);
    }
}
