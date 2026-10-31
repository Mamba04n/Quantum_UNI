<?php

namespace App\Domain\Compras\Receipts\Services;

use App\Enums\StockMovementType;
use App\Models\ActivityLog;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\QuotationRequest;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\OrderTrackerService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseReceiptService
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly OrderTrackerService $trackerService
    ) {}

    public function receive(PurchaseOrder $order, array $quantitiesByItem, User $user, ?UploadedFile $document = null, ?string $notes = null): PurchaseReceipt
    {
        if (! $user->can('purchase_order.receive') && ! $user->can('order.receive_items') && ! $user->can('kardex.entry') && ! $user->can('kardex.create_entry')) {
            throw ValidationException::withMessages(['receipt' => 'No tienes permiso para recibir compras y crear entrada Kardex.']);
        }

        $order->loadMissing('items.product', 'items.saleOrderItem', 'quotationRequest');
        $documentPath = $document?->store('purchase-receipts', 'private');

        return DB::transaction(function () use ($order, $quantitiesByItem, $user, $documentPath, $notes) {
            $receipt = PurchaseReceipt::create([
                'purchase_order_id' => $order->id,
                'reference' => $this->nextReference($order),
                'received_date' => now()->toDateString(),
                'status' => 'received',
                'document_path' => $documentPath,
                'received_by' => $user->id,
                'notes' => $notes,
            ]);

            $receivedAny = false;

            foreach ($order->items as $item) {
                $quantity = (float) ($quantitiesByItem[$item->id] ?? 0);
                if ($quantity <= 0) {
                    continue;
                }

                $this->receiveItem($receipt, $item, $quantity, $user, $documentPath);
                $receivedAny = true;
            }

            if (! $receivedAny) {
                throw ValidationException::withMessages(['receipt' => 'Debes recibir al menos una partida con cantidad mayor que cero.']);
            }

            $this->refreshOrderStatus($order->fresh('items'), $user);
            $this->recordTrackerEvents($receipt->fresh('items.kardexEntry'), $user);

            return $receipt->fresh(['items.kardexEntry', 'purchaseOrder.items']);
        });
    }

    private function receiveItem(PurchaseReceipt $receipt, PurchaseOrderItem $item, float $quantity, User $user, ?string $documentPath): PurchaseReceiptItem
    {
        $item = PurchaseOrderItem::whereKey($item->id)->lockForUpdate()->with('product', 'saleOrderItem')->firstOrFail();

        if (! $item->product_id) {
            throw ValidationException::withMessages(['receipt' => "La partida {$this->lineLabel($item)} no tiene producto vinculado para Kardex."]);
        }

        if ((float) ($item->kardexEntry?->quantity ?? 0) > 0 && (float) $item->received_quantity >= (float) $item->quantity) {
            throw ValidationException::withMessages(['receipt' => "La partida {$this->lineLabel($item)} ya fue recibida completamente."]);
        }

        $remaining = max(0, (float) $item->quantity - (float) ($item->received_quantity ?? 0));
        if ($quantity > $remaining) {
            throw ValidationException::withMessages(['receipt' => "No puedes recibir {$quantity}; solo quedan {$remaining} de {$this->lineLabel($item)}."]);
        }

        $receiptItem = PurchaseReceiptItem::create([
            'purchase_receipt_id' => $receipt->id,
            'purchase_order_item_id' => $item->id,
            'product_id' => $item->product_id,
            'quantity_received' => $quantity,
            'unit_cost' => (float) $item->unit_price,
            'status' => 'received',
            'notes' => $item->notes,
        ]);

        $move = $this->inventoryService->recordMovement(
            productId: $item->product_id,
            type: StockMovementType::In,
            quantity: (int) round($quantity),
            movementDate: now(),
            costIn: (float) $item->unit_price,
            reference: $receipt->reference,
            attachmentPath: $documentPath,
            state: 'hecho',
            purchaseOrderId: $receipt->purchase_order_id,
            purchaseOrderItemId: $item->id,
            purchaseReceiptId: $receipt->id,
            purchaseReceiptItemId: $receiptItem->id,
            saleOrderId: $item->order?->sale_order_id,
            saleOrderItemId: $item->sale_order_item_id,
            sourceModule: 'purchase_receipt_items',
            sourceId: $receiptItem->id
        );

        $receiptItem->forceFill(['kardex_entry_id' => $move->id])->save();

        $newReceived = (float) ($item->received_quantity ?? 0) + $quantity;
        $itemStatus = $newReceived >= (float) $item->quantity ? 'received' : 'partially_received';

        $item->forceFill([
            'received_quantity' => $newReceived,
            'received_by' => $user->id,
            'received_at' => now(),
            'kardex_entry_id' => $move->id,
            'receipt_document_path' => $documentPath ?: $item->receipt_document_path,
            'status' => $itemStatus,
        ])->save();

        $item->saleOrderItem?->forceFill([
            'product_id' => $item->product_id,
            'kardex_entry_id' => $move->id,
            'purchase_status' => $itemStatus,
            'inventory_status' => $itemStatus === 'received' ? 'in_inventory' : 'partially_received',
        ])->save();

        $item->quotationItem?->forceFill([
            'product_id' => $item->product_id,
            'status' => $itemStatus === 'received' ? 'purchase_received' : 'purchase_partially_received',
        ])->save();

        return $receiptItem->fresh('kardexEntry');
    }

    private function refreshOrderStatus(PurchaseOrder $order, User $user): void
    {
        $items = $order->items;
        $receivedCount = $items->whereIn('status', ['received', 'partially_received'])->count();
        $allReceived = $items->isNotEmpty() && $items->every(fn ($item) => $item->status === 'received');

        $order->forceFill([
            'status' => $allReceived ? 'received' : ($receivedCount > 0 ? 'partially_received' : $order->status),
            'received_by' => $allReceived ? $user->id : $order->received_by,
            'received_at' => $allReceived ? now() : $order->received_at,
        ])->save();
    }

    private function recordTrackerEvents(PurchaseReceipt $receipt, User $user): void
    {
        $order = $receipt->purchaseOrder()->with('quotationRequest')->first();
        $quotation = $order?->quotationRequest ?: QuotationRequest::where('sale_order_id', $order?->sale_order_id)->first();

        if ($quotation) {
            $tracker = $this->trackerService->trackerForQuotation($quotation);
            $this->trackerService->addReference($tracker, 'purchase_receipt', $receipt->reference, 'purchase_receipts', $receipt->id);
            $this->trackerService->recordActivity(
                $tracker,
                'purchase_order.received',
                "Recepcion {$receipt->reference} registrada para OC {$order->reference_number}",
                'purchase_order',
                'purchase_receipts',
                $receipt->id,
                null,
                $order->status,
                $user
            );

            foreach ($receipt->items as $item) {
                $this->trackerService->recordActivity(
                    $tracker,
                    'inventory.kardex_entry_created',
                    "Entrada Kardex {$item->kardexEntry?->reference} creada por recepcion {$receipt->reference}",
                    'inventory',
                    'stock_moves',
                    $item->kardex_entry_id,
                    null,
                    'hecho',
                    $user
                );
            }
        }

        ActivityLog::log('compras', 'purchase_order.received', "Recepcion {$receipt->reference} registrada.", [
            'purchase_order_id' => $receipt->purchase_order_id,
            'purchase_receipt_id' => $receipt->id,
        ]);
    }

    private function nextReference(PurchaseOrder $order): string
    {
        $next = PurchaseReceipt::where('purchase_order_id', $order->id)->count() + 1;

        do {
            $reference = 'RCPT-'.$order->reference_number.'-'.str_pad((string) $next, 2, '0', STR_PAD_LEFT);
            $next++;
        } while (PurchaseReceipt::where('reference', $reference)->exists());

        return $reference;
    }

    private function lineLabel(PurchaseOrderItem $item): string
    {
        return $item->part_number ?: ($item->part_name ?: 'sin referencia');
    }
}
