<?php

namespace App\Domain\Compras\Preparation\Services;

use App\Models\ActivityLog;
use App\Models\ApprovalRequest;
use App\Models\ClientApproval;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchasePreparationItem;
use App\Models\QuotationRequest;
use App\Models\SupplierBidResponseItem;
use App\Models\User;
use App\Services\ExchangeRateService;
use App\Services\OrderTrackerService;
use App\Services\PurchaseOrderService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PurchasePreparationService
{
    public function __construct(
        private readonly OrderTrackerService $trackerService,
        private readonly PurchaseOrderService $purchaseOrderService,
        private readonly ExchangeRateService $exchangeRateService,
    ) {}

    public function ensureForQuotation(QuotationRequest $quotation, User $user): Collection
    {
        $quotation->loadMissing([
            'items.supplierBidSelection.responseItem.response.supplier',
            'offerVersions.items',
            'clientApprovals.clientOffer.items',
            'purchasePreparationItems',
        ]);

        if (! in_array($quotation->status, [
            QuotationRequest::STATUS_CLIENT_APPROVED_TOTAL,
            QuotationRequest::STATUS_CLIENT_APPROVED_PARTIAL,
            QuotationRequest::STATUS_APPROVED_TOTAL,
            QuotationRequest::STATUS_APPROVED_PARTIAL,
            OrderTrackerService::QUOTE_PENDING_APPROVAL,
        ], true)) {
            throw ValidationException::withMessages(['purchase_preparation' => 'Primero debe existir aprobacion del cliente.']);
        }

        $approvedItems = $this->approvedItemsForPreparation($quotation);

        if ($approvedItems->isEmpty()) {
            throw ValidationException::withMessages(['purchase_preparation' => 'No hay items aprobados por el cliente para preparar compra.']);
        }

        return DB::transaction(function () use ($quotation, $approvedItems, $user) {
            $latestOffer = $quotation->offerVersions->sortByDesc('version_number')->first();
            $approvedItemIds = $approvedItems->pluck('id')->all();

            // A customer correction can add, remove or change the quantity of a line
            // after preparation was first opened. The preparation must be a current
            // operational reflection of the approved offer, never a stale snapshot.
            PurchasePreparationItem::query()
                ->where('quotation_request_id', $quotation->id)
                ->whereNotIn('quotation_request_item_id', $approvedItemIds)
                ->update(['status' => PurchasePreparationItem::STATUS_EXCLUDED]);

            foreach ($approvedItems as $item) {
                $selection = $item->supplierBidSelection;
                $responseItem = $selection?->responseItem;
                $offerItem = $latestOffer?->items?->firstWhere('quotation_request_item_id', $item->id);

                // The corrected quotation quantity is authoritative. The selected
                // supplier quantity is only a legacy fallback for old records.
                $quantity = (float) $item->quantity;
                if ($quantity <= 0) {
                    $quantity = (float) ($selection?->selected_quantity ?? 0);
                }

                if ($quantity <= 0) {
                    throw ValidationException::withMessages([
                        'purchase_preparation' => "La partida {$this->itemLabelFromQuotationItem($item)} no tiene una cantidad válida.",
                    ]);
                }

                $preparation = PurchasePreparationItem::firstOrNew([
                    'quotation_request_id' => $quotation->id,
                    'quotation_request_item_id' => $item->id,
                ]);

                $isNew = ! $preparation->exists;
                $preparation->client_offer_item_id = $offerItem?->id;
                $preparation->original_supplier_quote_id = $responseItem?->id;
                $preparation->original_supplier_id = $selection?->selected_supplier_id;
                $preparation->original_unit_cost = $selection?->selected_unit_cost;
                // Always take the current corrected quantity, including on an
                // existing preparation row that was created before the correction.
                $preparation->quantity_to_purchase = $quantity;

                if ($isNew || ! $preparation->final_supplier_id) {
                    $preparation->final_supplier_quote_id = $responseItem?->id;
                    $preparation->final_supplier_id = $selection?->selected_supplier_id;
                    $preparation->final_unit_cost = $selection?->selected_unit_cost;
                }

                if ($isNew || $preparation->status === PurchasePreparationItem::STATUS_EXCLUDED) {
                    $preparation->status = PurchasePreparationItem::STATUS_PENDING_REVIEW;
                    $preparation->reviewed_by = null;
                    $preparation->reviewed_at = null;
                }

                $preparation->save();
            }

            $tracker = $this->trackerService->trackerForQuotation($quotation);
            $tracker->update([
                'operational_phase' => 'purchase_preparation',
                'operational_status' => $quotation->status,
            ]);

            $this->trackerService->recordActivity(
                $tracker,
                'purchase_preparation.started',
                'Oferta aprobada por cliente. Orden lista para preparacion de compra.',
                'purchase_preparation',
                'quotation_requests',
                $quotation->id,
                null,
                $quotation->status,
                $user
            );

            return $quotation->fresh('purchasePreparationItems')->purchasePreparationItems;
        });
    }

    private function approvedItemsForPreparation(QuotationRequest $quotation): Collection
    {
        $flaggedItems = $quotation->items
            ->filter(fn ($item) => (bool) $item->is_approved)
            ->values();

        if (! in_array($quotation->status, [
            QuotationRequest::STATUS_CLIENT_APPROVED_TOTAL,
            QuotationRequest::STATUS_APPROVED_TOTAL,
        ], true)) {
            return $flaggedItems;
        }

        // A total approval applies to the immutable offer version the customer
        // actually accepted. Item flags can be stale after a correction cycle,
        // so they must never make valid lines disappear from purchasing.
        $approvedOffer = $quotation->clientApprovals
            ->where('status', ClientApproval::STATUS_APPROVED_TOTAL)
            ->sortByDesc(fn ($approval) => $approval->approved_at?->getTimestamp() ?? $approval->id)
            ->first()?->clientOffer;

        $approvedOffer ??= $quotation->offerVersions->sortByDesc('version_number')->first();
        $approvedItemIds = $approvedOffer?->items
            ?->pluck('quotation_request_item_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all() ?? [];

        if ($approvedItemIds === []) {
            return $flaggedItems;
        }

        return $quotation->items
            ->whereIn('id', $approvedItemIds)
            ->values();
    }

    public function confirmSupplier(PurchasePreparationItem $item, User $user): PurchasePreparationItem
    {
        $this->assertReadyLine($item);

        $item->forceFill([
            'status' => PurchasePreparationItem::STATUS_READY_FOR_PURCHASE_ORDER,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ])->save();

        $this->recordItemActivity($item, $user, 'purchase_preparation.supplier_confirmed', "Proveedor final confirmado para item {$this->itemLabel($item)}.");

        return $item->fresh(['quotationItem', 'finalSupplierQuote.response.supplier', 'finalSupplier']);
    }

    public function confirmAllSuppliers(QuotationRequest $quotation, User $user): Collection
    {
        return DB::transaction(function () use ($quotation, $user): Collection {
            $items = PurchasePreparationItem::query()
                ->where('quotation_request_id', $quotation->id)
                ->whereNotIn('status', [
                    PurchasePreparationItem::STATUS_READY_FOR_PURCHASE_ORDER,
                    PurchasePreparationItem::STATUS_EXCLUDED,
                ])
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'purchase_preparation' => 'No hay partidas pendientes de confirmar.',
                ]);
            }

            $items->each(fn (PurchasePreparationItem $item) => $this->assertReadyLine($item));

            return $items
                ->map(fn (PurchasePreparationItem $item) => $this->confirmSupplier($item, $user))
                ->values();
        });
    }

    public function changeFinalSupplier(PurchasePreparationItem $item, SupplierBidResponseItem $quoteItem, User $user, string $reason): PurchasePreparationItem
    {
        $item->loadMissing(['quotationItem', 'originalSupplier', 'finalSupplier']);
        $quoteItem->loadMissing('response.supplier');

        if ($quoteItem->quotation_request_item_id !== $item->quotation_request_item_id) {
            throw ValidationException::withMessages(['supplier_quote' => 'La cotizacion seleccionada no pertenece a este item.']);
        }

        if (blank(trim($reason))) {
            throw ValidationException::withMessages(['change_reason' => 'Debe registrar el motivo del cambio de proveedor.']);
        }

        if (! $quoteItem->is_selectable) {
            throw ValidationException::withMessages(['supplier_quote' => 'La cotizacion seleccionada no tiene precio y cantidad validos.']);
        }

        $oldSupplier = $item->finalSupplier?->name ?: $item->originalSupplier?->name ?: 'Sin proveedor';
        $oldCost = (float) ($item->final_unit_cost ?? $item->original_unit_cost ?? 0);
        $newSupplier = $quoteItem->response?->supplier?->name ?: 'Proveedor';
        $newCost = (float) $quoteItem->unit_cost;

        $item->forceFill([
            'final_supplier_quote_id' => $quoteItem->id,
            'final_supplier_id' => $quoteItem->response?->supplier_id,
            'final_unit_cost' => $newCost,
            'quantity_to_purchase' => (float) ($quoteItem->quoted_quantity ?: $item->quantity_to_purchase),
            'change_reason' => trim($reason),
            'status' => PurchasePreparationItem::STATUS_SUPPLIER_CONFIRMED,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ])->save();

        $label = $this->itemLabel($item);
        $message = "{$user->name} cambio el proveedor del item {$label} de {$oldSupplier} a {$newSupplier} por {$reason}.";

        $this->recordItemActivity($item, $user, 'purchase_preparation.supplier_changed', $message, [
            'old_supplier' => $oldSupplier,
            'new_supplier' => $newSupplier,
            'old_cost' => $oldCost,
            'new_cost' => $newCost,
            'difference' => round($newCost - $oldCost, 2),
            'reason' => $reason,
        ]);

        return $item->fresh(['quotationItem', 'finalSupplierQuote.response.supplier', 'finalSupplier']);
    }

    public function generatePurchaseOrders(QuotationRequest $quotation, User $user, string $currency = 'USD'): Collection
    {
        $currency = strtoupper(trim($currency));
        if (! in_array($currency, ['USD', 'NIO'], true)) {
            throw ValidationException::withMessages(['purchase_order_currency' => 'La moneda de la orden debe ser USD o NIO.']);
        }

        $quotation->loadMissing([
            'purchasePreparationItems.finalSupplierQuote.response.supplier',
            'purchasePreparationItems.quotationItem.product',
        ]);

        $readyItems = $quotation->purchasePreparationItems
            ->where('status', PurchasePreparationItem::STATUS_READY_FOR_PURCHASE_ORDER)
            ->filter(fn (PurchasePreparationItem $item) => $item->final_supplier_id && (float) $item->final_unit_cost > 0 && (float) $item->quantity_to_purchase > 0)
            ->reject(fn (PurchasePreparationItem $item) => PurchaseOrderItem::query()
                ->where('quotation_request_item_id', $item->quotation_request_item_id)
                ->whereHas('order', fn ($order) => $order->whereNotIn('status', ['anulado', 'cancelado', 'cancelled']))
                ->exists())
            ->values();

        if ($readyItems->isEmpty()) {
            throw ValidationException::withMessages(['purchase_order' => 'No hay items listos para generar orden de compra.']);
        }

        $missing = $quotation->purchasePreparationItems
            ->reject(fn (PurchasePreparationItem $item) => $item->status === PurchasePreparationItem::STATUS_EXCLUDED)
            ->filter(fn (PurchasePreparationItem $item) => ! $item->final_supplier_id || (float) $item->final_unit_cost <= 0 || (float) $item->quantity_to_purchase <= 0);

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages(['purchase_order' => 'Todos los items activos deben tener proveedor final, cantidad y costo proveedor.']);
        }

        return DB::transaction(function () use ($quotation, $readyItems, $user, $currency) {
            $orders = collect();
            $approval = $this->pendingPurchaseOrderApproval($quotation, $user);

            foreach ($readyItems->groupBy('final_supplier_id') as $supplierId => $supplierItems) {
                $convertedItems = $supplierItems->map(function (PurchasePreparationItem $item) use ($currency) {
                    $sourceCurrency = strtoupper($item->finalSupplierQuote?->currency ?: 'USD');
                    $unitCost = (float) $item->final_unit_cost;

                    if ($sourceCurrency !== $currency) {
                        $unitCost = (float) $this->exchangeRateService->convert($unitCost, $sourceCurrency, $currency)['amount'];
                    }

                    return ['item' => $item, 'unit_cost' => round($unitCost, 2)];
                });
                $total = $convertedItems->sum(fn (array $row) => (float) $row['item']->quantity_to_purchase * $row['unit_cost']);
                $first = $supplierItems->first();
                $response = $first->finalSupplierQuote?->response;

                $orders->push($this->purchaseOrderService->createOrderWithPayable([
                    'supplier_id' => (int) $supplierId,
                    'quotation_request_id' => $quotation->id,
                    'sale_order_id' => $quotation->sale_order_id,
                    'supplier_bid_response_id' => $response?->id,
                    'approval_request_id' => $approval->id,
                    'reference_number' => $this->nextPreparationReference($supplierId),
                    'source_type' => 'purchase_preparation',
                    'total_fob' => round($total, 2),
                    'currency' => $currency,
                    'order_date' => now()->toDateString(),
                    'expected_date' => null,
                    'generated_by' => $user->id,
                    'generated_at' => now(),
                    'notes' => "Generada desde preparacion de compra para {$quotation->reference}.",
                    'items' => $convertedItems->map(function (array $row) use ($currency) {
                        /** @var PurchasePreparationItem $prepItem */
                        $prepItem = $row['item'];
                        $unitCost = $row['unit_cost'];
                        $quoteItem = $prepItem->quotationItem;
                        $supplierQuote = $prepItem->finalSupplierQuote;

                        return [
                            'product_id' => $quoteItem?->product_id,
                            'quotation_request_item_id' => $quoteItem?->id,
                            'supplier_bid_selection_id' => $quoteItem?->supplierBidSelection?->id,
                            'supplier_bid_response_item_id' => $supplierQuote?->id,
                            'part_name' => $supplierQuote?->quoted_description ?: $quoteItem?->part_name,
                            'part_number' => $supplierQuote?->quoted_part_number ?: $quoteItem?->part_number,
                            'brand' => $supplierQuote?->quoted_brand ?: $quoteItem?->brand,
                            'model' => $supplierQuote?->quoted_model ?: $quoteItem?->model,
                            'quantity' => (float) $prepItem->quantity_to_purchase,
                            'unit_price' => $unitCost,
                            'total_price' => round((float) $prepItem->quantity_to_purchase * $unitCost, 2),
                            'currency' => $currency,
                            'expected_date' => $supplierQuote?->estimated_arrival_date,
                            'notes' => $prepItem->change_reason,
                        ];
                    })->all(),
                ]));
            }

            $tracker = $this->trackerService->trackerForQuotation($quotation);
            foreach ($orders as $order) {
                $this->trackerService->addReference($tracker, 'purchase_order', $order->reference_number, 'purchase_orders', $order->id);
                $this->trackerService->recordActivity(
                    $tracker,
                    'purchase_order.generated_by_supplier',
                    "Orden de compra generada para proveedor {$order->supplier?->name}.",
                    'purchase_order',
                    'purchase_orders',
                    $order->id,
                    null,
                    $order->status,
                    $user
                );
            }

            $this->trackerService->addReference($tracker, 'internal_approval', $approval->approval_code ?: 'APV-'.$approval->id, 'approval_requests', $approval->id);
            $this->trackerService->recordActivity(
                $tracker,
                'purchase_order.sent_to_approval',
                'Orden de compra generada y enviada a aprobacion administrativa.',
                'aprobacion_interna',
                'approval_requests',
                $approval->id,
                null,
                ApprovalRequest::STATUS_PENDING,
                $user
            );

            return $orders;
        });
    }

    public function sendGeneratedPurchaseOrdersToApproval(QuotationRequest $quotation, User $user): ApprovalRequest
    {
        $quotation->loadMissing('purchaseOrders');

        if ($quotation->purchaseOrders->isEmpty()) {
            throw ValidationException::withMessages(['purchase_order' => 'No hay ordenes de compra generadas para enviar a aprobacion.']);
        }

        return DB::transaction(function () use ($quotation, $user) {
            $approval = $this->pendingPurchaseOrderApproval($quotation, $user);

            if (Schema::hasColumn('purchase_orders', 'approval_request_id')) {
                PurchaseOrder::where('quotation_request_id', $quotation->id)
                    ->where(function ($orders) use ($approval) {
                        $orders->whereNull('approval_request_id')
                            ->orWhere('approval_request_id', $approval->id);
                    })
                    ->update(['approval_request_id' => $approval->id]);
            }

            $tracker = $this->trackerService->trackerForQuotation($quotation);
            $this->trackerService->addReference($tracker, 'internal_approval', $approval->approval_code ?: 'APV-'.$approval->id, 'approval_requests', $approval->id);
            $this->trackerService->recordActivity(
                $tracker,
                'purchase_order.sent_to_approval',
                'Orden de compra generada enviada a aprobacion administrativa.',
                'aprobacion_interna',
                'approval_requests',
                $approval->id,
                null,
                ApprovalRequest::STATUS_PENDING,
                $user
            );

            return $approval->fresh(['tracker', 'requester']);
        });
    }

    private function pendingPurchaseOrderApproval(QuotationRequest $quotation, User $user): ApprovalRequest
    {
        $tracker = $this->trackerService->trackerForQuotation($quotation);
        $hasApprovalType = Schema::hasColumn('approval_requests', 'approval_type');
        $hasApprovalCode = Schema::hasColumn('approval_requests', 'approval_code');
        $hasActiveKey = Schema::hasColumn('approval_requests', 'active_key');
        $activeKey = 'quotation_requests:'.$quotation->id.':purchase_order_generated';

        $query = ApprovalRequest::query()
            ->where('source_module', 'quotation_requests')
            ->where('source_id', $quotation->id)
            ->where('status', ApprovalRequest::STATUS_PENDING)
            ->where('request_type', ApprovalRequest::TYPE_INTERNAL_PURCHASE);

        if ($hasApprovalType) {
            $query->where(function ($type) {
                $type->whereNull('approval_type')
                    ->orWhere('approval_type', ApprovalRequest::TYPE_INTERNAL_PURCHASE);
            });
        }

        if ($hasActiveKey) {
            $query->where('active_key', $activeKey);
        }

        $existing = $query->lockForUpdate()->first();
        if ($existing) {
            return $existing;
        }

        $data = [
            'tracker_id' => $tracker->id,
            'request_type' => ApprovalRequest::TYPE_INTERNAL_PURCHASE,
            'source_module' => 'quotation_requests',
            'source_id' => $quotation->id,
            'requested_by' => $user->id,
            'status' => ApprovalRequest::STATUS_PENDING,
            'reason' => 'Revision administrativa posterior a Orden de Compra Generada.',
        ];

        if ($hasApprovalCode) {
            $data['approval_code'] = $this->nextApprovalCode();
        }

        if ($hasApprovalType) {
            $data['approval_type'] = ApprovalRequest::TYPE_INTERNAL_PURCHASE;
        }

        if ($hasActiveKey) {
            $data['active_key'] = $activeKey;
        }

        return ApprovalRequest::create($data);
    }

    private function assertReadyLine(PurchasePreparationItem $item): void
    {
        if (! $item->final_supplier_id || (float) $item->final_unit_cost <= 0) {
            throw ValidationException::withMessages(['purchase_preparation' => 'El item no tiene proveedor final y costo proveedor.']);
        }

        if ((float) $item->quantity_to_purchase <= 0) {
            throw ValidationException::withMessages(['purchase_preparation' => 'El item no tiene cantidad a comprar.']);
        }
    }

    private function itemLabelFromQuotationItem($item): string
    {
        return $item->part_name ?: $item->part_number ?: '#'.$item->id;
    }

    private function recordItemActivity(PurchasePreparationItem $item, User $user, string $action, string $message, array $context = []): void
    {
        $item->loadMissing('quotationRequest');
        $tracker = $this->trackerService->trackerForQuotation($item->quotationRequest);

        $this->trackerService->recordActivity(
            $tracker,
            $action,
            $message,
            'purchase_preparation',
            'purchase_preparation_items',
            $item->id,
            null,
            $item->status,
            $user
        );

        ActivityLog::log('compras', $action, $message, array_merge($context, [
            'quotation_request_id' => $item->quotation_request_id,
            'quotation_request_item_id' => $item->quotation_request_item_id,
            'purchase_preparation_item_id' => $item->id,
        ]));
    }

    private function itemLabel(PurchasePreparationItem $item): string
    {
        $item->loadMissing('quotationItem');

        return $item->quotationItem?->part_name
            ?: $item->quotationItem?->part_number
            ?: '#'.$item->quotation_request_item_id;
    }

    private function nextPreparationReference(int|string $supplierId): string
    {
        $next = PurchaseOrder::count() + 1;
        $supplierCode = str_pad((string) $supplierId, 3, '0', STR_PAD_LEFT);

        do {
            $reference = 'OC-'.$supplierCode.'-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            $next++;
        } while (PurchaseOrder::where('reference_number', $reference)->exists());

        return $reference;
    }

    private function nextApprovalCode(): string
    {
        $next = ApprovalRequest::whereYear('created_at', now()->year)->count() + 1;

        do {
            $code = 'APV-'.now()->year.'-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            $next++;
        } while (Schema::hasColumn('approval_requests', 'approval_code') && ApprovalRequest::where('approval_code', $code)->exists());

        return $code;
    }
}
