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

class CreateKardexEntry
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly OrderStatusActivity $activity,
        private readonly OrderStatusDocumentRequirement $documents,
        private readonly OrderStatusInventoryProduct $inventoryProduct
    ) {
    }

    public function handle(OrderStatusItem $item, float $quantity, float $unitCost, ?User $user = null, ?string $attachmentPath = null): OrderStatusItem
    {
        $item = OrderStatusItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
        $this->documents->assertExists($item, 'kardex_entry_document');
        $quantity = $this->positiveQuantity($quantity);
        $this->inventoryProduct->ensureForKardex($item);

        if ((float) $item->quantity_kardex_entry + $quantity > (float) $item->quantity_received) {
            throw ValidationException::withMessages(['quantity' => 'No puedes ingresar a Kardex mas cantidad que la recibida.']);
        }

        $item->loadMissing('orderStatus');

        $move = $this->inventoryService->recordMovement(
            productId: $item->product_id,
            type: StockMovementType::In,
            quantity: (int) $quantity,
            movementDate: now(),
            costIn: $unitCost,
            reference: 'Entrada desde estado del pedido #'.$item->orderStatus->tracker?->tracking_code,
            attachmentPath: $attachmentPath,
            saleOrderId: $item->orderStatus->sale_order_id,
            saleOrderItemId: $item->sale_order_item_id,
            sourceModule: 'order_status_items',
            sourceId: $item->id
        );

        $oldStatus = $item->status;
        $newEntry = (float) $item->quantity_kardex_entry + $quantity;
        $newStatus = $newEntry >= (float) $item->quantity_ordered
            ? OrderStatusService::STATUS_KARDEX_ENTRY
            : OrderStatusService::STATUS_KARDEX_ENTRY_PARTIAL;

        $item->forceFill([
            'quantity_kardex_entry' => $newEntry,
            'kardex_entry_date' => now()->toDateString(),
            'status' => $newStatus,
        ])->save();

        $this->activity->record(
            $item->orderStatus,
            $item,
            'kardex.entry_created_from_order',
            OrderStatusService::PHASE_KARDEX_ENTRY,
            $oldStatus,
            $newStatus,
            "Entrada Kardex {$move->reference} creada.",
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
