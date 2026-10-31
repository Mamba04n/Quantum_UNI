<?php

namespace App\Domain\Pedidos\OrderStatus\Actions;

use App\Domain\Pedidos\OrderStatus\Services\OrderStatusService;
use App\Domain\Pedidos\OrderStatus\Support\OrderStatusActivity;
use App\Domain\Pedidos\OrderStatus\Support\OrderStatusDocumentRequirement;
use App\Domain\Pedidos\OrderStatus\Support\OrderStatusStateCatalog;
use App\Models\OrderStatus;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class MarkProviderPurchaseOrderSent
{
    public function __construct(
        private readonly OrderStatusActivity $activity,
        private readonly OrderStatusDocumentRequirement $documents,
        private readonly OrderStatusStateCatalog $states
    ) {
    }

    public function handle(
        OrderStatus $status,
        ?User $user = null,
        ?string $notes = null,
        ?PurchaseOrder $purchaseOrder = null
    ): OrderStatus
    {
        $status = OrderStatus::whereKey($status->id)
            ->lockForUpdate()
            ->with('items.purchaseOrderItem')
            ->firstOrFail();

        $items = $purchaseOrder
            ? $status->items->filter(fn ($item) => (int) $item->purchaseOrderItem?->purchase_order_id === (int) $purchaseOrder->id)
            : $status->items;

        if ($purchaseOrder && $items->isEmpty()) {
            throw ValidationException::withMessages([
                'purchase_order' => 'La orden de compra no tiene partidas vinculadas a este seguimiento.',
            ]);
        }

        foreach ($items as $item) {
            $this->documents->assertExists($item, 'purchase_order_provider_pdf');
        }

        foreach ($items as $item) {
            if (! in_array($item->status, [OrderStatusService::STATUS_PENDING_PO, OrderStatusService::STATUS_PO_GENERATED], true)) {
                continue;
            }

            $oldStatus = $item->status;
            $item->forceFill(['status' => OrderStatusService::STATUS_WAITING_SUPPLIER])->save();

            $this->activity->record(
                $status,
                $item,
                'purchase_order.sent_to_provider',
                OrderStatusService::PHASE_PURCHASE,
                $oldStatus,
                OrderStatusService::STATUS_WAITING_SUPPLIER,
                "OC proveedor enviada para {$this->states->itemLabel($item)}.",
                $user,
                $notes
            );
        }

        $this->activity->record(
            $status,
            null,
            'purchase_order.sent_to_provider',
            OrderStatusService::PHASE_PURCHASE,
            $status->administrative_status,
            OrderStatusService::STATUS_WAITING_SUPPLIER,
            $purchaseOrder
                ? "Orden de compra {$purchaseOrder->reference_number} marcada como enviada al proveedor."
                : 'Orden de compra marcada como enviada al proveedor.',
            $user,
            $notes
        );

        return $status;
    }
}
