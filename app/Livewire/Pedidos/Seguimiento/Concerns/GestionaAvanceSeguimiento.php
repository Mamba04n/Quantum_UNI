<?php

namespace App\Livewire\Pedidos\Seguimiento\Concerns;

use App\Models\Entity;
use App\Models\OrderStatusItem;
use App\Services\DocumentSequenceService;
use App\Services\OrderStatusService;
use App\Services\PreprintedInvoiceService;
use App\Services\PurchaseOrderDocumentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

trait GestionaAvanceSeguimiento
{
    public function completeActivePhase(OrderStatusService $statusService, PurchaseOrderDocumentService $documentService, PreprintedInvoiceService $preprintedInvoices): void
    {
        abort_unless(
            auth()->user()?->can('order_status.manage')
            || auth()->user()?->can('purchase_order_provider.edit')
            || auth()->user()?->can('purchase_order_document.edit')
            || auth()->user()?->can('warehouse_receipt.register')
            || auth()->user()?->can('supplier_confirmation.upload')
            || auth()->user()?->can('shipment.upload')
            || auth()->user()?->can('kardex.output_from_order')
            || auth()->user()?->can('logistics.upload_document'),
            403
        );

        if (! $this->selectedGroupKey && $this->selectedItemId) {
            $item = $this->selectedItem();
            if (in_array($item->status, [OrderStatusService::STATUS_DELIVERED, OrderStatusService::STATUS_CLOSED], true)) {
                throw ValidationException::withMessages(['phase' => 'Esta partida ya fue entregada y su proceso está culminado.']);
            }
        }

        try {
            if ($this->selectedGroupKey) {
                $this->completeActiveGroupPhase($statusService, $documentService, $preprintedInvoices);
                return;
            }

            if ($this->activePhase === OrderStatusService::PHASE_KARDEX_OUTPUT) {
                $this->completeKardexOutputPhase($statusService, $preprintedInvoices);
                return;
            }

            $this->attachPendingDocumentForPhase($statusService);

            match ($this->activePhase) {
                OrderStatusService::PHASE_PURCHASE => $this->completePurchasePhase($documentService),
                OrderStatusService::PHASE_SUPPLIER_CONFIRMATION => $this->completeSupplierConfirmationPhase($statusService),
                OrderStatusService::PHASE_TRANSIT => $this->completeTransitPhase($statusService),
                OrderStatusService::PHASE_CUSTOMS => $this->completeItemPhase($statusService, 'shipping_document', fn ($item) => $statusService->registerCustomsEntry($item, $this->quantity, auth()->user(), $this->phaseNotes())),
                OrderStatusService::PHASE_WAREHOUSE => $this->completeWarehousePhase(),
                OrderStatusService::PHASE_LOGISTICS => $this->completeItemPhase($statusService, 'delivery_logistics_document', fn ($item) => $statusService->registerDeliveryDispatch($item, $this->quantity, auth()->user(), $this->phaseNotes())),
                OrderStatusService::PHASE_DELIVERY => $this->completeItemPhase($statusService, 'delivery_confirmation', fn ($item) => $statusService->registerDelivery($item, $this->quantity, auth()->user(), $this->phaseNotes())),
                default => null,
            };
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            throw ValidationException::withMessages(['phase' => \App\Support\UserMessage::error($e, 'No se pudo completar la acción. Revisa los datos e intenta de nuevo.')]);
        }
    }

    public function markItem(OrderStatusService $service): void
    {
        abort_unless(auth()->user()?->can('order_status.manage') || auth()->user()?->can('order_status.force_transition'), 403);
        $item = OrderStatusItem::findOrFail($this->selectedItemId);
        $service->markItemStatus($item, $this->targetStatus, auth()->user(), $this->notes ?: null);
        $this->refreshStatus('Estado actualizado.', true);
    }

    public function registerReceipt(OrderStatusService $service): void
    {
        abort_unless(auth()->user()?->can('warehouse_receipt.register'), 403);
        $item = OrderStatusItem::findOrFail($this->selectedItemId);
        $service->registerWarehouseReceipt($item, $this->quantity, auth()->user(), $this->notes ?: null);
        $this->refreshStatus('Recepcion registrada.', true);
    }

    public function confirmSupplierItem(OrderStatusService $service): void
    {
        abort_unless(auth()->user()?->can('order_status.manage') || auth()->user()?->can('supplier_confirmation.upload'), 403);
        $item = OrderStatusItem::findOrFail($this->selectedItemId);
        $service->confirmSupplierItem($item, $this->quantity, auth()->user(), $this->notes ?: null);
        $this->refreshStatus('Cantidad confirmada por proveedor.', true);
    }

    public function registerCustomsEntry(OrderStatusService $service): void
    {
        abort_unless(auth()->user()?->can('order_status.manage') || auth()->user()?->can('logistics.upload_document'), 403);
        $item = OrderStatusItem::findOrFail($this->selectedItemId);
        $service->registerCustomsEntry($item, $this->quantity, auth()->user(), $this->notes ?: null);
        $this->refreshStatus('Entrada a aduana registrada.', true);
    }

    public function createKardexEntry(OrderStatusService $service): void
    {
        abort_unless(auth()->user()?->can('kardex.entry_from_order'), 403);
        $item = OrderStatusItem::findOrFail($this->selectedItemId);
        $service->createKardexEntry($item, $this->quantity, $this->unitCost, auth()->user());
        $this->refreshStatus('Entrada Kardex creada.', true);
    }

    public function receiveItemAndCreateKardexEntry(OrderStatusService $service): void
    {
        abort_unless(auth()->user()?->can('warehouse_receipt.register') || auth()->user()?->can('kardex.entry_from_order') || auth()->user()?->can('order_status.manage'), 403);

        $this->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'unitCost' => ['nullable', 'numeric', 'min:0'],
            'warehouseCondition' => ['required', 'in:bueno,danado,incompleto,pendiente_revision'],
            'warehouseLocation' => ['nullable', 'string', 'max:120'],
            'warehouseEvidenceFile' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp'],
            'warehousePhotoFile' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $item = $this->selectedItem();
        $service->receiveItemAndCreateKardexEntry(
            $item,
            $this->quantity,
            $this->unitCost,
            auth()->user(),
            $this->notes ?: null,
            $this->warehouseEvidenceFile,
            $this->warehousePhotoFile,
            $this->warehouseLocation ?: null,
            $this->warehouseCondition
        );

        $this->reset(['warehouseEvidenceFile', 'warehousePhotoFile', 'warehouseLocation', 'notes']);
        $this->warehouseCondition = 'bueno';
        $this->refreshStatus('Recepcion registrada y Kardex de entrada creado.', true);
    }

    public function authorizePartialDispatch(OrderStatusService $service): void
    {
        abort_unless(auth()->user()?->can('order_status.manage'), 403);

        $service->authorizePartialDispatch($this->selectedItem(), auth()->user(), $this->notes ?: null);
        $this->refreshStatus('Salida autorizada por Operaciones.', true);
    }

    public function requestDispatchAuthorization(OrderStatusService $service): void
    {
        abort_unless(
            auth()->user()?->can('order_status.manage')
            || auth()->user()?->can('kardex.output_from_order'),
            403,
        );

        $service->requestDispatchAuthorization($this->selectedItem(), auth()->user(), $this->notes ?: null);
        $this->refreshStatus('Autorización de salida solicitada a Operaciones.', true);
    }

    public function requestGroupDispatchAuthorization(OrderStatusService $service): void
    {
        abort_unless(
            auth()->user()?->can('order_status.manage')
            || auth()->user()?->can('kardex.output_from_order'),
            403,
        );

        $items = $this->activeGroupItems();
        DB::transaction(function () use ($items, $service) {
            foreach ($items as $item) {
                if ($item->status !== OrderStatusService::STATUS_APPROVED_PARTIAL_DISPATCH) {
                    $service->requestDispatchAuthorization($item, auth()->user(), $this->notes ?: null);
                }
            }
        });

        $this->refreshStatus('Autorización solicitada para todas las piezas del viaje.', true);
    }

    public function authorizeGroupDispatch(OrderStatusService $service): void
    {
        abort_unless(auth()->user()?->can('order_status.manage'), 403);

        $items = $this->activeGroupItems();
        DB::transaction(function () use ($items, $service) {
            foreach ($items as $item) {
                if ($item->status !== OrderStatusService::STATUS_APPROVED_PARTIAL_DISPATCH) {
                    $service->authorizePartialDispatch($item, auth()->user(), $this->notes ?: null);
                }
            }
        });

        $this->refreshStatus('Salida autorizada por Operaciones para todo el viaje.', true);
    }

    public function prepareWaitForOtherItems(): void
    {
        abort_unless(auth()->user()?->can('order_status.manage'), 403);

        $this->waitingForItemIds = $this->selectedItem()?->waiting_for_item_ids ?: [];
        $this->showWaitingForItems = true;
    }

    public function cancelWaitForOtherItems(): void
    {
        $this->showWaitingForItems = false;
        $this->waitingForItemIds = [];
    }

    public function holdItemForOtherItems(OrderStatusService $service): void
    {
        abort_unless(auth()->user()?->can('order_status.manage'), 403);

        $item = $this->selectedItem();
        $this->validate([
            'waitingForItemIds' => ['required', 'array', 'min:1'],
            'waitingForItemIds.*' => ['integer'],
        ]);

        $service->holdItemForOtherItems($item, $this->waitingForItemIds, auth()->user(), $this->notes ?: null);
        $this->cancelWaitForOtherItems();
        $this->refreshStatus('Partida marcada en espera de otras piezas.', true);
    }

    public function createKardexOutput(OrderStatusService $service): void
    {
        abort_unless(auth()->user()?->can('kardex.output_from_order'), 403);
        $item = OrderStatusItem::findOrFail($this->selectedItemId);
        $service->createKardexOutput($item, $this->quantity, auth()->user());
        $this->refreshStatus('Salida Kardex creada.', true);
    }

    public function registerDeliveryDispatch(OrderStatusService $service): void
    {
        abort_unless(auth()->user()?->can('order_status.manage') || auth()->user()?->can('logistics.upload_document'), 403);
        $item = OrderStatusItem::findOrFail($this->selectedItemId);
        $service->registerDeliveryDispatch($item, $this->quantity, auth()->user(), $this->notes ?: null);
        $this->refreshStatus('Despacho al cliente registrado.', true);
    }

    public function registerDelivery(OrderStatusService $service): void
    {
        abort_unless(auth()->user()?->can('order_status.manage') || auth()->user()?->can('logistics.upload_document'), 403);
        $item = OrderStatusItem::findOrFail($this->selectedItemId);
        $service->registerDelivery($item, $this->quantity, auth()->user(), $this->notes ?: null);
        $this->refreshStatus('Entrega al cliente registrada.', true);
    }

    public function requestSupplierCancellation(): void
    {
        abort_unless(auth()->user()?->can('supplier_cancellation.register'), 403);

        if (! $this->selectedItemId) {
            throw ValidationException::withMessages(['item' => 'Selecciona una partida antes de cancelar.']);
        }

        $item = $this->selectedItem();

        if (in_array($item->status, [OrderStatusService::STATUS_DELIVERED, OrderStatusService::STATUS_CLOSED], true)) {
            throw ValidationException::withMessages(['item' => 'No puedes cancelar por proveedor una partida ya entregada.']);
        }

        $this->cancelSupplierId = $item->supplier_id
            ?: ($item->purchaseOrderItem?->order?->supplier_id ?: null);
        $this->cancelReason = '';
        $this->cancelConfirmation = false;
        $this->showCancelConfirmation = true;
    }

    public function closeSupplierCancellation(): void
    {
        $this->showCancelConfirmation = false;
        $this->cancelReason = '';
        $this->cancelConfirmation = false;
        $this->cancelSupplierId = null;
        $this->resetValidation(['cancelReason', 'cancelConfirmation', 'cancelSupplierId']);
    }

    public function cancelBySupplier(OrderStatusService $service): void
    {
        abort_unless(auth()->user()?->can('supplier_cancellation.register'), 403);

        $this->validate([
            'cancelSupplierId' => ['nullable', 'integer', 'exists:entities,id'],
            'cancelReason' => ['required', 'string', 'min:10', 'max:1000'],
            'cancelConfirmation' => ['accepted'],
        ], [
            'cancelReason.required' => 'Indica el motivo de la cancelación.',
            'cancelReason.min' => 'Explica el motivo con al menos 10 caracteres.',
            'cancelConfirmation.accepted' => 'Debes confirmar la cancelación para proceder.',
        ]);

        $item = OrderStatusItem::findOrFail($this->selectedItemId);

        if ($this->cancelSupplierId && (int) $item->supplier_id !== (int) $this->cancelSupplierId) {
            $item->supplier_id = $this->cancelSupplierId;
            $item->save();
        }

        $reason = $this->cancelReason;
        $service->cancelBySupplier($item, $reason, auth()->user());
        $this->closeSupplierCancellation();
        $this->refreshStatus('Partida cancelada por proveedor.', true);
        $this->showReplacementForm = false;
    }

    public function requestFullOrderCancellation(): void
    {
        abort_unless(auth()->user()?->can('order_status.manage') || auth()->user()?->can('supplier_cancellation.register'), 403);

        if ($this->orderStatus->administrative_status === 'cancelado_total' || $this->orderStatus->saleOrder?->status === 'cancelado') {
            throw ValidationException::withMessages(['order' => 'Esta orden ya se encuentra cancelada en su totalidad.']);
        }

        $this->fullOrderCancelReason = '';
        $this->fullOrderCancelConfirmation = false;
        $this->showFullOrderCancelConfirmation = true;
    }

    public function closeFullOrderCancellation(): void
    {
        $this->showFullOrderCancelConfirmation = false;
        $this->fullOrderCancelReason = '';
        $this->fullOrderCancelConfirmation = false;
        $this->resetValidation(['fullOrderCancelReason', 'fullOrderCancelConfirmation']);
    }

    public function cancelFullOrder(OrderStatusService $service): void
    {
        abort_unless(auth()->user()?->can('order_status.manage') || auth()->user()?->can('supplier_cancellation.register'), 403);

        $this->validate([
            'fullOrderCancelReason' => ['required', 'string', 'min:10', 'max:1000'],
            'fullOrderCancelConfirmation' => ['accepted'],
        ], [
            'fullOrderCancelReason.required' => 'Indica el motivo de cancelación de la orden.',
            'fullOrderCancelReason.min' => 'Explica el motivo con al menos 10 caracteres.',
            'fullOrderCancelConfirmation.accepted' => 'Debes confirmar la cancelación de la orden completa para proceder.',
        ]);

        $reason = $this->fullOrderCancelReason;
        $user = auth()->user();

        DB::transaction(function () use ($service, $reason, $user) {
            $this->orderStatus->loadMissing('items');

            foreach ($this->orderStatus->items as $item) {
                if (! in_array($item->status, [OrderStatusService::STATUS_DELIVERED, OrderStatusService::STATUS_CLOSED, OrderStatusService::STATUS_CANCELLED_SUPPLIER], true)) {
                    $service->cancelBySupplier($item, $reason, $user);
                }
            }

            if ($this->orderStatus->saleOrder) {
                $this->orderStatus->saleOrder->update(['status' => 'cancelado']);
            }

            $service->recordEvent(
                $this->orderStatus,
                null,
                'order.cancelled_total',
                OrderStatusService::PHASE_PURCHASE,
                $this->orderStatus->administrative_status,
                'cancelado_total',
                "Orden cancelada en su totalidad: {$reason}",
                $user
            );

            $service->recalculate($this->orderStatus);
        });

        $this->closeFullOrderCancellation();
        $this->refreshStatus('Orden completa cancelada exitosamente.', true);
    }

    public function requestReplacement(OrderStatusService $service): void
    {
        abort_unless(auth()->user()?->can('replacement.request') || auth()->user()?->can('order_status.manage'), 403);
        $item = OrderStatusItem::findOrFail($this->selectedItemId);
        $this->validate(['replacementReason' => ['required', 'string', 'min:10', 'max:1000']]);
        $service->requestReplacement($item, $this->replacementReason, auth()->user());
        $this->showReplacementForm = false;
        $this->replacementReason = '';
        $this->refreshStatus('Reemplazo solicitado. El cliente verá la actualización en su pedido.', true);
    }

    public function saveReplacementCandidate(OrderStatusService $service): void
    {
        abort_unless(auth()->user()?->can('replacement.request') || auth()->user()?->can('order_status.manage'), 403);

        $this->validate([
            'replacementReason' => ['required', 'string', 'min:10', 'max:1000'],
            'replacementSupplierId' => ['nullable', 'integer', 'exists:entities,id'],
            'replacementSupplierName' => ['required_without:replacementSupplierId', 'nullable', 'string', 'max:255'],
            'replacementSupplierContact' => ['nullable', 'string', 'max:255'],
            'replacementPartName' => ['required', 'string', 'max:255'],
            'replacementPartNumber' => ['nullable', 'string', 'max:255'],
            'replacementQuantity' => ['required', 'integer', 'min:1'],
            'replacementSupplierUnitCost' => ['required', 'numeric', 'min:0'],
            'replacementSupplierCurrency' => ['required', 'in:NIO,USD,EUR,INR'],
            'replacementClientUnitPrice' => ['required', 'numeric', 'min:0'],
            'replacementWarranty' => ['required', 'string', 'max:255'],
            'replacementDocuments' => ['nullable', 'array', 'max:10'],
            'replacementDocuments.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp,xlsx,xls,csv', 'max:10240'],
        ]);

        $item = OrderStatusItem::findOrFail($this->selectedItemId);
        $supplier = filled($this->replacementSupplierId)
            ? Entity::where('type', 'proveedor')->findOrFail($this->replacementSupplierId)
            : Entity::firstOrCreate(
                ['type' => 'proveedor', 'name' => trim($this->replacementSupplierName)],
                ['status' => 'activo', 'contact_name' => trim($this->replacementSupplierContact) ?: null]
            );

        if (filled($this->replacementSupplierContact)) {
            $supplier->forceFill(['contact_name' => trim($this->replacementSupplierContact)])->save();
        }

        $replacement = $service->requestReplacement($item, $this->replacementReason, auth()->user());
        $paths = [];
        foreach ($this->replacementDocuments as $document) {
            $stored = $service->attachDocument($this->orderStatus, $item, OrderStatusService::PHASE_PURCHASE, 'replacement_supplier_document', $document, auth()->user(), 'Propuesta de reemplazo');
            $paths[] = $stored->file_path;
        }

        $replacement->forceFill([
            'new_supplier_id' => $supplier->id,
            'supplier_contact' => trim($this->replacementSupplierContact) ?: $supplier->contact_name,
            'part_name' => trim($this->replacementPartName),
            'part_number' => trim($this->replacementPartNumber) ?: null,
            'quantity' => $this->replacementQuantity,
            'supplier_unit_cost' => $this->replacementSupplierUnitCost,
            'supplier_currency' => $this->replacementSupplierCurrency,
            'client_unit_price' => $this->replacementClientUnitPrice,
            'warranty' => trim($this->replacementWarranty),
            'document_paths' => $paths,
        ])->save();

        $this->showReplacementForm = false;
        $this->resetReplacementCandidateForm();
        $this->refreshStatus('Propuesta de reemplazo registrada.', true);
    }

    private function resetReplacementCandidateForm(): void
    {
        $this->reset(['replacementReason', 'replacementSupplierId', 'replacementSupplierName', 'replacementSupplierContact', 'replacementPartName', 'replacementPartNumber', 'replacementSupplierUnitCost', 'replacementClientUnitPrice', 'replacementDocuments']);
        $this->replacementQuantity = 1;
        $this->replacementSupplierCurrency = 'USD';
        $this->replacementWarranty = 'Sin garantía';
    }

    public function closeReplacementForm(): void
    {
        $this->showReplacementForm = false;
        $this->replacementReason = '';
    }

    public function openReplacementForm(int $itemId): void
    {
        abort_unless(auth()->user()?->can('replacement.request') || auth()->user()?->can('order_status.manage'), 403);

        $item = $this->orderStatus->items->firstWhere('id', $itemId);
        if (! $item || $item->status !== OrderStatusService::STATUS_CANCELLED_SUPPLIER) {
            return;
        }

        $this->selectedItemId = $item->id;
        $this->selectedGroupKey = null;
        $this->replacementReason = $item->part_number
            ? "Reemplazar partida {$item->part_number}: ".($item->cancellation_reason ?: '')
            : ($item->cancellation_reason ?: '');
        $this->showReplacementForm = true;
    }

    private function refreshStatus(string $message, bool $showLocation = false): void
    {
        $this->orderStatus = $this->orderStatus->fresh(['saleOrder.customer', 'items.product', 'items.supplier', 'items.documents', 'items.events.user', 'shipmentBatches.supplier', 'shipmentBatches.items.orderStatusItem', 'documents.uploader', 'documents.generator', 'documents.item', 'purchaseOrderDocuments.purchaseOrder.supplier', 'purchaseOrderDocuments.sender', 'purchaseOrderDocuments.generator', 'purchaseOrderDocuments.approvalRequest', 'events.user']);
        $this->initializePurchaseOrderGroups();
        if ($this->selectedGroupKey && ! isset($this->itemGroups[$this->selectedGroupKey])) {
            $this->selectedGroupKey = null;
        }
        $selectedItem = $this->selectedItemId
            ? $this->orderStatus->items->firstWhere('id', $this->selectedItemId)
            : null;

        $this->activePhase = $selectedItem
            ? $this->phaseForItem($selectedItem)
            : $this->phaseForCurrentStatus();
        $this->documentPhase = $this->activePhase;
        $this->documentType = $this->defaultDocumentTypeForPhase($this->activePhase);
        $this->setDefaultQuantityForPhase($selectedItem);
        session()->flash('message', $showLocation ? $this->statusLocationMessage($message) : $message);
        $this->dispatch('attachmentUploaded');
        $this->dispatch('refreshAttachments');
    }

    private function completePurchasePhase(PurchaseOrderDocumentService $documentService): void
    {
        $purchaseOrderIds = $this->selectedGroupKey
            ? $this->activeGroupItems()->pluck('purchaseOrderItem.purchase_order_id')->filter()->unique()
            : collect([$this->selectedItem()?->purchaseOrderItem?->purchase_order_id])->filter();

        $documents = $this->orderStatus->purchaseOrderDocuments
            ->when(
                $purchaseOrderIds->isNotEmpty(),
                fn ($documents) => $documents->whereIn('purchase_order_id', $purchaseOrderIds)
            )
            ->values();

        if ($documents->isEmpty() || $documents->contains(fn ($document) => blank($document->pdf_path))) {
            $this->assertRequiredPhaseDocument('purchase_order_provider_pdf');

            if ($this->orderStatus->items->contains(fn (OrderStatusItem $item) => in_array($item->status, [OrderStatusService::STATUS_PENDING_PO, OrderStatusService::STATUS_PO_GENERATED], true))) {
                app(OrderStatusService::class)->markProviderPurchaseOrderSent($this->orderStatus, auth()->user(), $this->phaseNotes());
            }

            $this->refreshStatus('OC proveedor validada desde documentos de seguimiento.', true);
            return;
        }

        foreach ($documents->whereNull('sent_to_provider_at') as $document) {
            $documentService->markSentToProvider($document, auth()->user(), $this->phaseNotes());
        }

        $this->refreshStatus('Fase OC / Pedido completada.', true);
    }

    private function completeSupplierConfirmationPhase(OrderStatusService $service): void
    {
        $item = $this->selectedItem();
        $this->assertRequiredPhaseDocument('supplier_confirmation', $item->id);

        $this->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'supplierReference' => ['nullable', 'string', 'max:120'],
            'supplierContact' => ['nullable', 'string', 'max:120'],
            'supplierConfirmedAt' => ['nullable', 'date'],
            'supplierEta' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $service->confirmSupplierItem($item, $this->quantity, auth()->user(), $this->phaseNotes());
        $this->refreshStatus('Confirmacion del proveedor registrada.', true);
    }

    private function completeTransitPhase(OrderStatusService $service): void
    {
        $item = $this->selectedItem();
        $this->assertRequiredPhaseDocument('shipping_document', $item->id);

        $this->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $service->createShipmentBatch(
            $this->orderStatus,
            [
                'supplier_id' => $item->supplier_id,
                'notes' => $this->phaseNotes(),
            ],
            [$item->id => $this->quantity],
            auth()->user()
        );

        $this->refreshStatus('Transito registrado.', true);
    }

    private function completeWarehousePhase(): void
    {
        $item = $this->selectedItem();

        if ((float) $item->quantity_received <= 0 || (float) $item->quantity_kardex_entry <= 0) {
            throw ValidationException::withMessages([
                'phase' => 'La recepcion y entrada Kardex debe registrarla Bodega desde Ordenes por recibir.',
            ]);
        }

        $this->refreshStatus('Recepcion de Bodega validada.', true);
    }

    private function completeItemPhase(OrderStatusService $service, string $documentType, callable $action): void
    {
        $item = $this->selectedItem();
        $this->assertRequiredPhaseDocument($documentType, $item->id);

        $this->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'unitCost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $action($item);
        $this->refreshStatus('Fase completada para la partida seleccionada.', true);
    }

    private function completeActiveGroupPhase(OrderStatusService $service, PurchaseOrderDocumentService $documentService, PreprintedInvoiceService $preprintedInvoices): void
    {
        $items = $this->activeGroupItems();

        if ($items->count() < 2) {
            throw ValidationException::withMessages(['item' => 'El grupo debe tener al menos dos partidas.']);
        }

        if ($items->every(fn (OrderStatusItem $item) => in_array($item->status, [OrderStatusService::STATUS_DELIVERED, OrderStatusService::STATUS_CLOSED], true))) {
            throw ValidationException::withMessages(['phase' => 'Este viaje ya fue entregado y su proceso está culminado.']);
        }

        if ($items->contains(fn (OrderStatusItem $item) => $this->phaseForBoardItem($item) !== $this->activePhase)) {
            throw ValidationException::withMessages(['phase' => 'Las partidas del viaje estan en fases distintas. Sepáralas para avanzar cada una de forma independiente.']);
        }

        if ($this->activePhase === OrderStatusService::PHASE_WAREHOUSE) {
            throw ValidationException::withMessages(['phase' => 'Para recibir en bodega, registra condición, evidencia y costo de cada partida.']);
        }

        if ($this->activePhase === OrderStatusService::PHASE_KARDEX_OUTPUT) {
            $this->completeGroupKardexOutput($items, $service, $preprintedInvoices);
            return;
        }

        $this->attachPendingDocumentForPhase($service);
        $items = $this->activeGroupItems();

        DB::transaction(function () use ($items, $service, $documentService) {
            match ($this->activePhase) {
                OrderStatusService::PHASE_PURCHASE => $this->completePurchasePhase($documentService),
                OrderStatusService::PHASE_SUPPLIER_CONFIRMATION => $this->completeGroupSupplierConfirmation($items, $service),
                OrderStatusService::PHASE_TRANSIT => $this->completeGroupTransit($items, $service),
                OrderStatusService::PHASE_CUSTOMS => $this->completeGroupItemAction($items, $service, 'shipping_document', fn (OrderStatusItem $item, float $quantity) => $service->registerCustomsEntry($item, $quantity, auth()->user(), $this->phaseNotes())),
                OrderStatusService::PHASE_LOGISTICS => $this->completeGroupDispatch($items, $service),
                OrderStatusService::PHASE_DELIVERY => $this->completeGroupItemAction($items, $service, 'delivery_confirmation', fn (OrderStatusItem $item, float $quantity) => $service->registerDelivery($item, $quantity, auth()->user(), $this->phaseNotes())),
                default => null,
            };
        });

        $this->refreshStatus('Fase aplicada a todas las partidas del viaje.', true);
    }

    private function completeGroupSupplierConfirmation($items, OrderStatusService $service): void
    {
        $this->validate(['notes' => ['nullable', 'string', 'max:1000']]);
        foreach ($items as $item) {
            $this->assertRequiredPhaseDocument('supplier_confirmation', $item->id);
            $service->confirmSupplierItem($item, $this->defaultQuantityForPhase($item, $this->activePhase), auth()->user(), $this->phaseNotes());
        }
    }

    private function completeGroupTransit($items, OrderStatusService $service): void
    {
        foreach ($items as $item) {
            $this->assertRequiredPhaseDocument('shipping_document', $item->id);
        }

        $supplierIds = $items->pluck('supplier_id')->filter()->unique();
        $service->createShipmentBatch($this->orderStatus, [
            'supplier_id' => $supplierIds->count() === 1 ? $supplierIds->first() : null,
            'notes' => $this->phaseNotes(),
        ], $items->mapWithKeys(fn (OrderStatusItem $item) => [$item->id => $this->defaultQuantityForPhase($item, $this->activePhase)])->all(), auth()->user());
    }

    public function invoiceCustomData(): array
    {
        return [
            'name' => $this->invoiceCustomerName,
            'ruc' => $this->invoiceCustomerRuc,
            'address' => $this->invoiceCustomerAddress,
            'phone' => $this->invoiceCustomerPhone,
            'issue_date' => $this->invoiceIssueDate,
            'due_date' => $this->invoiceDueDate,
            'condition' => $this->invoiceCondition,
            'seller' => $this->invoiceSeller,
            'apply_iva' => $this->invoiceApplyIva,
            'apply_ir' => $this->invoiceApplyIr,
            'iva_rate' => $this->invoiceIvaRate / 100,
            'ir_rate' => $this->invoiceIrRate / 100,
        ];
    }

    private function completeKardexOutputPhase(OrderStatusService $service, PreprintedInvoiceService $preprintedInvoices): void
    {
        $item = $this->selectedItem();
        $this->validateKardexInvoice([$item]);
        $path = null;
        try {
            DB::transaction(function () use ($item, $service, $preprintedInvoices, &$path) {
                $invoiceNumber = trim($this->dispatchInvoiceNumber) !== ''
                    ? trim($this->dispatchInvoiceNumber)
                    : app(DocumentSequenceService::class)->formattedNext('preprinted_invoice', '', 2, 2);
                $customData = $this->invoiceCustomData();
                $path = $preprintedInvoices->store(
                    $this->orderStatus,
                    [$item->id => $this->quantity],
                    $invoiceNumber,
                    auth()->user()?->name,
                    $customData
                );
                $service->attachDocument(
                    $this->orderStatus,
                    $item,
                    OrderStatusService::PHASE_KARDEX_OUTPUT,
                    'kardex_output_document',
                    $path,
                    auth()->user(),
                    'Factura de talonario generada para la salida Kardex.'
                );
                $service->createKardexOutput($item, $this->quantity, auth()->user(), $path);
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('private')->delete($path);
            }
            throw $e;
        }

        $this->dispatchInvoiceNumber = '';
        $this->refreshStatus('Factura emitida. Continúa con la salida y entrega en bodega.', true);
    }

    private function completeGroupKardexOutput($items, OrderStatusService $service, PreprintedInvoiceService $preprintedInvoices): void
    {
        $this->validateKardexInvoice($items->all());
        $quantities = $items->mapWithKeys(fn (OrderStatusItem $item) => [
            $item->id => $this->defaultQuantityForPhase($item, OrderStatusService::PHASE_KARDEX_OUTPUT),
        ])->all();

        $path = null;
        try {
            DB::transaction(function () use ($items, $quantities, $service, $preprintedInvoices, &$path) {
                $invoiceNumber = trim($this->dispatchInvoiceNumber) !== ''
                    ? trim($this->dispatchInvoiceNumber)
                    : app(DocumentSequenceService::class)->formattedNext('preprinted_invoice', '', 2, 2);
                $customData = $this->invoiceCustomData();
                $path = $preprintedInvoices->store(
                    $this->orderStatus,
                    $quantities,
                    $invoiceNumber,
                    auth()->user()?->name,
                    $customData
                );
                $service->attachDocument(
                    $this->orderStatus,
                    null,
                    OrderStatusService::PHASE_KARDEX_OUTPUT,
                    'kardex_output_document',
                    $path,
                    auth()->user(),
                    'Factura de talonario generada para la salida Kardex agrupada.'
                );
                foreach ($items as $item) {
                    $service->createKardexOutput($item, $quantities[$item->id], auth()->user(), $path);
                }
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('private')->delete($path);
            }
            throw $e;
        }

        $this->dispatchInvoiceNumber = '';
        $this->refreshStatus('Factura del viaje emitida. Continúa con la salida y entrega en bodega.', true);
    }

    /** @param array<int, OrderStatusItem> $items */
    private function validateKardexInvoice(array $items): void
    {
        $this->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        foreach ($items as $item) {
            $entry = (float) $item->quantity_kardex_entry > 0
                ? (float) $item->quantity_kardex_entry
                : ((float) $item->quantity_received > 0 ? (float) $item->quantity_received : (float) $item->quantity_ordered);

            $available = max(0, $entry - (float) $item->quantity_kardex_output);
            if ($available <= 0 && (float) $item->quantity_ordered > 0) {
                $available = (float) $item->quantity_ordered;
            }

            if ($this->quantity > $available && $available > 0) {
                throw ValidationException::withMessages(['quantity' => "La cantidad a facturar supera la cantidad disponible ({$available})."]);
            }
        }
    }

    private function completeGroupItemAction($items, OrderStatusService $service, string $documentType, callable $action): void
    {
        foreach ($items as $item) {
            $this->assertRequiredPhaseDocument($documentType, $item->id);
            $action($item, $this->defaultQuantityForPhase($item, $this->activePhase));
        }
    }

    private function completeGroupDispatch($items, OrderStatusService $service): void
    {
        foreach ($items as $item) {
            $this->assertRequiredPhaseDocument('delivery_logistics_document', $item->id);
        }

        $service->registerDeliveryDispatchBatch($this->orderStatus, $items
            ->mapWithKeys(fn (OrderStatusItem $item) => [$item->id => $this->defaultQuantityForPhase($item, $this->activePhase)])
            ->all(), auth()->user(), $this->phaseNotes());
    }

    private function selectedItem(): OrderStatusItem
    {
        $item = $this->selectedItemId
            ? $this->orderStatus->items->firstWhere('id', $this->selectedItemId)
            : $this->orderStatus->items->first();

        if (! $item) {
            throw ValidationException::withMessages(['item' => 'Selecciona una partida para completar esta fase.']);
        }

        return $item;
    }

    private function phaseNotes(): ?string
    {
        $arr = [];
        if ($this->supplierReference) {
            $arr[] = 'Referencia proveedor: '.$this->supplierReference;
        }
        if ($this->supplierContact) {
            $arr[] = 'Contacto proveedor: '.$this->supplierContact;
        }
        if ($this->supplierConfirmedAt) {
            $arr[] = 'Confirmado el: '.$this->supplierConfirmedAt;
        }
        if ($this->supplierEta) {
            $arr[] = 'ETA proveedor: '.$this->supplierEta;
        }
        if ($this->notes) {
            $arr[] = $this->notes;
        }

        return empty($arr) ? null : implode(' | ', $arr);
    }

    private function statusLocationMessage(string $message): string
    {
        $phaseLabel = $this->phaseDefinitions()[$this->activePhase]['label'] ?? str($this->activePhase)->replace('_', ' ')->headline()->toString();
        $selectedItem = $this->selectedItemId
            ? $this->orderStatus->items->firstWhere('id', $this->selectedItemId)
            : null;

        if ($selectedItem) {
            $itemLabel = $selectedItem->part_number ?: ($selectedItem->part_name ?: 'Partida #'.$selectedItem->id);

            return "{$message} Avanzado a: {$phaseLabel}. Partida actual: {$itemLabel} - ".app(OrderStatusService::class)->humanStatus($selectedItem->status).'.';
        }

        return "{$message} Avanzado a: {$phaseLabel}. Estado general: ".str($this->orderStatus->administrative_status ?? '')->replace('_', ' ')->headline()->toString().'.';
    }

    private function generateProgressMessage(string $phaseLabel, ?string $phaseName = null): string
    {
        $message = $this->selectedItemId
            ? "Producto PN: {$this->orderStatus->items->firstWhere('id', $this->selectedItemId)?->part_number}."
            : ($this->selectedGroupKey ? "Grupo: {$this->itemGroups[$this->selectedGroupKey]['label']}." : 'Todos los productos.');

        return "{$message} Avanzado a: {$phaseLabel}. Estado general: ".str($this->orderStatus->administrative_status ?? '')->replace('_', ' ')->headline()->toString().'.';
    }

    public function selectedGroupItems(): \Illuminate\Support\Collection
    {
        return $this->selectedGroupKey && isset($this->itemGroups[$this->selectedGroupKey])
            ? $this->orderStatus->items->whereIn('id', $this->itemGroups[$this->selectedGroupKey]['item_ids'])->values()
            : collect();
    }

    public function previousStageInfo(): ?array
    {
        return match ($this->activePhase) {
            OrderStatusService::PHASE_DELIVERY, OrderStatusService::PHASE_LOGISTICS => [
                'label' => 'Facturación',
                'target_phase' => OrderStatusService::PHASE_KARDEX_OUTPUT,
                'target_status' => OrderStatusService::STATUS_AVAILABLE_FOR_DISPATCH,
            ],
            OrderStatusService::PHASE_KARDEX_OUTPUT => [
                'label' => 'Bodega',
                'target_phase' => OrderStatusService::PHASE_WAREHOUSE,
                'target_status' => OrderStatusService::STATUS_IN_CUSTOMS,
            ],
            OrderStatusService::PHASE_WAREHOUSE, OrderStatusService::PHASE_CUSTOMS => [
                'label' => 'Tránsito / Aduana',
                'target_phase' => OrderStatusService::PHASE_TRANSIT,
                'target_status' => OrderStatusService::STATUS_IN_TRANSIT,
            ],
            OrderStatusService::PHASE_TRANSIT => [
                'label' => 'Confirmación Proveedor',
                'target_phase' => OrderStatusService::PHASE_SUPPLIER_CONFIRMATION,
                'target_status' => OrderStatusService::STATUS_SUPPLIER_CONFIRMED,
            ],
            OrderStatusService::PHASE_SUPPLIER_CONFIRMATION => [
                'label' => 'OC / Compra',
                'target_phase' => OrderStatusService::PHASE_PURCHASE,
                'target_status' => OrderStatusService::STATUS_PO_GENERATED,
            ],
            default => null,
        };
    }

    public function revertStage(OrderStatusService $service): void
    {
        abort_unless(
            auth()->user()?->can('order_status.manage')
            || auth()->user()?->can('purchase_order_provider.edit')
            || auth()->user()?->can('purchase_order_document.edit')
            || auth()->user()?->can('warehouse_receipt.register')
            || auth()->user()?->can('supplier_confirmation.upload')
            || auth()->user()?->can('shipment.upload')
            || auth()->user()?->can('kardex.output_from_order')
            || auth()->user()?->can('logistics.upload_document'),
            403
        );

        $previous = $this->previousStageInfo();
        if (! $previous) {
            $this->dispatch('notify', ['type' => 'warning', 'message' => 'Esta es la primera etapa o no admite un retroceso.']);

            return;
        }

        DB::transaction(function () use ($previous, $service) {
            if ($this->selectedGroupKey) {
                $groupItems = $this->selectedGroupItems();
                foreach ($groupItems as $item) {
                    $oldStatus = $item->status;
                    $item->status = $previous['target_status'];
                    $item->save();
                    $service->recordEvent(
                        $this->orderStatus,
                        $item,
                        'phase.reverted',
                        $previous['target_phase'],
                        $oldStatus,
                        $previous['target_status'],
                        "Fase revertida a {$previous['label']} por ".auth()->user()?->name,
                        auth()->user(),
                        $this->notes ?: null
                    );
                }
            } else {
                $item = $this->selectedItem();
                $oldStatus = $item->status;
                $item->status = $previous['target_status'];
                $item->save();
                $service->recordEvent(
                    $this->orderStatus,
                    $item,
                    'phase.reverted',
                    $previous['target_phase'],
                    $oldStatus,
                    $previous['target_status'],
                    "Fase revertida a {$previous['label']} por ".auth()->user()?->name,
                    auth()->user(),
                    $this->notes ?: null
                );
            }

            $service->recalculate($this->orderStatus);
        });

        $this->activePhase = $previous['target_phase'];
        $this->refreshStatus("Etapa cambiada a: {$previous['label']}.", true);
    }
}
