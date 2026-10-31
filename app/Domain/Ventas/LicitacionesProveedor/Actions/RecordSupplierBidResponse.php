<?php

namespace App\Domain\Ventas\LicitacionesProveedor\Actions;

use App\Domain\Ventas\LicitacionesProveedor\Support\SupplierBidActivity;
use App\Domain\Ventas\LicitacionesProveedor\Support\SupplierBidResponseData;
use App\Domain\Ventas\LicitacionesProveedor\Support\SupplierBidResponsePayload;
use App\Domain\Ventas\LicitacionesProveedor\Support\SupplierBidResponseRepository;
use App\Domain\Ventas\LicitacionesProveedor\Support\SupplierBidResponseTracker;
use App\Models\Entity;
use App\Models\QuotationRequest;
use App\Models\SupplierBidResponse;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordSupplierBidResponse
{
    public function __construct(
        private readonly SupplierBidResponseTracker $tracker,
        private readonly SupplierBidResponseData $responseData,
        private readonly SupplierBidResponsePayload $payload,
        private readonly SupplierBidResponseRepository $responses,
        private readonly SupplierBidActivity $activity,
    ) {
    }

    public function execute(QuotationRequest $request, array $data, User $user, array $attachments = []): SupplierBidResponse
    {
        $request->loadMissing('items');

        if (!in_array($request->status, [
            QuotationRequest::STATUS_RFQ_GENERATED,
            QuotationRequest::STATUS_SENT_TO_SUPPLIER,
            QuotationRequest::STATUS_SUPPLIER_RESPONSE_PENDING,
            QuotationRequest::STATUS_SUPPLIER_RESPONSE_RECEIVED,
            QuotationRequest::STATUS_SUPPLIER_SELECTED,
        ], true)) {
            throw ValidationException::withMessages(['supplier' => 'No se puede registrar respuesta proveedor sin RFQ generada o enviada.']);
        }

        return DB::transaction(function () use ($request, $data, $user, $attachments) {
            $supplier = Entity::where('type', 'proveedor')->findOrFail((int) $data['supplier_id']);
            $response = filled($data['response_id'] ?? null)
                ? SupplierBidResponse::where('quotation_request_id', $request->id)->findOrFail((int) $data['response_id'])
                : new SupplierBidResponse([
                    'quotation_request_id' => $request->id,
                    'supplier_id' => $supplier->id,
                    'response_code' => $this->responses->nextResponseCode($request, $supplier),
                ]);

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

            $responseItems = $this->payload->itemsForResponse($request, $data['item_ids'] ?? null);
            $this->payload->validate($responseItems, $data['items'] ?? [], $response, $attachments);

            $oldStatus = $request->status;
            $tracker = $this->tracker->trackerFor($request);
            $activityAction = $response->exists ? 'supplier_bid.response_updated' : 'supplier_bid.response_created';
            $supplierBid = $this->responses->bidForSupplier($request, $supplier, $user);

            $response->fill($this->responseData->responseAttributes($supplierBid, $supplier, $data, $response, $user))->save();

            if ($response->wasRecentlyCreated === false && filled($data['response_id'] ?? null)) {
                $response->items()->delete();
            }

            foreach ($responseItems as $item) {
                $row = $data['items'][$item->id] ?? [];
                $status = $this->payload->normalizeItemStatus($row);

                $response->items()->create([
                    'quotation_request_item_id' => $item->id,
                ] + $this->responseData->itemAttributes($item, $row, $status, $data, $status === 'not_quoted'));
            }

            if (!empty($attachments)) {
                $this->tracker->attachDocuments($tracker, $response, $supplier, $request, $attachments, true);
            }

            $this->tracker->markRequestPendingResponse($request);
            $this->tracker->syncTrackerToSupplier($tracker, $request, $supplier);
            $this->tracker->addResponseReference($tracker, $response, $supplier);
            $this->tracker->recordActivity(
                $tracker,
                $activityAction,
                "Cotizacion proveedor registrada: {$supplier->name} ({$response->response_code})",
                'supplier_bid_responses',
                $response->id,
                $oldStatus,
                $request->status,
                $user
            );

            $this->activity->logGlobal($activityAction, "Cotizacion de proveedor {$supplier->name} registrada para {$request->reference}", [
                'quote_id' => $request->id,
                'supplier_bid_response_id' => $response->id,
            ]);

            return $response->fresh(['supplier', 'items.quotationItem']);
        });
    }
}
