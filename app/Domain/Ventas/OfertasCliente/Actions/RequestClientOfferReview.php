<?php

namespace App\Domain\Ventas\OfertasCliente\Actions;

use App\Domain\Ventas\OfertasCliente\Support\ClientOfferReviewActivity;
use App\Domain\Ventas\OfertasCliente\Support\ClientOfferValidator;
use App\Models\ClientOfferReview;
use App\Models\QuotationRequest;
use App\Models\User;
use App\Services\ClientOfferVersionService;
use App\Services\SalesWorkflowService;
use App\Support\Concurrency\MonitoredOperation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequestClientOfferReview
{
    public function __construct(
        private readonly ClientOfferValidator $validator,
        private readonly ClientOfferReviewActivity $activity,
        private readonly ClientOfferVersionService $versionService,
        private readonly MonitoredOperation $monitor
    ) {}

    public function execute(QuotationRequest $quotation, User $user, ?string $notes = null): ClientOfferReview
    {
        return $this->monitor->run(
            'client_offer.review.request',
            'quotation_request',
            $quotation->id,
            fn () => DB::transaction(function () use ($quotation, $user, $notes) {
                $quotation = QuotationRequest::with([
                    'items.supplierBidSelection.responseItem.response.supplier',
                    'supplierBidSelections.responseItem.response.supplier',
                    'offerReviews',
                ])->whereKey($quotation->id)->lockForUpdate()->firstOrFail();

                $this->validator->assertOfferComplete($quotation);

                $existing = $quotation->offerReviews()
                    ->where(ClientOfferReview::query()->qualifyColumn('status'), ClientOfferReview::STATUS_PENDING)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    return $existing->refresh()->load(['quotationRequest', 'requester']);
                }

                $version = $this->versionService->createVersion($quotation, $user, $notes ?: 'Oferta enviada a revision admin', null, 'pending_admin_review');

                $review = ClientOfferReview::create([
                    'quotation_request_id' => $quotation->id,
                    'client_offer_version_id' => $version->id,
                    'requested_by' => $user->id,
                    'status' => ClientOfferReview::STATUS_PENDING,
                    'review_notes' => $notes,
                    'requested_at' => now(),
                ]);

                $oldStatus = $quotation->status;
                $quotation->forceFill([
                    'status' => QuotationRequest::STATUS_PENDING_ADMIN_REVIEW,
                    'commercial_phase' => SalesWorkflowService::PHASE_CLIENT_OFFER,
                    'admin_review_requested_at' => now(),
                    'offer_review_notes' => $notes,
                ])->save();

                $this->activity->recordReviewRequested($quotation, $review, $user, $oldStatus, $version->id);

                return $review->refresh()->load(['quotationRequest', 'requester']);
            }, (int) config('concurrency.transaction_attempts', 3))
        );
    }
}
