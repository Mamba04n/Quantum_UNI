<?php

namespace App\Domain\Ventas\OfertasCliente\Actions;

use App\Domain\Ventas\OfertasCliente\Support\ClientOfferReviewActivity;
use App\Models\ClientOfferReview;
use App\Models\QuotationRequest;
use App\Models\User;
use App\Services\SalesWorkflowService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequestClientOfferChanges
{
    public function __construct(
        private readonly ClientOfferReviewActivity $activity
    ) {}

    public function execute(ClientOfferReview $review, User $admin, string $reason): ClientOfferReview
    {
        if (! $admin->can('client_offer_review.request_changes')) {
            throw ValidationException::withMessages(['review' => 'No tienes permiso para solicitar cambios.']);
        }

        if (blank($reason)) {
            throw ValidationException::withMessages(['reviewReason' => 'El motivo es obligatorio.']);
        }

        return DB::transaction(function () use ($review, $admin, $reason) {
            $review = ClientOfferReview::whereKey($review->id)->lockForUpdate()->firstOrFail();

            if ($review->status !== ClientOfferReview::STATUS_PENDING) {
                throw ValidationException::withMessages(['review' => 'Esta revision ya fue procesada.']);
            }

            $quotation = QuotationRequest::whereKey($review->quotation_request_id)->lockForUpdate()->firstOrFail();

            if ($quotation->status !== QuotationRequest::STATUS_PENDING_ADMIN_REVIEW) {
                throw ValidationException::withMessages(['review' => 'La oferta no esta pendiente de revision admin.']);
            }

            $review->forceFill([
                'status' => ClientOfferReview::STATUS_CHANGES_REQUESTED,
                'reviewed_by' => $admin->id,
                'review_notes' => $reason,
                'reviewed_at' => now(),
            ])->save();

            $oldStatus = $quotation->status;
            $quotation->forceFill([
                'status' => QuotationRequest::STATUS_ADMIN_CHANGES_REQUESTED,
                'commercial_phase' => SalesWorkflowService::PHASE_CLIENT_OFFER,
                'admin_reviewed_at' => now(),
                'offer_review_notes' => $reason,
            ])->save();

            $review->offerVersion?->forceFill([
                'status' => QuotationRequest::STATUS_ADMIN_CHANGES_REQUESTED,
                'approved_by' => null,
                'approved_at' => null,
            ])->save();

            $this->activity->recordReviewProcessed(
                $quotation,
                $review,
                $admin,
                $oldStatus,
                'client_offer.admin_changes_requested',
                'Administrador solicito cambios en la oferta',
                $reason
            );

            return $review->fresh(['quotationRequest', 'offerVersion', 'requester', 'reviewer']);
        });
    }
}
