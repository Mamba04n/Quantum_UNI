<?php

namespace App\Livewire\Pedidos\Seguimiento\Concerns;

use App\Domain\Pedidos\OrderStatus\Support\OrderStatusStateCatalog;
use App\Models\OrderStatusItem;
use App\Services\OrderStatusService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

trait GestionaTableroSeguimiento
{
    public string $viewMode = 'list';

    public function selectItem(int $itemId): void
    {
        $item = $this->orderStatus->items->firstWhere('id', $itemId);
        if (! $item || ! $this->isActiveTravelItem($item)) {
            return;
        }

        $this->selectedItemId = $itemId;
        $this->selectedGroupKey = null;

        if ($item) {
            $this->activePhase = $this->phaseForItem($item);
            $this->documentPhase = $this->activePhase;
            $this->documentType = $this->defaultDocumentTypeForPhase($this->activePhase);
            $this->setDefaultQuantityForPhase($item);
        }
    }

    public function backToKanban(): void
    {
        $this->selectedItemId = null;
        $this->selectedGroupKey = null;
    }

    public function openGroup(string $groupKey): void
    {
        if (! isset($this->itemGroups[$groupKey])) {
            return;
        }

        $this->selectedItemId = null;
        $this->selectedGroupKey = $groupKey;

        $firstItem = $this->orderStatus->items->firstWhere('id', $this->itemGroups[$groupKey]['item_ids'][0] ?? null);
        if ($firstItem) {
            $this->activePhase = $this->phaseForItem($firstItem);
            $this->documentPhase = $this->activePhase;
            $this->documentType = $this->defaultDocumentTypeForPhase($this->activePhase);
            $this->setDefaultQuantityForPhase($firstItem);
        }
    }

    public function toggleGroupItem(int $itemId): void
    {
        $item = $this->orderStatus->items->firstWhere('id', $itemId);
        if (! $item || ! $this->isActiveTravelItem($item)) {
            return;
        }

        if (in_array($itemId, $this->groupedItemIds(), true)) {
            return;
        }

        if (in_array($itemId, $this->selectedGroupItemIds, true)) {
            $this->selectedGroupItemIds = array_values(array_diff($this->selectedGroupItemIds, [$itemId]));
            return;
        }

        $this->selectedGroupItemIds[] = $itemId;
    }

    public function clearGroupSelection(): void
    {
        $this->selectedGroupItemIds = [];
    }

    public function selectGroupForTravel(string $groupKey): void
    {
        if (! isset($this->itemGroups[$groupKey])) {
            return;
        }

        $itemIds = collect($this->itemGroups[$groupKey]['item_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => ($item = $this->orderStatus->items->firstWhere('id', $id)) && $this->isActiveTravelItem($item))
            ->values();
        $items = $this->orderStatus->items->whereIn('id', $itemIds);

        if ($items->map(fn (OrderStatusItem $item) => $this->phaseForItem($item))->unique()->count() > 1) {
            throw ValidationException::withMessages([
                'group' => 'Este grupo contiene piezas en fases distintas. Ábrelo para revisar sus avances antes de crear un nuevo viaje.',
            ]);
        }

        // Una OC automática deja de ser un bloque para que sus piezas puedan
        // combinarse con otras en un viaje físico real.
        $this->ungroupItems($groupKey);
        $this->selectedGroupItemIds = collect($this->selectedGroupItemIds)
            ->merge($itemIds)
            ->unique()
            ->values()
            ->all();
    }

    public function createItemGroup(): void
    {
        $itemIds = collect($this->selectedGroupItemIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => ($item = $this->orderStatus->items->firstWhere('id', $id)) && $this->isActiveTravelItem($item))
            ->reject(fn ($id) => in_array($id, $this->groupedItemIds(), true))
            ->unique()
            ->values();

        if ($itemIds->count() < 2) {
            return;
        }

        $items = $this->orderStatus->items->whereIn('id', $itemIds);

        if ($items->map(fn (OrderStatusItem $item) => $this->phaseForItem($item))->unique()->count() > 1) {
            throw ValidationException::withMessages([
                'group' => 'Solo puedes agrupar piezas que estén en la misma fase. Avánzalas individualmente hasta igualarlas o crea el viaje antes de iniciar el seguimiento.',
            ]);
        }

        $supplierNames = $items->pluck('supplier.name')->filter()->unique()->values();
        $key = 'viaje_'.$this->groupSequence++;

        $this->itemGroups[$key] = [
            'label' => 'Viaje '.($this->groupSequence - 1),
            'item_ids' => $itemIds->all(),
            'supplier_label' => $supplierNames->count() === 1 ? $supplierNames->first() : 'Varios proveedores',
            'manual' => true,
        ];

        $this->selectedGroupItemIds = [];
        $this->persistTrackingGroups();
        $this->openGroup($key);
    }

    public function ungroupItems(string $groupKey): void
    {
        $purchaseOrderId = $this->itemGroups[$groupKey]['purchase_order_id'] ?? null;
        if ($purchaseOrderId) {
            $this->ungroupedPurchaseOrderIds[] = (int) $purchaseOrderId;
            $this->ungroupedPurchaseOrderIds = array_values(array_unique($this->ungroupedPurchaseOrderIds));
        }

        unset($this->itemGroups[$groupKey]);
        $this->persistTrackingGroups();

        if ($this->selectedGroupKey === $groupKey) {
            $this->selectedGroupKey = null;
        }
    }

    private function initializePurchaseOrderGroups(): void
    {
        $saved = $this->orderStatus->tracking_groups ?: [];
        $activeItemIds = $this->orderStatus->items
            ->filter(fn (OrderStatusItem $item) => $this->isActiveTravelItem($item))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->itemGroups = collect($saved['manual_groups'] ?? [])
            ->filter(fn ($group) => ! empty($group['item_ids']))
            ->map(function ($group) use ($activeItemIds) {
                $group['item_ids'] = collect($group['item_ids'])
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => in_array($id, $activeItemIds, true))
                    ->values()
                    ->all();
                $group['manual'] = true;

                return $group;
            })
            ->filter(fn ($group) => count($group['item_ids']) > 1)
            ->all();
        $this->ungroupedPurchaseOrderIds = collect($saved['ungrouped_purchase_order_ids'] ?? [])
            ->map(fn ($id) => (int) $id)->unique()->values()->all();
        $this->groupSequence = max(1, (int) ($saved['group_sequence'] ?? 1));

        $groups = $this->orderStatus->items
            ->filter(fn (OrderStatusItem $item) => $this->isActiveTravelItem($item) && $item->purchase_order_item_id && $item->purchaseOrderItem?->purchase_order_id)
            ->groupBy(fn (OrderStatusItem $item) => $item->purchaseOrderItem->purchase_order_id)
            ->filter(fn ($items, $purchaseOrderId) => $items->count() > 1 && ! in_array((int) $purchaseOrderId, $this->ungroupedPurchaseOrderIds, true));

        foreach ($groups as $purchaseOrderId => $items) {
            $purchaseOrder = $items->first()?->purchaseOrderItem?->order;
            if (! $purchaseOrder) {
                continue;
            }

            $key = 'oc_'.$purchaseOrderId;
            $this->itemGroups[$key] = [
                'label' => 'OC '.$purchaseOrder->reference_number,
                'item_ids' => $items->pluck('id')->map(fn ($id) => (int) $id)->all(),
                'supplier_label' => $purchaseOrder->supplier?->name ?: 'Proveedor pendiente',
                'purchase_order_id' => (int) $purchaseOrderId,
            ];
        }
    }

    public function setPhase(string $phase): void
    {
        if (! array_key_exists($phase, $this->phaseDefinitions())) {
            return;
        }

        $this->activePhase = $phase;
        $this->documentPhase = $phase;
        $this->documentType = $this->defaultDocumentTypeForPhase($phase);
        $this->setDefaultQuantityForPhase(null, $phase);
    }

    private function visibleItems()
    {
        $terms = collect(preg_split('/\s+/', $this->normalizedSearchText($this->itemSearch), -1, PREG_SPLIT_NO_EMPTY));

        return $this->orderStatus->items
            ->filter(function (OrderStatusItem $item) use ($terms) {
                if ($terms->isEmpty()) {
                    return true;
                }

                $haystack = $this->searchableItemText($item);

                return $terms->every(fn (string $term) => str_contains($haystack, $term));
            })
            ->filter(fn (OrderStatusItem $item) => match ($this->itemFilter) {
                'pending' => (float) $item->quantity_received <= 0 && ! in_array($item->status, [OrderStatusService::STATUS_CANCELLED_SUPPLIER], true),
                'transit' => in_array($item->status, [OrderStatusService::STATUS_IN_TRANSIT, OrderStatusService::STATUS_IN_CUSTOMS, OrderStatusService::STATUS_CUSTOMS_RELEASED], true),
                'warehouse' => in_array($item->status, [OrderStatusService::STATUS_RECEIVED_WAREHOUSE_PARTIAL, OrderStatusService::STATUS_RECEIVED_WAREHOUSE, OrderStatusService::STATUS_AVAILABLE_FOR_DISPATCH, OrderStatusService::STATUS_WAITING_OTHER_ITEMS, OrderStatusService::STATUS_HELD_BY_ADMIN], true),
                'received' => (float) $item->quantity_received > 0,
                'ready' => (float) $item->quantity_kardex_entry > (float) $item->quantity_kardex_output,
                'delivered' => in_array($item->status, [OrderStatusService::STATUS_DELIVERED, OrderStatusService::STATUS_DELIVERED_PARTIAL, OrderStatusService::STATUS_CLOSED], true),
                'cancelled' => $item->status === OrderStatusService::STATUS_CANCELLED_SUPPLIER,
                'incident' => in_array($item->status, [OrderStatusService::STATUS_CANCELLED_SUPPLIER, OrderStatusService::STATUS_REPLACEMENT_REQUIRED], true),
                default => true,
            })
            ->values();
    }

    public function phaseForBoardItem(OrderStatusItem $item): string
    {
        return $this->phaseForItem($item);
    }

    private function groupedItemIds(): array
    {
        return collect($this->itemGroups)
            ->pluck('item_ids')
            ->flatten()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    protected function activeGroupItems()
    {
        if (! $this->selectedGroupKey || ! isset($this->itemGroups[$this->selectedGroupKey])) {
            return collect();
        }

        return $this->orderStatus->items
            ->whereIn('id', $this->itemGroups[$this->selectedGroupKey]['item_ids'] ?? [])
            ->filter(fn (OrderStatusItem $item) => $this->isActiveTravelItem($item))
            ->values();
    }

    private function isActiveTravelItem(OrderStatusItem $item): bool
    {
        return $item->status !== OrderStatusService::STATUS_CANCELLED_SUPPLIER;
    }

    private function persistTrackingGroups(): void
    {
        $manualGroups = collect($this->itemGroups)
            ->filter(fn ($group) => (bool) ($group['manual'] ?? false))
            ->all();

        $this->orderStatus->forceFill([
            'tracking_groups' => [
                'manual_groups' => $manualGroups,
                'ungrouped_purchase_order_ids' => $this->ungroupedPurchaseOrderIds,
                'group_sequence' => $this->groupSequence,
            ],
        ])->save();
    }

    private function phaseForItem(OrderStatusItem $item): string
    {
        $phase = app(OrderStatusStateCatalog::class)->phaseForItemStatus($item->status);

        return $phase && array_key_exists($phase, $this->phaseDefinitions())
            ? $phase
            : (array_key_exists($this->orderStatus->administrative_phase, $this->phaseDefinitions())
                ? $this->orderStatus->administrative_phase
                : OrderStatusService::PHASE_PURCHASE);
    }

    protected function setDefaultQuantityForPhase(?OrderStatusItem $item = null, ?string $phase = null): void
    {
        $item ??= $this->activeDetailItem();

        if (! $item) {
            $this->quantity = 1;
            return;
        }

        $this->quantity = $this->defaultQuantityForPhase($item, $phase ?? $this->activePhase);

        if (($phase ?? $this->activePhase) === OrderStatusService::PHASE_KARDEX_OUTPUT) {
            $this->initInvoiceFormData($item);
        }
    }

    public function initInvoiceFormData(?OrderStatusItem $item = null): void
    {
        $order = $this->orderStatus->saleOrder;
        $customer = $order?->customer;

        $this->invoiceCustomerName = $this->invoiceCustomerName ?: ($customer?->legal_name ?: $customer?->name ?: $order?->client_name ?: 'Cliente');
        $this->invoiceCustomerRuc = $this->invoiceCustomerRuc ?: ($customer?->rfc_ruc ?: $order?->client_ruc ?: '');
        $this->invoiceCustomerAddress = $this->invoiceCustomerAddress ?: ($customer?->exact_address ?: $customer?->address ?: $order?->shipping_address ?: '');
        $this->invoiceCustomerPhone = $this->invoiceCustomerPhone ?: ($customer?->phone_mobile ?: $customer?->phone_home ?: '');
        $this->invoiceIssueDate = $this->invoiceIssueDate ?: now()->format('Y-m-d');
        $this->invoiceDueDate = $this->invoiceDueDate ?: ($order?->payment_type === 'Credito' || $order?->payment_type === 'Crédito' ? now()->addDays(30)->format('Y-m-d') : now()->format('Y-m-d'));
        $this->invoiceCondition = $this->invoiceCondition ?: ($order?->payment_type === 'Credito' || $order?->payment_type === 'Crédito' ? 'credito' : 'contado');
        $this->invoiceSeller = $this->invoiceSeller ?: (auth()->user()?->name ?: 'T01');

        if (blank($this->dispatchInvoiceNumber)) {
            $this->dispatchInvoiceNumber = app(\App\Services\DocumentSequenceService::class)
                ->formattedPeek('preprinted_invoice', '', 2, 2);
        }
    }

    protected function defaultQuantityForPhase(OrderStatusItem $item, string $phase): int
    {
        $quantity = match ($phase) {
            OrderStatusService::PHASE_SUPPLIER_CONFIRMATION => (float) $item->quantity_ordered - (float) $item->quantity_confirmed - (float) $item->quantity_cancelled,
            OrderStatusService::PHASE_TRANSIT => (float) $item->quantity_confirmed - (float) $item->quantity_shipped,
            OrderStatusService::PHASE_CUSTOMS => (float) $item->quantity_shipped - (float) $item->quantity_in_customs,
            OrderStatusService::PHASE_WAREHOUSE => ((float) $item->quantity_shipped > 0 ? (float) $item->quantity_shipped : (float) $item->quantity_ordered) - (float) $item->quantity_received,
            OrderStatusService::PHASE_KARDEX_OUTPUT => (float) $item->quantity_kardex_entry - (float) $item->quantity_kardex_output,
            OrderStatusService::PHASE_LOGISTICS => (float) $item->quantity_kardex_output - (float) $item->quantity_dispatched,
            OrderStatusService::PHASE_DELIVERY => (float) $item->quantity_dispatched - (float) $item->quantity_delivered,
            default => (float) $item->quantity_ordered,
        };

        return max(1, (int) ceil($quantity));
    }

    private function activeDetailItem(): ?OrderStatusItem
    {
        if ($this->selectedItemId) {
            return $this->orderStatus->items->firstWhere('id', $this->selectedItemId);
        }

        if ($this->selectedGroupKey && isset($this->itemGroups[$this->selectedGroupKey])) {
            return $this->orderStatus->items->firstWhere('id', $this->itemGroups[$this->selectedGroupKey]['item_ids'][0] ?? null);
        }

        return $this->orderStatus->items->first();
    }

    private function searchableItemText(OrderStatusItem $item): string
    {
        $phase = $this->phaseForItem($item);

        return $this->normalizedSearchText(collect([
            $item->part_name,
            $item->part_number,
            $item->brand,
            $item->model,
            $item->supplier?->name,
            $item->product?->name,
            $item->product?->sku,
            $item->product?->part_number,
            $item->status,
            app(OrderStatusService::class)->humanStatus($item->status),
            $this->phaseDefinitions()[$phase]['label'] ?? null,
        ])->filter()->implode(' '));
    }

    private function normalizedSearchText(?string $value): string
    {
        return Str::of($value ?? '')
            ->ascii()
            ->lower()
            ->squish()
            ->toString();
    }

    private function phaseDefinitions(): array
    {
        return [
            OrderStatusService::PHASE_PURCHASE => [
                'label' => 'OC / Pedido',
                'required' => ['purchase_order_provider_pdf' => 'PDF OC proveedor'],
                'statuses' => [OrderStatusService::STATUS_PO_GENERATED, OrderStatusService::STATUS_WAITING_SUPPLIER],
                'document_type' => 'purchase_order_provider_pdf',
            ],
            OrderStatusService::PHASE_SUPPLIER_CONFIRMATION => [
                'label' => 'Confirmacion',
                'required' => ['supplier_confirmation' => 'Confirmacion proveedor'],
                'statuses' => [OrderStatusService::STATUS_SUPPLIER_CONFIRMED],
                'document_type' => 'supplier_confirmation',
            ],
            OrderStatusService::PHASE_TRANSIT => [
                'label' => 'Transito',
                'required' => ['shipping_document' => 'Comprobante de envio'],
                'statuses' => [OrderStatusService::STATUS_IN_TRANSIT],
                'document_type' => 'shipping_document',
            ],
            OrderStatusService::PHASE_CUSTOMS => [
                'label' => 'Aduana',
                'required' => ['shipping_document' => 'Guia / documento de envio'],
                'statuses' => [OrderStatusService::STATUS_IN_CUSTOMS, OrderStatusService::STATUS_CUSTOMS_RELEASED],
                'document_type' => 'shipping_document',
            ],
            OrderStatusService::PHASE_WAREHOUSE => [
                'label' => 'Bodega',
                'required' => ['warehouse_receipt' => 'Evidencia de recepcion'],
                'statuses' => [OrderStatusService::STATUS_IN_CUSTOMS, OrderStatusService::STATUS_CUSTOMS_RELEASED],
                'document_type' => 'warehouse_receipt',
            ],
            OrderStatusService::PHASE_KARDEX_OUTPUT => [
                'label' => 'Facturación',
                'required' => ['kardex_output_document' => 'Factura de salida Kardex'],
                'statuses' => [OrderStatusService::STATUS_RECEIVED_WAREHOUSE_PARTIAL, OrderStatusService::STATUS_RECEIVED_WAREHOUSE, OrderStatusService::STATUS_KARDEX_ENTRY_PARTIAL, OrderStatusService::STATUS_KARDEX_ENTRY, OrderStatusService::STATUS_AVAILABLE_FOR_DISPATCH, OrderStatusService::STATUS_WAITING_OTHER_ITEMS, OrderStatusService::STATUS_HELD_BY_ADMIN, OrderStatusService::STATUS_DISPATCH_AUTHORIZATION_REQUESTED, OrderStatusService::STATUS_APPROVED_PARTIAL_DISPATCH],
                'document_type' => 'kardex_output_document',
            ],
            OrderStatusService::PHASE_LOGISTICS => [
                'label' => 'Despacho al cliente',
                'required' => ['delivery_logistics_document' => 'Foto/comprobante de envio'],
                'statuses' => [OrderStatusService::STATUS_SENT_CUSTOMER],
                'document_type' => 'delivery_logistics_document',
            ],
            OrderStatusService::PHASE_DELIVERY => [
                'label' => 'Confirmar entrega',
                'required' => ['delivery_confirmation' => 'Comprobante de entrega'],
                'statuses' => [OrderStatusService::STATUS_DELIVERED_PARTIAL, OrderStatusService::STATUS_DELIVERED, OrderStatusService::STATUS_CLOSED],
                'document_type' => 'delivery_confirmation',
            ],
        ];
    }

    private function defaultDocumentTypeForPhase(string $phase): string
    {
        return $this->phaseDefinitions()[$phase]['document_type'] ?? 'purchase_order_pdf';
    }

    private function phaseForCurrentStatus(): string
    {
        $phase = app(OrderStatusStateCatalog::class)->phaseForGeneralStatus($this->orderStatus->administrative_status ?? '');

        return array_key_exists($phase, $this->phaseDefinitions())
            ? $phase
            : (array_key_exists($this->orderStatus->administrative_phase, $this->phaseDefinitions())
                ? $this->orderStatus->administrative_phase
                : ($this->orderStatus->administrative_phase === OrderStatusService::PHASE_KARDEX_ENTRY ? OrderStatusService::PHASE_WAREHOUSE : OrderStatusService::PHASE_PURCHASE));
    }
}
