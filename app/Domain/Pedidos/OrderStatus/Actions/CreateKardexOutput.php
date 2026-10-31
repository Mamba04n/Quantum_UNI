<?php

namespace App\Domain\Pedidos\OrderStatus\Actions;

use App\Domain\Pedidos\OrderStatus\Services\OrderStatusService;
use App\Domain\Pedidos\OrderStatus\Support\OrderStatusActivity;
use App\Domain\Pedidos\OrderStatus\Support\OrderStatusDocumentRequirement;
use App\Domain\Pedidos\OrderStatus\Support\OrderStatusInventoryProduct;
use App\Enums\StockMovementType;
use App\Models\OrderStatusItem;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Validation\ValidationException;

class CreateKardexOutput
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly OrderStatusActivity $activity,
        private readonly OrderStatusDocumentRequirement $documents,
        private readonly OrderStatusInventoryProduct $inventoryProduct
    ) {}

    public function handle(OrderStatusItem $item, float $quantity, ?User $user = null, ?string $attachmentPath = null): OrderStatusItem
    {
        $item = OrderStatusItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
        $this->documents->assertExists($item, 'kardex_output_document');
        $quantity = $this->positiveQuantity($quantity);
        $this->inventoryProduct->assertLinked($item);

        if ((float) $item->quantity_kardex_entry <= 0) {
            $item->quantity_kardex_entry = (float) $item->quantity_received > 0
                ? (float) $item->quantity_received
                : ((float) $item->quantity_ordered > 0 ? (float) $item->quantity_ordered : $quantity);
        }

        if ((float) $item->quantity_kardex_output + $quantity > (float) $item->quantity_kardex_entry) {
            if ($quantity <= (float) $item->quantity_ordered) {
                $item->quantity_kardex_entry = max((float) $item->quantity_ordered, (float) $item->quantity_kardex_output + $quantity);
            } else {
                throw ValidationException::withMessages(['quantity' => 'No puedes sacar más cantidad que la ingresada a Kardex.']);
            }
        }

        $item->loadMissing('orderStatus');

        $move = $this->inventoryService->recordMovement(
            productId: $item->product_id,
            type: StockMovementType::Out,
            quantity: (int) $quantity,
            movementDate: now(),
            reference: 'Salida desde estado del pedido #'.$item->orderStatus->tracker?->tracking_code,
            attachmentPath: $attachmentPath,
            saleOrderId: $item->orderStatus->sale_order_id,
            saleOrderItemId: $item->sale_order_item_id,
            sourceModule: 'order_status_items',
            sourceId: $item->id
        );

        $oldStatus = $item->status;
        $newOutput = (float) $item->quantity_kardex_output + $quantity;
        $isComplete = $newOutput >= (float) $item->quantity_ordered;
        $newStatus = $isComplete
            ? OrderStatusService::STATUS_DELIVERED
            : OrderStatusService::STATUS_DELIVERED_PARTIAL;

        $item->forceFill([
            'quantity_kardex_output' => $newOutput,
            'quantity_dispatched' => $newOutput,
            'quantity_delivered' => $newOutput,
            'kardex_output_date' => now()->toDateString(),
            'delivered_at' => now(),
            'status' => $newStatus,
        ])->save();

        $this->activity->record(
            $item->orderStatus,
            $item,
            'kardex.output_created_from_order',
            OrderStatusService::PHASE_KARDEX_OUTPUT,
            $oldStatus,
            $newStatus,
            "Salida Kardex {$move->reference} creada.",
            $user
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
