<?php

namespace App\Domain\Ventas\FlujoTrabajo\Actions;

use App\Domain\Ventas\FlujoTrabajo\Services\SalesWorkflowService;
use App\Models\QuotationRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewAndRouteRequest
{
    public function execute(SalesWorkflowService $workflow, QuotationRequest $request, User $user): QuotationRequest
    {
        return DB::transaction(function () use ($workflow, $request, $user) {
            $request->loadMissing('items.product');

            if ($request->items->isEmpty()) {
                throw ValidationException::withMessages(['workflow' => 'La solicitud no tiene partidas para revisar.']);
            }

            $workflow->assertRequestItemsAreNormalized($request);

            $oldStatus = $request->status;

            if ($workflow->hasAvailableInventory($request)) {
                $workflow->transition($request, QuotationRequest::STATUS_CLIENT_OFFER_GENERATED, $user, true);
                $workflow->record($request, 'customer_request.reviewed', 'Solicitud revisada con stock disponible; lista para oferta directa.', SalesWorkflowService::PHASE_DIRECT_ORDER, $oldStatus, $request->status, $user);
            } else {
                $workflow->transition($request, QuotationRequest::STATUS_PENDING_BID, $user, true);
                $workflow->record($request, 'customer_request.sent_to_bid', 'Solicitud revisada y enviada a licitacion proveedor.', SalesWorkflowService::PHASE_SUPPLIER_BID, $oldStatus, $request->status, $user);
            }

            return $request->fresh('items');
        });
    }
}
