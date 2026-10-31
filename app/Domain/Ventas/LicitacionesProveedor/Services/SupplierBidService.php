<?php

namespace App\Domain\Ventas\LicitacionesProveedor\Services;

use App\Models\ActivityLog;
use App\Models\Entity;
use App\Models\QuotationRequest;
use App\Models\QuotationRequestItem;
use App\Models\SupplierBid;
use App\Models\SupplierBidItem;
use App\Models\User;
use App\Services\ExchangeRateService;
use App\Services\OrderTrackerService;
use App\Services\SalesWorkflowService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierBidService
{
    public function __construct(private readonly OrderTrackerService $trackerService) {}

    public function createOrUpdateBid(QuotationRequest $request, array $data, User $user, ?UploadedFile $proforma = null): SupplierBid
    {
        $request->loadMissing('items');

        if (! in_array($request->status, [
            QuotationRequest::STATUS_RFQ_GENERATED,
            QuotationRequest::STATUS_SENT_TO_SUPPLIER,
            QuotationRequest::STATUS_SUPPLIER_RESPONSE_PENDING,
            QuotationRequest::STATUS_SUPPLIER_RESPONSE_RECEIVED,
            QuotationRequest::STATUS_SUPPLIER_SELECTED,
        ], true)) {
            throw ValidationException::withMessages(['supplier' => 'No se puede registrar respuesta proveedor sin RFQ generada o enviada.']);
        }

        return DB::transaction(function () use ($request, $data, $user, $proforma) {
            $supplier = Entity::where('type', 'proveedor')->findOrFail($data['supplier_id']);
            $this->validateResponseItems($request, $data['items'] ?? []);

            $bid = SupplierBid::firstOrCreate(
                [
                    'quotation_request_id' => $request->id,
                    'supplier_id' => $supplier->id,
                ],
                [
                    'reference' => $this->nextReference($request),
                    'created_by' => $user->id,
                ]
            );

            $proformaPath = $proforma ? $proforma->store('supplier-proformas', 'private') : $bid->proforma_path;

            $bid->fill([
                'status' => 'response_received',
                'currency' => strtoupper($data['currency'] ?? $bid->currency ?? 'USD'),
                'exchange_rate' => $data['exchange_rate'] ?? null,
                'payment_terms' => $data['payment_terms'] ?? null,
                'delivery_time' => $data['delivery_time'] ?? null,
                'warranty' => $data['warranty'] ?? null,
                'valid_until' => $data['valid_until'] ?? null,
                'proforma_path' => $proformaPath,
                'notes' => $data['notes'] ?? null,
            ])->save();

            foreach ($request->items as $item) {
                $row = $data['items'][$item->id] ?? [];
                $isQuoted = ! (bool) ($row['not_quoted'] ?? false);
                $unitCost = $isQuoted ? (float) ($row['unit_cost'] ?? 0) : 0;
                $quantity = (float) ($row['quantity'] ?? $item->quantity ?? 1);

                $bid->items()->updateOrCreate(
                    ['quotation_request_item_id' => $item->id],
                    [
                        'quantity' => $quantity,
                        'unit_cost' => $unitCost,
                        'line_total' => round($quantity * $unitCost, 2),
                        'currency' => strtoupper($data['currency'] ?? 'USD'),
                        'is_quoted' => $isQuoted,
                        'delivery_time' => $row['delivery_time'] ?? $data['delivery_time'] ?? null,
                        'estimated_arrival_date' => $row['estimated_arrival_date'] ?? null,
                        'part_model' => $row['part_model'] ?? $item->model,
                        'serial_number' => $row['serial_number'] ?? $item->serial_number,
                        'brand' => $row['brand'] ?? $item->brand,
                        'warranty' => $row['warranty'] ?? $data['warranty'] ?? null,
                        'notes' => $row['notes'] ?? null,
                    ]
                );
            }

            $oldStatus = $request->status;
            $request->forceFill([
                'status' => QuotationRequest::STATUS_SUPPLIER_RESPONSE_RECEIVED,
                'commercial_phase' => SalesWorkflowService::PHASE_SUPPLIER_BID,
            ])->save();

            $tracker = $this->trackerService->trackerForQuotation($request);
            $tracker->update([
                'operational_phase' => SalesWorkflowService::PHASE_SUPPLIER_BID,
                'operational_status' => $request->status,
                'supplier_id' => $supplier->id,
            ]);

            $this->trackerService->addReference($tracker, 'supplier_bid', $bid->reference, 'supplier_bids', $bid->id);
            $this->trackerService->recordActivity(
                $tracker,
                'supplier_bid.response_created',
                "Respuesta de proveedor registrada: {$supplier->name}",
                SalesWorkflowService::PHASE_SUPPLIER_BID,
                'supplier_bids',
                $bid->id,
                $oldStatus,
                $request->status,
                $user
            );

            ActivityLog::log('ventas', 'supplier_bid.response_created', "Respuesta de proveedor {$supplier->name} registrada para {$request->reference}", [
                'quote_id' => $request->id,
                'supplier_bid_id' => $bid->id,
            ]);

            if ($proforma) {
                $this->trackerService->addAttachment(
                    $tracker,
                    $proforma,
                    SalesWorkflowService::PHASE_SUPPLIER_BID,
                    "Proforma proveedor {$supplier->name}",
                    null,
                    'supplier_bids',
                    $bid->id
                );

                ActivityLog::log('ventas', 'supplier_bid.document_attached', "Proforma adjuntada para {$supplier->name}", [
                    'quote_id' => $request->id,
                    'supplier_bid_id' => $bid->id,
                    'file_path' => $proformaPath,
                ]);
            }

            return $bid->fresh(['supplier', 'items.quotationItem']);
        });
    }

    public function selectWinner(SupplierBid $bid, User $user, ?string $reason = null): SupplierBid
    {
        return DB::transaction(function () use ($bid, $user, $reason) {
            $bid->loadMissing('quotationRequest.items', 'items', 'supplier');
            $request = $bid->quotationRequest;

            if ($bid->items->isEmpty() || $bid->items->contains(fn ($item) => $item->is_quoted && (float) $item->unit_cost <= 0)) {
                throw ValidationException::withMessages(['supplier' => 'La oferta del proveedor tiene partidas sin costo.']);
            }

            SupplierBid::where('quotation_request_id', $request->id)->update([
                'is_winner' => false,
                'selected_by' => null,
                'selected_at' => null,
            ]);

            $bid->update([
                'is_winner' => true,
                'status' => 'winner_selected',
                'winner_reason' => $reason,
                'selected_by' => $user->id,
                'selected_at' => now(),
            ]);

            foreach ($bid->items as $bidItem) {
                $quoteItem = $bidItem->quotationItem;
                if ($quoteItem && $bidItem->is_quoted) {
                    $quoteItem = app(ExchangeRateService::class)->syncSupplierCostInNio(
                        $quoteItem,
                        $bidItem->unit_cost,
                        $bidItem->currency ?: $bid->currency,
                    );
                    if ((float) ($quoteItem->unit_price ?? 0) <= 0) {
                        $quoteItem->unit_price = round((float) $quoteItem->supplier_unit_cost_nio * 1.35, 2);
                    }
                    $quoteItem->selected_supplier_bid_item_id = $bidItem->id;
                    $quoteItem->selected_supplier_id = $bid->supplier_id;
                    $quoteItem->supplier_delivery_time = $bidItem->delivery_time ?: $bid->delivery_time;
                    $quoteItem->supplier_response_notes = $bidItem->notes ?: $bid->notes;
                    $quoteItem->line_total = round((float) $quoteItem->quantity * (float) ($quoteItem->unit_price ?? 0), 2);
                    $quoteItem->save();
                }
            }

            $oldStatus = $request->status;
            $request->load('items');
            $request->forceFill([
                'status' => QuotationRequest::STATUS_SUPPLIER_SELECTED,
                'commercial_phase' => SalesWorkflowService::PHASE_SUPPLIER_BID,
                'subtotal' => $request->items->sum('line_total'),
                'total_amount' => round($request->items->sum('line_total') * 1.15, 2),
            ])->save();

            $tracker = $this->trackerService->trackerForQuotation($request);
            $tracker->update([
                'operational_phase' => SalesWorkflowService::PHASE_SUPPLIER_BID,
                'operational_status' => $request->status,
                'supplier_id' => $bid->supplier_id,
            ]);

            $this->trackerService->recordActivity(
                $tracker,
                'supplier_bid.winner_selected',
                "Proveedor ganador seleccionado: {$bid->supplier?->name}",
                SalesWorkflowService::PHASE_SUPPLIER_BID,
                'supplier_bids',
                $bid->id,
                $oldStatus,
                $request->status,
                $user
            );

            ActivityLog::log('ventas', 'supplier_bid.winner_selected', "Proveedor ganador {$bid->supplier?->name} seleccionado para {$request->reference}", [
                'quote_id' => $request->id,
                'supplier_bid_id' => $bid->id,
            ]);

            return $bid->fresh(['supplier', 'items.quotationItem']);
        });
    }

    public function selectLineWinner(SupplierBidItem $bidItem, User $user, ?string $reason = null): SupplierBidItem
    {
        return DB::transaction(function () use ($bidItem, $user, $reason) {
            $bidItem->loadMissing('bid.supplier', 'quotationItem.quotationRequest.items');

            if ((float) $bidItem->unit_cost <= 0) {
                throw ValidationException::withMessages(['supplier' => 'La partida seleccionada no tiene costo registrado.']);
            }

            if (! $bidItem->is_quoted) {
                throw ValidationException::withMessages(['supplier' => 'No puedes seleccionar una partida marcada como no cotizada.']);
            }

            $bid = $bidItem->bid;
            $quoteItem = $bidItem->quotationItem;
            $request = $quoteItem->quotationRequest;

            $quoteItem->forceFill([
                'selected_supplier_bid_item_id' => $bidItem->id,
                'selected_supplier_id' => $bid->supplier_id,
                'supplier_delivery_time' => $bidItem->delivery_time ?: $bid->delivery_time,
                'supplier_response_notes' => $bidItem->notes ?: $bid->notes,
            ]);
            $quoteItem->save();

            $quoteItem = app(ExchangeRateService::class)->syncSupplierCostInNio(
                $quoteItem,
                $bidItem->unit_cost,
                $bidItem->currency ?: $bid->currency,
            );
            if ((float) ($quoteItem->unit_price ?? 0) <= 0) {
                $quoteItem->unit_price = round((float) $quoteItem->supplier_unit_cost_nio * 1.35, 2);
            }
            $quoteItem->line_total = round((float) $quoteItem->quantity * (float) ($quoteItem->unit_price ?? 0), 2);
            $quoteItem->save();

            $bid->update([
                'status' => 'selected_for_line',
                'is_winner' => true,
                'winner_reason' => $reason ?: $bid->winner_reason,
                'selected_by' => $user->id,
                'selected_at' => now(),
            ]);

            $request->load('items');
            $oldStatus = $request->status;
            $request->forceFill([
                'status' => QuotationRequest::STATUS_SUPPLIER_SELECTED,
                'commercial_phase' => SalesWorkflowService::PHASE_SUPPLIER_BID,
                'subtotal' => $request->items->sum('line_total'),
                'total_amount' => round($request->items->sum('line_total') * 1.15, 2),
            ])->save();

            $tracker = $this->trackerService->trackerForQuotation($request);
            $tracker->update([
                'operational_phase' => SalesWorkflowService::PHASE_SUPPLIER_BID,
                'operational_status' => $request->status,
                'supplier_id' => $bid->supplier_id,
            ]);

            $this->trackerService->recordActivity(
                $tracker,
                'supplier_bid.item_selected',
                "Proveedor seleccionado para partida {$this->lineLabel($quoteItem)}: {$bid->supplier?->name}",
                SalesWorkflowService::PHASE_SUPPLIER_BID,
                'supplier_bid_items',
                $bidItem->id,
                $oldStatus,
                $request->status,
                $user
            );

            ActivityLog::log('ventas', 'supplier_bid.item_selected', "Proveedor {$bid->supplier?->name} seleccionado para partida {$this->lineLabel($quoteItem)}", [
                'quote_id' => $request->id,
                'quote_item_id' => $quoteItem->id,
                'supplier_bid_id' => $bid->id,
                'supplier_bid_item_id' => $bidItem->id,
            ]);

            return $bidItem->fresh(['bid.supplier', 'quotationItem']);
        });
    }

    public function removeLineSelection(QuotationRequestItem $item, User $user): QuotationRequestItem
    {
        $item->forceFill([
            'unit_cost' => null,
            'selected_supplier_bid_item_id' => null,
            'selected_supplier_id' => null,
            'supplier_delivery_time' => null,
            'supplier_response_notes' => null,
        ])->save();

        ActivityLog::log('ventas', 'supplier_bid.item_selection_removed', "Seleccion removida para partida {$this->lineLabel($item)}", [
            'quote_item_id' => $item->id,
            'quote_id' => $item->quotation_request_id,
        ]);

        return $item->fresh();
    }

    public function markReadyForClientOffer(QuotationRequest $request, User $user): QuotationRequest
    {
        $request->loadMissing('items.selectedSupplier', 'supplierBids.items');
        $selected = $request->items->filter(fn ($item) => $item->selected_supplier_id && (float) ($item->unit_cost ?? 0) > 0);

        if ($selected->isEmpty()) {
            throw ValidationException::withMessages(['supplier' => 'Selecciona al menos una partida cotizada antes de continuar.']);
        }

        $request->items()->update(['is_approved' => false]);
        foreach ($selected as $item) {
            $item->forceFill(['is_approved' => true])->save();
        }

        $oldStatus = $request->status;
        $request->forceFill([
            'status' => QuotationRequest::STATUS_SUPPLIER_SELECTED,
            'commercial_phase' => SalesWorkflowService::PHASE_SUPPLIER_BID,
            'subtotal' => $request->items()->where('is_approved', true)->sum('line_total'),
            'total_amount' => round($request->items()->where('is_approved', true)->sum('line_total') * 1.15, 2),
        ])->save();

        $tracker = $this->trackerService->trackerForQuotation($request);
        $this->trackerService->recordActivity(
            $tracker,
            'supplier_bid.ready_for_client_offer',
            "Licitacion {$request->reference} lista para Oferta Cliente",
            SalesWorkflowService::PHASE_SUPPLIER_BID,
            'quotation_requests',
            $request->id,
            $oldStatus,
            $request->status,
            $user
        );

        ActivityLog::log('ventas', 'supplier_bid.ready_for_client_offer', "Licitacion lista para oferta cliente {$request->reference}", [
            'quote_id' => $request->id,
            'selected_items' => $selected->count(),
        ]);

        return $request->fresh('items');
    }

    private function validateResponseItems(QuotationRequest $request, array $rows): void
    {
        foreach ($request->items as $item) {
            $row = $rows[$item->id] ?? [];
            $notQuoted = (bool) ($row['not_quoted'] ?? false);
            $quantity = (float) ($row['quantity'] ?? 0);
            $unitCost = (float) ($row['unit_cost'] ?? 0);

            if ($quantity <= 0) {
                throw ValidationException::withMessages(["supplierBidItems.{$item->id}.quantity" => 'La cantidad cotizada debe ser mayor que 0.']);
            }

            if ($unitCost < 0) {
                throw ValidationException::withMessages(["supplierBidItems.{$item->id}.unit_cost" => 'El precio proveedor no puede ser negativo.']);
            }

            if (! $notQuoted && $unitCost <= 0) {
                throw ValidationException::withMessages(["supplierBidItems.{$item->id}.unit_cost" => 'Ingresa precio proveedor o marca la partida como No cotizado.']);
            }
        }
    }

    private function nextReference(QuotationRequest $request): string
    {
        $count = SupplierBid::where('quotation_request_id', $request->id)->count() + 1;

        return 'SB-'.$request->reference.'-'.str_pad((string) $count, 2, '0', STR_PAD_LEFT);
    }

    private function lineLabel(QuotationRequestItem $item): string
    {
        return $item->part_number ?: ($item->part_name ?: 'sin referencia');
    }
}
