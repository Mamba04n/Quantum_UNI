<?php

namespace App\Domain\Pedidos\OrderStatus\Services;

use App\Domain\Pedidos\OrderStatus\Actions\AuthorizePartialDispatch;
use App\Domain\Pedidos\OrderStatus\Actions\CancelSupplierItem;
use App\Domain\Pedidos\OrderStatus\Actions\ConfirmSupplierItem;
use App\Domain\Pedidos\OrderStatus\Actions\CreateKardexEntry;
use App\Domain\Pedidos\OrderStatus\Actions\CreateKardexOutput;
use App\Domain\Pedidos\OrderStatus\Actions\CreateShipmentBatch;
use App\Domain\Pedidos\OrderStatus\Actions\EnsureOrderStatusForSaleOrder;
use App\Domain\Pedidos\OrderStatus\Actions\HoldItemForOtherItems;
use App\Domain\Pedidos\OrderStatus\Actions\MarkOrderStatusItemStatus;
use App\Domain\Pedidos\OrderStatus\Actions\MarkProviderPurchaseOrderSent;
use App\Domain\Pedidos\OrderStatus\Actions\ReceiveItemAndCreateKardexEntry;
use App\Domain\Pedidos\OrderStatus\Actions\RegisterCustomsEntry;
use App\Domain\Pedidos\OrderStatus\Actions\RegisterDelivery;
use App\Domain\Pedidos\OrderStatus\Actions\RegisterDeliveryDispatch;
use App\Domain\Pedidos\OrderStatus\Actions\RegisterWarehouseReceipt;
use App\Domain\Pedidos\OrderStatus\Actions\RequestDispatchAuthorization;
use App\Domain\Pedidos\OrderStatus\Actions\RequestSupplierReplacement;
use App\Domain\Pedidos\OrderStatus\Actions\SyncPurchaseOrderToOrderStatus;
use App\Domain\Pedidos\OrderStatus\Support\OrderStatusActivity;
use App\Domain\Pedidos\OrderStatus\Support\OrderStatusStateCatalog;
use App\Models\OrderStatus;
use App\Models\OrderStatusDocument;
use App\Models\OrderStatusItem;
use App\Models\PurchaseOrder;
use App\Models\ReplacementRequest;
use App\Models\SaleOrder;
use App\Models\ShipmentBatch;
use App\Models\User;
use App\Services\PackingListService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderStatusService
{
    public const STATUS_PENDING_ORDER = 'pendiente_orden';

    public const STATUS_ORDER_GENERATED = 'orden_generada';

    public const STATUS_ORDER_SENT = 'orden_enviada';

    public const STATUS_PENDING_PO = 'pendiente_oc';

    public const STATUS_PO_GENERATED = 'oc_generada';

    public const STATUS_WAITING_SUPPLIER = 'esperando_confirmacion_proveedor';

    public const STATUS_SUPPLIER_CONFIRMED = 'confirmado_proveedor';

    public const STATUS_PAYMENT_CONFIRMED = 'pago_confirmado';

    public const STATUS_SUPPLIER_DISPATCHED = 'salida_proveedor';

    public const STATUS_IN_TRANSIT = 'en_transito';

    public const STATUS_IN_CUSTOMS = 'en_aduana';

    public const STATUS_CUSTOMS_RELEASED = 'liberado_aduana';

    public const STATUS_RECEIVED_WAREHOUSE = 'recibido_bodega_total';

    public const STATUS_RECEIVED_WAREHOUSE_PARTIAL = 'recibido_bodega_parcial';

    public const STATUS_KARDEX_ENTRY = 'entrada_kardex_total';

    public const STATUS_KARDEX_ENTRY_PARTIAL = 'entrada_kardex_parcial';

    public const STATUS_RESERVED_CUSTOMER = 'reservado_cliente';

    public const STATUS_AVAILABLE_FOR_DISPATCH = 'available_for_dispatch';

    public const STATUS_HELD_BY_ADMIN = 'held_by_admin';

    public const STATUS_DISPATCH_AUTHORIZATION_REQUESTED = 'dispatch_authorization_requested';

    public const STATUS_APPROVED_PARTIAL_DISPATCH = 'approved_for_partial_dispatch';

    public const STATUS_WAITING_OTHER_ITEMS = 'waiting_other_items';

    public const STATUS_KARDEX_OUTPUT = 'salida_kardex_total';

    public const STATUS_KARDEX_OUTPUT_PARTIAL = 'salida_kardex_parcial';

    public const STATUS_SENT_CUSTOMER = 'en_ruta_cliente';

    public const STATUS_DELIVERED = 'entregado_total';

    public const STATUS_DELIVERED_PARTIAL = 'entregado_parcial';

    public const STATUS_CANCELLED_SUPPLIER = 'cancelado_proveedor';

    public const STATUS_REPLACEMENT_REQUIRED = 'reemplazo_requerido';

    public const STATUS_REPLACEMENT_QUOTING = 'reemplazo_en_cotizacion';

    public const STATUS_REPLACEMENT_APPROVED = 'reemplazo_aprobado';

    public const STATUS_CLOSED = 'cerrado';

    public const PHASE_PURCHASE = 'pedido_extranjero';

    public const PHASE_SUPPLIER_CONFIRMATION = 'confirmacion_proveedor';

    public const PHASE_TRANSIT = 'transito';

    public const PHASE_CUSTOMS = 'aduana';

    public const PHASE_WAREHOUSE = 'bodega';

    public const PHASE_KARDEX_ENTRY = 'kardex_entrada';

    public const PHASE_KARDEX_OUTPUT = 'kardex_salida';

    public const PHASE_LOGISTICS = 'logistica';

    public const PHASE_DELIVERY = 'entregado';

    public function __construct(
        private readonly PackingListService $packingListService,
        private readonly OrderStatusStateCatalog $states
    ) {}

    public function ensureForSaleOrder(SaleOrder $order, ?User $user = null): OrderStatus
    {
        return DB::transaction(function () use ($order, $user) {
            $status = app(EnsureOrderStatusForSaleOrder::class)->handle($order, $user);

            return $this->recalculate($status);
        });
    }

    public function attachDocument(OrderStatus $status, ?OrderStatusItem $item, string $phase, string $documentType, UploadedFile|string $file, ?User $user = null, ?string $notes = null): OrderStatusDocument
    {
        return DB::transaction(function () use ($status, $item, $phase, $documentType, $file, $user, $notes) {
            if ($file instanceof UploadedFile) {
                $path = $file->store('order-status-documents', 'private');
                $fileName = $file->getClientOriginalName();
            } else {
                $path = $file;
                $fileName = basename($file);
            }

            $document = OrderStatusDocument::create([
                'order_status_id' => $status->id,
                'order_status_item_id' => $item?->id,
                'phase' => $phase,
                'document_type' => $documentType,
                'file_name' => $fileName,
                'file_path' => $path,
                'uploaded_by' => $user?->id,
                'generated_by' => $user?->id,
                'notes' => $notes,
            ]);

            $this->recordEvent($status, $item, "{$documentType}.attached", $phase, $item?->status, $item?->status, "Documento {$documentType} adjuntado.", $user, $notes);

            return $document;
        });
    }

    public function syncPurchaseOrder(PurchaseOrder $purchaseOrder, ?User $user = null): void
    {
        DB::transaction(function () use ($purchaseOrder, $user) {
            $status = app(SyncPurchaseOrderToOrderStatus::class)->handle($purchaseOrder, $user);

            if ($status) {
                $this->recalculate($status);
            }
        });
    }

    public function markProviderPurchaseOrderSent(
        OrderStatus $status,
        ?User $user = null,
        ?string $notes = null,
        ?PurchaseOrder $purchaseOrder = null
    ): OrderStatus {
        return DB::transaction(function () use ($status, $user, $notes, $purchaseOrder) {
            $status = app(MarkProviderPurchaseOrderSent::class)->handle($status, $user, $notes, $purchaseOrder);

            return $this->recalculate($status);
        });
    }

    public function markItemStatus(OrderStatusItem $item, string $newStatus, ?User $user = null, ?string $notes = null): OrderStatusItem
    {
        return DB::transaction(function () use ($item, $newStatus, $user, $notes) {
            $item = app(MarkOrderStatusItemStatus::class)->handle($item, $newStatus, $user, $notes);

            return $this->recalculate($item->orderStatus)->items()->findOrFail($item->id);
        });
    }

    public function confirmSupplierItem(OrderStatusItem $item, float $quantity, ?User $user = null, ?string $notes = null): OrderStatusItem
    {
        return DB::transaction(function () use ($item, $quantity, $user, $notes) {
            $item = app(ConfirmSupplierItem::class)->handle($item, $quantity, $user, $notes);

            return $this->recalculate($item->orderStatus)->items()->findOrFail($item->id);
        });
    }

    public function createShipmentBatch(OrderStatus $status, array $data, array $items, ?User $user = null): ShipmentBatch
    {
        return DB::transaction(function () use ($status, $data, $items, $user) {
            $batch = app(CreateShipmentBatch::class)->handle($status, $data, $items, $user);
            $this->recalculate($batch->orderStatus);

            return $batch->fresh(['supplier', 'items.orderStatusItem']);
        });
    }

    public function registerCustomsEntry(OrderStatusItem $item, float $quantity, ?User $user = null, ?string $notes = null): OrderStatusItem
    {
        return DB::transaction(function () use ($item, $quantity, $user, $notes) {
            $item = app(RegisterCustomsEntry::class)->handle($item, $quantity, $user, $notes);

            return $this->recalculate($item->orderStatus)->items()->findOrFail($item->id);
        });
    }

    public function registerWarehouseReceipt(OrderStatusItem $item, float $quantity, ?User $user = null, ?string $notes = null): OrderStatusItem
    {
        return DB::transaction(function () use ($item, $quantity, $user, $notes) {
            $item = app(RegisterWarehouseReceipt::class)->handle($item, $quantity, $user, $notes);

            return $this->recalculate($item->orderStatus)->items()->findOrFail($item->id);
        });
    }

    public function createKardexEntry(OrderStatusItem $item, float $quantity, float $unitCost, ?User $user = null, ?string $attachmentPath = null): OrderStatusItem
    {
        return DB::transaction(function () use ($item, $quantity, $unitCost, $user, $attachmentPath) {
            $item = app(CreateKardexEntry::class)->handle($item, $quantity, $unitCost, $user, $attachmentPath);

            return $this->recalculate($item->orderStatus)->items()->findOrFail($item->id);
        });
    }

    public function receiveItemAndCreateKardexEntry(
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
        return DB::transaction(function () use ($item, $quantity, $unitCost, $user, $notes, $evidence, $photo, $location, $condition) {
            $item = app(ReceiveItemAndCreateKardexEntry::class)->handle($item, $quantity, $unitCost, $user, $notes, $evidence, $photo, $location, $condition);

            return $this->recalculate($item->orderStatus)->items()->findOrFail($item->id);
        });
    }

    public function authorizePartialDispatch(OrderStatusItem $item, ?User $user = null, ?string $notes = null): OrderStatusItem
    {
        return DB::transaction(function () use ($item, $user, $notes) {
            $item = app(AuthorizePartialDispatch::class)->handle($item, $user, $notes);

            return $this->recalculate($item->orderStatus)->items()->findOrFail($item->id);
        });
    }

    public function requestDispatchAuthorization(OrderStatusItem $item, ?User $user = null, ?string $notes = null): OrderStatusItem
    {
        return DB::transaction(function () use ($item, $user, $notes) {
            $item = app(RequestDispatchAuthorization::class)
                ->handle($item, $user, $notes);

            return $this->recalculate($item->orderStatus)->items()->findOrFail($item->id);
        });
    }

    public function holdItemForOtherItems(OrderStatusItem $item, array $waitingForItemIds = [], ?User $user = null, ?string $notes = null): OrderStatusItem
    {
        return DB::transaction(function () use ($item, $waitingForItemIds, $user, $notes) {
            $item = app(HoldItemForOtherItems::class)->handle($item, $waitingForItemIds, $user, $notes);

            return $this->recalculate($item->orderStatus)->items()->findOrFail($item->id);
        });
    }

    public function createKardexOutput(OrderStatusItem $item, float $quantity, ?User $user = null, ?string $attachmentPath = null): OrderStatusItem
    {
        return DB::transaction(function () use ($item, $quantity, $user, $attachmentPath) {
            $item = app(CreateKardexOutput::class)->handle($item, $quantity, $user, $attachmentPath);

            return $this->recalculate($item->orderStatus)->items()->findOrFail($item->id);
        });
    }

    public function registerDeliveryDispatch(OrderStatusItem $item, float $quantity, ?User $user = null, ?string $notes = null): OrderStatusItem
    {
        return DB::transaction(function () use ($item, $quantity, $user, $notes) {
            $item = app(RegisterDeliveryDispatch::class)->handle($item, $quantity, $user, $notes);
            $status = $this->recalculate($item->orderStatus);
            $this->generatePackingListAfterCommit($status->id, [$item->id => $quantity], $user?->id);

            return $status->items()->findOrFail($item->id);
        });
    }

    public function registerDeliveryDispatchBatch(OrderStatus $status, array $items, ?User $user = null, ?string $notes = null): OrderStatus
    {
        return DB::transaction(function () use ($status, $items, $user, $notes) {
            $status = OrderStatus::with('items')->lockForUpdate()->findOrFail($status->id);
            $dispatchQuantities = collect($items)
                ->mapWithKeys(fn ($quantity, $itemId) => [(int) $itemId => (float) $quantity])
                ->filter(fn ($quantity) => $quantity > 0);

            foreach ($dispatchQuantities as $itemId => $quantity) {
                $item = $status->items->firstWhere('id', $itemId);

                if (! $item) {
                    continue;
                }

                app(RegisterDeliveryDispatch::class)->handle($item, $quantity, $user, $notes);
            }

            $status = $this->recalculate($status->fresh('items'));
            $this->generatePackingListAfterCommit($status->id, $dispatchQuantities->all(), $user?->id);

            return $status->fresh(['items.product', 'documents', 'events']);
        });
    }

    public function registerDelivery(OrderStatusItem $item, float $quantity, ?User $user = null, ?string $notes = null): OrderStatusItem
    {
        return DB::transaction(function () use ($item, $quantity, $user, $notes) {
            $item = app(RegisterDelivery::class)->handle($item, $quantity, $user, $notes);

            return $this->recalculate($item->orderStatus)->items()->findOrFail($item->id);
        });
    }

    public function cancelBySupplier(OrderStatusItem $item, string $reason, ?User $user = null): OrderStatusItem
    {
        return DB::transaction(function () use ($item, $reason, $user) {
            $item = app(CancelSupplierItem::class)->handle($item, $reason, $user);

            return $this->recalculate($item->orderStatus)->items()->findOrFail($item->id);
        });
    }

    public function requestReplacement(OrderStatusItem $item, string $reason, ?User $user = null): ReplacementRequest
    {
        return DB::transaction(function () use ($item, $reason, $user) {
            return app(RequestSupplierReplacement::class)->handle($item, $reason, $user);
        });
    }

    public function recalculate(OrderStatus $status): OrderStatus
    {
        return app(OrderStatusRecalculator::class)->recalculate($status);
    }

    public function requiredDocumentForStatus(string $status): ?string
    {
        return $this->states->requiredDocumentForStatus($status);
    }

    public function recordEvent(OrderStatus $status, ?OrderStatusItem $item, string $eventType, string $phase, ?string $oldStatus, ?string $newStatus, string $description, ?User $user = null, ?string $notes = null): void
    {
        app(OrderStatusActivity::class)->record($status, $item, $eventType, $phase, $oldStatus, $newStatus, $description, $user, $notes);
    }

    public function humanStatus(?string $status): string
    {
        if (! $status) {
            return 'Desconocido';
        }

        return $this->states->humanStatus($status);
    }

    public function humanDocument(string $documentType): string
    {
        return $this->states->humanDocument($documentType);
    }

    /** @param array<int, float|int> $quantities */
    private function generatePackingListAfterCommit(int $orderStatusId, array $quantities, ?int $userId): void
    {
        DB::afterCommit(function () use ($orderStatusId, $quantities, $userId): void {
            try {
                $status = OrderStatus::with(['items', 'saleOrder'])->findOrFail($orderStatusId);
                $this->packingListService->generateAndNotifyForDispatch($status, $quantities, $userId);
            } catch (\Throwable $exception) {
                Log::error('packing_list.generation_failed', [
                    'order_status_id' => $orderStatusId,
                    'user_id' => $userId,
                    'exception' => $exception::class,
                ]);
            }
        });
    }
}
