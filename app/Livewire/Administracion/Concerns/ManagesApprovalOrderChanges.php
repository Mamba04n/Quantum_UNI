<?php

namespace App\Livewire\Administracion\Concerns;

use App\Models\ApprovalRequest;
use App\Models\Entity;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchasePreparationItem;
use App\Models\SupplierBidResponse;
use App\Models\SupplierBidResponseItem;
use App\Services\OrderStatusService;
use App\Services\OrderTrackerService;
use App\Services\PurchasePreparationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

trait ManagesApprovalOrderChanges
{
    public function setTab(string $tab): void
    {
        if (! in_array($tab, ['summary', 'products', 'supplier', 'documents', 'timeline'], true)) {
            return;
        }

        $this->activeTab = $tab;
        $this->editingPurchasePreparationItemId = null;
    }

    public function startEditingOrder(int $purchasePreparationItemId): void
    {
        $approval = $this->selectedApproval();
        $item = PurchasePreparationItem::find($purchasePreparationItemId);

        if (! $item || $item->quotation_request_id !== $approval->source_id) {
            session()->flash('error', 'El item no pertenece a esta aprobacion.');
            return;
        }

        $this->activeTab = 'products';
        $this->editingPurchasePreparationItemId = $purchasePreparationItemId;
    }

    public function stopEditingOrder(): void
    {
        $this->editingPurchasePreparationItemId = null;
    }

    public function requestChangeFinalSupplier(int $purchasePreparationItemId): void
    {
        $this->queueOrderChangeConfirmation($purchasePreparationItemId, 'existing_quote');
    }

    public function requestAddManualSupplierAndChange(int $purchasePreparationItemId): void
    {
        $this->queueOrderChangeConfirmation($purchasePreparationItemId, 'manual_supplier');
    }

    public function closeOrderChangeConfirmation(): void
    {
        $this->showOrderChangeConfirmation = false;
        $this->pendingOrderChangeType = '';
        $this->pendingOrderChangeItemId = null;
    }

    public function confirmPendingOrderChange(PurchasePreparationService $service): void
    {
        abort_unless($this->pendingOrderChangeItemId, 404);

        if ($this->pendingOrderChangeType === 'existing_quote') {
            $this->changeFinalSupplier($this->pendingOrderChangeItemId, $service);
        } elseif ($this->pendingOrderChangeType === 'manual_supplier') {
            $this->addManualSupplierAndChange($this->pendingOrderChangeItemId, $service);
        }

        $this->closeOrderChangeConfirmation();
    }

    public function changeFinalSupplier(int $purchasePreparationItemId, PurchasePreparationService $service): void
    {
        abort_unless($this->canAny(['internal_approval.approve', 'approval.approve', 'order_approval.approve', 'purchase_order.generate']), 403);

        $approval = $this->selectedApproval();
        $quoteId = (int) ($this->selectedFinalSupplierQuote[$purchasePreparationItemId] ?? 0);
        $item = PurchasePreparationItem::with(['quotationRequest', 'quotationItem'])->find($purchasePreparationItemId);
        $supplierQuote = SupplierBidResponseItem::with('response.supplier')->find($quoteId);

        if (! $item || ! $supplierQuote || $item->quotation_request_id !== $approval->source_id) {
            session()->flash('error', 'Seleccione una cotizacion proveedor valida para esta aprobacion.');
            return;
        }

        try {
            DB::transaction(function () use ($approval, $item, $supplierQuote, $service) {
                $service->changeFinalSupplier($item, $supplierQuote, auth()->user(), $this->changeSupplierReason);
                $this->syncGeneratedPurchaseOrderLine($approval, $item->fresh(), $supplierQuote);
            });

            $this->changeSupplierReason = '';
            unset($this->selectedFinalSupplierQuote[$purchasePreparationItemId]);
            $this->editingPurchasePreparationItemId = null;
            session()->flash('message', 'Proveedor final actualizado en preparacion y OC generada.');
        } catch (ValidationException $e) {
            session()->flash('error', collect($e->errors())->flatten()->first());
        } catch (\Throwable $e) {
            report($e);
            session()->flash('error', 'No se pudo cambiar el proveedor final. Revise la OC e intente nuevamente.');
        }
    }

    public function addManualSupplierAndChange(int $purchasePreparationItemId, PurchasePreparationService $service): void
    {
        abort_unless($this->canAny(['internal_approval.approve', 'approval.approve', 'order_approval.approve', 'purchase_order.generate']), 403);

        $approval = $this->selectedApproval();
        $prepItem = PurchasePreparationItem::with(['quotationRequest', 'quotationItem'])->find($purchasePreparationItemId);

        if (! $prepItem || $prepItem->quotation_request_id !== $approval->source_id) {
            session()->flash('error', 'El item no pertenece a esta aprobacion.');
            return;
        }

        $supplierId = (int) ($this->manualSupplierId[$purchasePreparationItemId] ?? 0);
        $supplierName = trim((string) ($this->manualSupplierName[$purchasePreparationItemId] ?? ''));
        $unitCost = (float) ($this->manualSupplierCost[$purchasePreparationItemId] ?? 0);
        $currency = strtoupper(trim((string) ($this->manualSupplierCurrency[$purchasePreparationItemId] ?? 'USD'))) ?: 'USD';
        $delivery = trim((string) ($this->manualSupplierDelivery[$purchasePreparationItemId] ?? ''));

        if (($supplierId <= 0 && blank($supplierName)) || $unitCost <= 0 || blank(trim($this->changeSupplierReason))) {
            session()->flash('error', 'Seleccione o escriba proveedor, indique precio mayor a cero y registre el motivo.');
            return;
        }

        $supplier = $supplierId > 0
            ? Entity::where('type', 'proveedor')->where('status', '!=', 'inactivo')->find($supplierId)
            : Entity::firstOrCreate(
                [
                    'type' => 'proveedor',
                    'name' => $supplierName,
                ],
                [
                    'status' => 'activo',
                    'contact_info' => 'Creado desde aprobacion administrativa',
                ]
            );

        if (! $supplier) {
            session()->flash('error', 'El proveedor seleccionado no esta disponible.');
            return;
        }

        try {
            DB::transaction(function () use ($approval, $prepItem, $supplier, $unitCost, $currency, $delivery, $service) {
                $supplierQuote = $this->createManualSupplierQuote($approval, $prepItem, $supplier, $unitCost, $currency, $delivery);

                $service->changeFinalSupplier($prepItem, $supplierQuote, auth()->user(), $this->changeSupplierReason);
                $this->syncGeneratedPurchaseOrderLine($approval, $prepItem->fresh(), $supplierQuote);
            });

            unset(
                $this->manualSupplierId[$purchasePreparationItemId],
                $this->manualSupplierName[$purchasePreparationItemId],
                $this->manualSupplierCost[$purchasePreparationItemId],
                $this->manualSupplierCurrency[$purchasePreparationItemId],
                $this->manualSupplierDelivery[$purchasePreparationItemId],
                $this->selectedFinalSupplierQuote[$purchasePreparationItemId],
            );
            $this->changeSupplierReason = '';
            $this->editingPurchasePreparationItemId = null;

            session()->flash('message', 'Proveedor manual agregado y OC actualizada.');
        } catch (ValidationException $e) {
            session()->flash('error', collect($e->errors())->flatten()->first());
        } catch (\Throwable $e) {
            report($e);
            session()->flash('error', 'No se pudo agregar el proveedor manual a la OC.');
        }
    }

    private function queueOrderChangeConfirmation(int $purchasePreparationItemId, string $type): void
    {
        abort_unless($this->canAny(['internal_approval.approve', 'approval.approve', 'order_approval.approve', 'purchase_order.generate']), 403);

        $approval = $this->selectedApproval();
        $item = PurchasePreparationItem::find($purchasePreparationItemId);

        if (! $item || $item->quotation_request_id !== $approval->source_id) {
            session()->flash('error', 'El item no pertenece a esta aprobacion.');
            return;
        }

        $this->pendingOrderChangeItemId = $purchasePreparationItemId;
        $this->pendingOrderChangeType = $type;
        $this->showOrderChangeConfirmation = true;
    }

    private function createManualSupplierQuote(
        ApprovalRequest $approval,
        PurchasePreparationItem $prepItem,
        Entity $supplier,
        float $unitCost,
        string $currency,
        string $delivery
    ): SupplierBidResponseItem {
        $quoteItem = $prepItem->quotationItem;

        $response = SupplierBidResponse::create([
            'quotation_request_id' => $approval->source_id,
            'supplier_id' => $supplier->id,
            'contact_name' => $supplier->contact_name,
            'currency' => $currency,
            'general_delivery_time' => $delivery ?: null,
            'status' => 'received',
            'created_by' => auth()->id(),
            'notes' => 'Cotizacion agregada manualmente desde aprobacion administrativa.',
        ]);

        return SupplierBidResponseItem::create([
            'supplier_bid_response_id' => $response->id,
            'quotation_request_item_id' => $prepItem->quotation_request_item_id,
            'quoted_part_number' => $quoteItem?->part_number,
            'quoted_description' => $quoteItem?->part_name ?: $quoteItem?->description,
            'quoted_model' => $quoteItem?->model,
            'quoted_brand' => $quoteItem?->brand,
            'quoted_quantity' => max(0.01, (float) $prepItem->quantity_to_purchase),
            'unit_cost' => $unitCost,
            'currency' => $currency,
            'delivery_time' => $delivery ?: null,
            'item_status' => 'quoted',
            'internal_comment' => 'Agregado manualmente desde aprobacion administrativa.',
        ]);
    }

    private function syncGeneratedPurchaseOrderLine(ApprovalRequest $approval, PurchasePreparationItem $prepItem, SupplierBidResponseItem $supplierQuote): void
    {
        if (! Schema::hasColumn('purchase_orders', 'approval_request_id')) {
            return;
        }

        $line = PurchaseOrderItem::with('order.items')
            ->whereHas('order', fn ($order) => $order->where('approval_request_id', $approval->id))
            ->where('quotation_request_item_id', $prepItem->quotation_request_item_id)
            ->lockForUpdate()
            ->first();

        if (! $line) {
            return;
        }

        $newSupplierId = (int) $supplierQuote->response->supplier_id;
        $oldOrder = $line->order;
        $targetOrder = $oldOrder;

        if ((int) $oldOrder->supplier_id !== $newSupplierId) {
            if ($oldOrder->items->count() === 1) {
                $oldOrder->forceFill([
                    'supplier_id' => $newSupplierId,
                    'supplier_bid_response_id' => $supplierQuote->supplier_bid_response_id,
                    'currency' => $supplierQuote->currency ?: $oldOrder->currency,
                ])->save();
            } else {
                $targetOrder = PurchaseOrder::firstOrCreate(
                    [
                        'approval_request_id' => $approval->id,
                        'supplier_id' => $newSupplierId,
                    ],
                    [
                        'quotation_request_id' => $oldOrder->quotation_request_id,
                        'sale_order_id' => $oldOrder->sale_order_id,
                        'supplier_bid_response_id' => $supplierQuote->supplier_bid_response_id,
                        'reference_number' => $this->nextPurchaseOrderReference($newSupplierId),
                        'status' => $oldOrder->status,
                        'source_type' => $oldOrder->source_type,
                        'total_fob' => 0,
                        'currency' => $supplierQuote->currency ?: $oldOrder->currency,
                        'order_date' => $oldOrder->order_date,
                        'expected_date' => $oldOrder->expected_date,
                        'generated_by' => auth()->id(),
                        'generated_at' => now(),
                        'notes' => 'Creada por cambio de proveedor desde aprobacion administrativa.',
                    ]
                );

                $line->forceFill(['purchase_order_id' => $targetOrder->id])->save();
            }
        }

        $quantity = (float) $prepItem->quantity_to_purchase;
        $unitCost = (float) $supplierQuote->unit_cost;

        $line->forceFill([
            'supplier_bid_response_item_id' => $supplierQuote->id,
            'part_name' => $supplierQuote->quoted_description ?: $line->part_name,
            'part_number' => $supplierQuote->quoted_part_number ?: $line->part_number,
            'brand' => $supplierQuote->quoted_brand ?: $line->brand,
            'model' => $supplierQuote->quoted_model ?: $line->model,
            'quantity' => $quantity,
            'unit_price' => $unitCost,
            'total_price' => round($quantity * $unitCost, 2),
            'currency' => $supplierQuote->currency ?: $line->currency,
            'expected_date' => $supplierQuote->estimated_arrival_date ?: $line->expected_date,
            'notes' => $prepItem->change_reason,
        ])->save();

        $this->recalculatePurchaseOrderTotal($oldOrder->fresh('items'));
        if ($targetOrder->id !== $oldOrder->id) {
            $this->recalculatePurchaseOrderTotal($targetOrder->fresh('items'));
        }

        app(OrderStatusService::class)->syncPurchaseOrder($targetOrder->fresh('items'), auth()->user());
        if ($targetOrder->id !== $oldOrder->id) {
            app(OrderStatusService::class)->syncPurchaseOrder($oldOrder->fresh('items'), auth()->user());
        }

        $tracker = $approval->tracker ?: ($approval->quotationRequest ? app(OrderTrackerService::class)->trackerForQuotation($approval->quotationRequest) : null);
        if ($tracker) {
            app(OrderTrackerService::class)->recordActivity(
                $tracker,
                'purchase_order.admin_changes_applied',
                'Cambios administrativos aplicados a la orden de compra y sincronizados con Order Status.',
                'aprobacion_interna',
                'purchase_preparation_items',
                $prepItem->id,
                null,
                $targetOrder->status,
                auth()->user()
            );
        }
    }

    private function supplierOptions(): Collection
    {
        return Entity::where('type', 'proveedor')
            ->where('status', '!=', 'inactivo')
            ->orderBy('name')
            ->limit(150)
            ->get();
    }

    private function recalculatePurchaseOrderTotal(PurchaseOrder $order): void
    {
        $order->forceFill([
            'total_fob' => round((float) $order->items->sum('total_price'), 2),
        ])->save();
    }

    private function nextPurchaseOrderReference(int|string $supplierId): string
    {
        $next = PurchaseOrder::count() + 1;
        $supplierCode = str_pad((string) $supplierId, 3, '0', STR_PAD_LEFT);

        do {
            $reference = 'OC-'.$supplierCode.'-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            $next++;
        } while (PurchaseOrder::where('reference_number', $reference)->exists());

        return $reference;
    }
}
