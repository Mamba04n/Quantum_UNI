<?php

namespace App\Domain\Ventas\OfertasCliente\Actions;

use App\Domain\Ventas\OfertasCliente\Support\ClientOfferReviewActivity;
use App\Models\ClientOfferChangeRequest;
use App\Models\QuotationRequest;
use App\Models\User;
use App\Services\ClientOfferVersionService;
use App\Services\SalesWorkflowService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegisterCustomerChangeRequest
{
    private const SENSITIVE_CHANGE_TYPES = [
        'cambio_precio',
        'cambio_cantidad',
        'cambio_producto',
        'cambio_marca',
        'cambio_modelo',
        'quitar_producto',
        'agregar_producto',
        'cambio_fecha_entrega',
    ];

    private const BID_CHANGE_TYPES = [
        'cambio_producto',
        'cambio_marca',
        'cambio_modelo',
        'agregar_producto',
    ];

    public function __construct(
        private readonly ClientOfferReviewActivity $activity,
        private readonly ClientOfferVersionService $versionService
    ) {}

    public function execute(
        QuotationRequest $quotation,
        User $user,
        string $changeType,
        string $reason,
        ?string $comment = null,
        array $affectedItems = [],
        ?UploadedFile $attachment = null
    ): ClientOfferChangeRequest {
        if (blank($changeType)) {
            throw ValidationException::withMessages(['customerChangeType' => 'El tipo de cambio es obligatorio.']);
        }

        if (blank($reason)) {
            throw ValidationException::withMessages(['customerChangeReason' => 'El motivo es obligatorio.']);
        }

        return DB::transaction(function () use ($quotation, $user, $changeType, $reason, $comment, $affectedItems, $attachment) {
            $quotation = QuotationRequest::with('items')->whereKey($quotation->id)->lockForUpdate()->firstOrFail();

            if (! in_array($quotation->status, [
                QuotationRequest::STATUS_CLIENT_RESPONSE_PENDING,
                QuotationRequest::STATUS_SENT_TO_CUSTOMER,
                QuotationRequest::STATUS_CLIENT_OFFER_SENT,
            ], true)) {
                throw ValidationException::withMessages(['customer_change' => 'La oferta no esta esperando respuesta del cliente.']);
            }

            $requiresAdminReview = in_array($changeType, self::SENSITIVE_CHANGE_TYPES, true);
            $requiresBidReselection = in_array($changeType, self::BID_CHANGE_TYPES, true);
            $attachmentPath = $attachment?->store('client-offer-changes', 'private');
            $version = $quotation->offerVersions()->latest('version_number')->first();

            $change = ClientOfferChangeRequest::create([
                'quotation_request_id' => $quotation->id,
                'client_offer_version_id' => $version?->id,
                'requested_by' => $user->id,
                'change_type' => $changeType,
                'affected_items_json' => $affectedItems ?: null,
                'reason' => $reason,
                'customer_comment' => $comment,
                'attachment_path' => $attachmentPath,
                'status' => ClientOfferChangeRequest::STATUS_PENDING,
                'requires_admin_review' => $requiresAdminReview,
                'requires_bid_reselection' => $requiresBidReselection,
            ]);

            $oldStatus = $quotation->status;
            $quotation->forceFill([
                'status' => QuotationRequest::STATUS_CUSTOMER_CHANGES_REQUESTED,
                'commercial_phase' => $requiresBidReselection ? SalesWorkflowService::PHASE_SUPPLIER_BID : SalesWorkflowService::PHASE_CLIENT_OFFER,
                'offer_review_notes' => $reason,
            ])->save();

            $this->versionService->createVersion($quotation, $user, 'Cliente solicito cambios: '.$reason, null, 'customer_changes_requested');

            $this->activity->recordCustomerChangesRequested($quotation, $change, $user, $oldStatus, $reason, $changeType);

            return $change->fresh(['quotationRequest', 'offerVersion']);
        });
    }
}
