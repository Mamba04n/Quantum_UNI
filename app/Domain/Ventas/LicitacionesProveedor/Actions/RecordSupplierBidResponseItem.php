<?php

namespace App\Domain\Ventas\LicitacionesProveedor\Actions;

use App\Domain\Ventas\LicitacionesProveedor\Support\SupplierBidActivity;
use App\Domain\Ventas\LicitacionesProveedor\Support\SupplierBidResponseData;
use App\Domain\Ventas\LicitacionesProveedor\Support\SupplierBidResponsePayload;
use App\Domain\Ventas\LicitacionesProveedor\Support\SupplierBidResponseRepository;
use App\Domain\Ventas\LicitacionesProveedor\Support\SupplierBidResponseTracker;
use App\Models\Entity;
use App\Models\QuotationRequest;
use App\Models\QuotationRequestItem;
use App\Models\SupplierBidResponse;
use App\Models\SupplierBidResponseItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordSupplierBidResponseItem
{
    public function __construct(
        private readonly SupplierBidResponseTracker $tracker,
        private readonly SupplierBidResponseData $responseData,
        private readonly SupplierBidResponsePayload $payload,
        private readonly SupplierBidResponseRepository $responses,
        private readonly SupplierBidActivity $activity,
    ) {
    }

    public function execute(QuotationRequest $request, QuotationRequestItem $item, array $data, User $user, array $attachments = []): SupplierBidResponseItem
    {
        if ((int) $item->quotation_request_id !== (int) $request->id) {
            throw ValidationException::withMessages(['supplierBidItems' => 'La partida no pertenece a esta solicitud.']);
        }

        if (!in_array($request->status, [
            QuotationRequest::STATUS_RFQ_GENERATED,
            QuotationRequest::STATUS_SENT_TO_SUPPLIER,
            QuotationRequest::STATUS_SUPPLIER_RESPONSE_PENDING,
            QuotationRequest::STATUS_SUPPLIER_RESPONSE_RECEIVED,
            QuotationRequest::STATUS_SUPPLIER_SELECTED,
        ], true)) {
            throw ValidationException::withMessages(['supplier' => 'No se puede registrar respuesta proveedor sin RFQ generada o enviada.']);
        }

        return DB::transaction(function () use ($request, $item, $data, $user, $attachments) {
            $supplier = Entity::where('type', 'proveedor')->findOrFail((int) $data['supplier_id']);
            $response = filled($data['response_id'] ?? null)
                ? SupplierBidResponse::where('quotation_request_id', $request->id)->findOrFail((int) $data['response_id'])
                : SupplierBidResponse::where('quotation_request_id', $request->id)
                    ->where('supplier_id', $supplier->id)
                    ->whereDoesntHave('items.selection')
                    ->latest('id')
                    ->first();

            if (! $response) {
                $response = new SupplierBidResponse([
                    'quotation_request_id' => $request->id,
                    'supplier_id' => $supplier->id,
                    'response_code' => $this->responses->nextResponseCode($request, $supplier),
                ]);
            }

            if ($response->exists && $response->items()->whereHas('selection')->exists()) {
                if (in_array($request->status, [
                    QuotationRequest::STATUS_RFQ_GENERATED,
                    QuotationRequest::STATUS_SENT_TO_SUPPLIER,
                    QuotationRequest::STATUS_SUPPLIER_RESPONSE_PENDING,
                    QuotationRequest::STATUS_PENDING_BID,
                    QuotationRequest::STATUS_READY_FOR_BID,
                ], true)) {
                    $itemIds = $response->items()->pluck('id');
                    \App\Models\SupplierBidSelection::whereIn('supplier_bid_response_item_id', $itemIds)->delete();
                } else {
                    throw ValidationException::withMessages(['supplier' => 'No puedes editar una cotizacion que ya fue usada en una seleccion. Duplica como nueva si necesitas otra variante.']);
                }
            }

            $row = $data['item'] ?? [];
            $this->payload->validate(
                collect([$item]),
                [$item->id => $row],
                $response,
                $attachments,
                (bool) ($data['allow_incomplete_item'] ?? false),
            );

            $oldStatus = $request->status;
            $tracker = $this->tracker->trackerFor($request);
            $activityAction = $response->exists ? 'supplier_bid.response_item_updated' : 'supplier_bid.response_created';
            $supplierBid = $this->responses->bidForSupplier($request, $supplier, $user);

            $response->fill($this->responseData->responseAttributes($supplierBid, $supplier, $data, $response, $user))->save();

            $status = $this->payload->normalizeItemStatus($row);

            $attributes = ['quotation_request_item_id' => $item->id]
                + $this->responseData->itemAttributes($item, $row, $status, $data, $status === 'not_quoted');

            if (filled($data['response_item_id'] ?? null)) {
                $responseItem = $response->items()->whereKey((int) $data['response_item_id'])->firstOrFail();
                $responseItem->fill($attributes)->save();
            } elseif (! empty($data['create_response_line'])) {
                $responseItem = $response->items()->create($attributes);
            } else {
                $responseItem = $response->items()->updateOrCreate(
                    ['quotation_request_item_id' => $item->id],
                    $attributes
                );
            }

            if (!empty($attachments)) {
                $this->tracker->attachDocuments($tracker, $response, $supplier, $request, $attachments);
            }

            $this->tracker->markRequestPendingResponse($request);
            $this->tracker->syncTrackerToSupplier($tracker, $request, $supplier);
            $this->tracker->addResponseReference($tracker, $response, $supplier);
            $this->tracker->recordActivity(
                $tracker,
                $activityAction,
                "Partida {$this->activity->lineLabel($item)} guardada para {$supplier->name}",
                'supplier_bid_response_items',
                $responseItem->id,
                $oldStatus,
                $request->status,
                $user
            );

            $this->activity->logGlobal($activityAction, "Partida {$this->activity->lineLabel($item)} guardada para {$request->reference}", [
                'quote_id' => $request->id,
                'quote_item_id' => $item->id,
                'supplier_bid_response_id' => $response->id,
                'supplier_bid_response_item_id' => $responseItem->id,
            ]);

            return $responseItem->fresh(['response.supplier', 'quotationItem']);
        });
    }
}
