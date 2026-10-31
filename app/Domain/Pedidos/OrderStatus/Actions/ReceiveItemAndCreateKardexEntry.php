<?php

namespace App\Domain\Pedidos\OrderStatus\Actions;

use App\Domain\Pedidos\OrderStatus\Services\OrderStatusService;
use App\Domain\Pedidos\OrderStatus\Support\OrderStatusActivity;
use App\Domain\Pedidos\OrderStatus\Support\OrderStatusInventoryProduct;
use App\Domain\Pedidos\OrderStatus\Support\OrderStatusOperationalDocumentStorage;
use App\Enums\StockMovementType;
use App\Models\ActivityLog;
use App\Models\OrderStatusItem;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class ReceiveItemAndCreateKardexEntry
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly OrderStatusActivity $activity,
        private readonly OrderStatusInventoryProduct $inventoryProduct,
        private readonly OrderStatusOperationalDocumentStorage $documents
    ) {
    }

    public function handle(
        OrderStatusItem $item,
        float $quantity,
        float $unitCost = 0,
        ?User $user = null,
        ?string $notes = null,
        UploadedFile|string|null $evidence = null,
        UploadedFile|string|null $photo = null,
        ?string $location = null,
        ?string $condition = null
    ): OrderStatusItem {
        $item = OrderStatusItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
        $quantity = $this->positiveQuantity($quantity);
        $this->inventoryProduct->ensureForKardex($item);

        $maxReceived = (float) $item->quantity_shipped > 0 ? (float) $item->quantity_shipped : (float) $item->quantity_ordered;
        $pendingReceipt = max(0, $maxReceived - (float) $item->quantity_received);

        if ($quantity > $pendingReceipt) {
            throw ValidationException::withMessages(['quantity' => 'No puedes recibir mas cantidad que la pendiente de esta partida.']);
        }

        $item->loadMissing('orderStatus.purchaseOrder', 'purchaseOrderItem.order');
        $status = $item->orderStatus;
        $evidencePath = $this->documents->store($status, $item, OrderStatusService::PHASE_WAREHOUSE, 'warehouse_receipt', $evidence, $user, $notes);
        $photoPath = $this->documents->store($status, $item, OrderStatusService::PHASE_WAREHOUSE, 'warehouse_product_photo', $photo, $user, $notes);

        $move = $this->inventoryService->recordMovement(
            productId: $item->product_id,
            type: StockMovementType::In,
            quantity: (int) $quantity,
            movementDate: now(),
            costIn: $unitCost,
            reference: 'Entrada bodega/Kardex desde pedido #'.$status->tracker?->tracking_code,
            attachmentPath: $evidencePath ?: $photoPath,
            purchaseOrderId: $item->purchaseOrderItem?->purchase_order_id,
            purchaseOrderItemId: $item->purchase_order_item_id,
            saleOrderId: $status->sale_order_id,
            saleOrderItemId: $item->sale_order_item_id,
            sourceModule: 'order_status_items',
            sourceId: $item->id
        );

        $oldStatus = $item->status;
        $newReceived = (float) $item->quantity_received + $quantity;
        $newKardexEntry = (float) $item->quantity_kardex_entry + $quantity;
        $receiptIsComplete = $newReceived >= (float) $item->quantity_ordered;
        $receiptEvent = $receiptIsComplete ? 'warehouse.item_received_total' : 'warehouse.item_received_partial';
        $receiptLabel = $receiptIsComplete ? 'total' : 'parcial';
        $extraNotes = collect([
            $condition ? 'Condicion: '.$condition : null,
            $location ? 'Ubicacion: '.$location : null,
            $notes ?: null,
        ])->filter()->implode(' | ');

        $item->forceFill([
            'quantity_received' => $newReceived,
            'quantity_kardex_entry' => $newKardexEntry,
            'actual_arrival_date' => now()->toDateString(),
            'warehouse_received_date' => now()->toDateString(),
            'kardex_entry_date' => now()->toDateString(),
            'status' => OrderStatusService::STATUS_AVAILABLE_FOR_DISPATCH,
        ])->save();

        // A manual/internal purchase has no sale order. Keep its purchase
        // receipt progress synchronized when Bodega creates this Kardex entry.
        if ($purchaseItem = $item->purchaseOrderItem) {
            $receivedQuantity = min(
                (float) $purchaseItem->quantity,
                (float) ($purchaseItem->received_quantity ?? 0) + $quantity
            );
            $purchaseItem->forceFill([
                'received_quantity' => $receivedQuantity,
                'received_by' => $user?->id,
                'received_at' => now(),
                'kardex_entry_id' => $move->id,
                'status' => $receivedQuantity >= (float) $purchaseItem->quantity ? 'received' : 'partially_received',
            ])->save();

            $purchaseOrder = $purchaseItem->order;
            if ($purchaseOrder) {
                $purchaseOrder->load('items');
                $allReceived = $purchaseOrder->items->isNotEmpty()
                    && $purchaseOrder->items->every(fn ($line) => (float) ($line->received_quantity ?? 0) >= (float) $line->quantity);

                $purchaseOrder->forceFill([
                    'status' => $allReceived ? 'received' : 'partially_received',
                    'received_by' => $allReceived ? $user?->id : $purchaseOrder->received_by,
                    'received_at' => $allReceived ? now() : $purchaseOrder->received_at,
                ])->save();
            }
        }

        $this->activity->record($status, $item, $receiptEvent, OrderStatusService::PHASE_WAREHOUSE, $oldStatus, OrderStatusService::STATUS_AVAILABLE_FOR_DISPATCH, "Recepcion {$receiptLabel} registrada: {$quantity} unidad(es).", $user, $extraNotes ?: null);
        $this->activity->record($status, $item, 'kardex.entry_created', OrderStatusService::PHASE_WAREHOUSE, $oldStatus, OrderStatusService::STATUS_AVAILABLE_FOR_DISPATCH, "Entrada Kardex {$move->reference} creada desde bodega.", $user, $extraNotes ?: null);
        ActivityLog::log('warehouse', 'warehouse.item_received_with_kardex', "Recepcion y Kardex de partida {$this->itemLabel($item)} registrados.", ['order_status_item_id' => $item->id]);

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
