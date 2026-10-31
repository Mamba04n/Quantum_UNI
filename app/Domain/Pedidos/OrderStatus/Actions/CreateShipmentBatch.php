<?php

namespace App\Domain\Pedidos\OrderStatus\Actions;

use App\Domain\Pedidos\OrderStatus\Services\OrderStatusService;
use App\Domain\Pedidos\OrderStatus\Support\OrderStatusActivity;
use App\Models\OrderStatus;
use App\Models\OrderStatusItem;
use App\Models\ShipmentBatch;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CreateShipmentBatch
{
    public function __construct(
        private readonly OrderStatusActivity $activity
    ) {
    }

    public function handle(OrderStatus $status, array $data, array $items, ?User $user = null): ShipmentBatch
    {
        $status = OrderStatus::whereKey($status->id)->lockForUpdate()->firstOrFail();

        $batch = ShipmentBatch::create([
            'order_status_id' => $status->id,
            'supplier_id' => $data['supplier_id'] ?? null,
            'shipment_code' => $data['shipment_code'] ?? ('SHIP-'.$status->id.'-'.now()->format('YmdHis')),
            'carrier_name' => $data['carrier_name'] ?? null,
            'tracking_number' => $data['tracking_number'] ?? null,
            'origin_country' => $data['origin_country'] ?? null,
            'destination_country' => $data['destination_country'] ?? null,
            'departure_date' => $data['departure_date'] ?? now()->toDateString(),
            'estimated_arrival_date' => $data['estimated_arrival_date'] ?? null,
            'actual_arrival_date' => $data['actual_arrival_date'] ?? null,
            'status' => $data['status'] ?? OrderStatusService::STATUS_IN_TRANSIT,
            'notes' => $data['notes'] ?? null,
        ]);

        foreach ($items as $itemId => $quantity) {
            $item = OrderStatusItem::where('order_status_id', $status->id)
                ->whereKey($itemId)
                ->lockForUpdate()
                ->firstOrFail();

            $quantity = $this->positiveQuantity((float) $quantity);
            $maxAllowed = max(0, (float) $item->quantity_confirmed - (float) $item->quantity_shipped);

            if ($quantity > $maxAllowed) {
                throw ValidationException::withMessages(['quantity' => "No puedes enviar mas cantidad que la confirmada para {$this->itemLabel($item)}."]);
            }

            $batch->items()->create([
                'order_status_item_id' => $item->id,
                'quantity_shipped' => $quantity,
            ]);

            $oldStatus = $item->status;
            $item->forceFill([
                'quantity_shipped' => (float) $item->quantity_shipped + $quantity,
                'actual_supplier_dispatch_date' => $data['departure_date'] ?? now()->toDateString(),
                'estimated_arrival_date' => $data['estimated_arrival_date'] ?? $item->estimated_arrival_date,
                'status' => OrderStatusService::STATUS_IN_TRANSIT,
            ])->save();

            $this->activity->record($status, $item, 'shipment.item_added', OrderStatusService::PHASE_TRANSIT, $oldStatus, OrderStatusService::STATUS_IN_TRANSIT, "Partida agregada al embarque {$batch->shipment_code}: {$quantity} unidad(es).", $user);
        }

        $this->activity->record($status, null, 'shipment.batch_created', OrderStatusService::PHASE_TRANSIT, $status->administrative_status, OrderStatusService::STATUS_IN_TRANSIT, "Embarque {$batch->shipment_code} creado.", $user, $data['notes'] ?? null);

        return $batch;
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
