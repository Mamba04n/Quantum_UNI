<?php

namespace App\Domain\Ventas\OfertasCliente\Actions;

use App\Domain\Ventas\OfertasCliente\Support\ClientOfferReviewActivity;
use App\Models\ClientOfferReview;
use App\Models\QuotationRequest;
use App\Models\User;
use App\Services\SalesWorkflowService;
use App\Support\Concurrency\MonitoredOperation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveClientOfferReview
{
    public function __construct(
        private readonly ClientOfferReviewActivity $activity,
        private readonly MonitoredOperation $monitor
    ) {}

    public function execute(ClientOfferReview $review, User $admin, ?string $notes = null): ClientOfferReview
    {
        if (! $admin->can('client_offer_review.approve')) {
            throw ValidationException::withMessages(['review' => 'No tienes permiso para autorizar ofertas.']);
        }

        return $this->monitor->run(
            'client_offer.review.approve',
            'quotation_request',
            $review->quotation_request_id,
            fn () => DB::transaction(function () use ($review, $admin, $notes) {
                // All review transitions acquire aggregate locks in the same order:
                // quotation request first, review second.
                $quotation = QuotationRequest::whereKey($review->quotation_request_id)->lockForUpdate()->firstOrFail();
                $review = ClientOfferReview::whereKey($review->id)->lockForUpdate()->firstOrFail();

                if ($review->quotation_request_id !== $quotation->id) {
                    throw ValidationException::withMessages(['review' => 'La revision no pertenece a esta oferta.']);
                }

                if ($review->status !== ClientOfferReview::STATUS_PENDING) {
                    throw ValidationException::withMessages(['review' => 'Esta revision ya fue procesada.']);
                }

                if ($quotation->status !== QuotationRequest::STATUS_PENDING_ADMIN_REVIEW) {
                    throw ValidationException::withMessages(['review' => 'La oferta no esta pendiente de revision admin.']);
                }

                $review->forceFill([
                    'status' => ClientOfferReview::STATUS_APPROVED,
                    'reviewed_by' => $admin->id,
                    'review_notes' => $notes,
                    'reviewed_at' => now(),
                ])->save();

                $oldStatus = $quotation->status;
                $quotation->forceFill([
                    'status' => QuotationRequest::STATUS_APPROVED_TO_SEND,
                    'commercial_phase' => SalesWorkflowService::PHASE_CLIENT_OFFER,
                    'admin_reviewed_at' => now(),
                    'offer_review_notes' => $notes,
                ])->save();

                $review->offerVersion?->forceFill([
                    'status' => QuotationRequest::STATUS_APPROVED_TO_SEND,
                    'approved_by' => $admin->id,
                    'approved_at' => now(),
                ])->save();

                $this->activity->recordReviewProcessed(
                    $quotation,
                    $review,
                    $admin,
                    $oldStatus,
                    'client_offer.admin_approved_to_send',
                    'Administrador autorizo enviar la oferta al cliente',
                    $notes
                );

                return $review->fresh(['quotationRequest', 'offerVersion', 'requester', 'reviewer']);
            }, (int) config('concurrency.transaction_attempts', 3))
        );
    }
}
