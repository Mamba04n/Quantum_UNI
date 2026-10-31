<?php

namespace App\Domain\Ventas\OfertasCliente\Support;

use App\Models\ActivityLog;
use App\Models\ClientOfferChangeRequest;
use App\Models\ClientOfferReview;
use App\Models\QuotationRequest;
use App\Models\User;
use App\Services\OrderTrackerService;

class ClientOfferReviewActivity
{
    public function __construct(
        private readonly OrderTrackerService $trackerService
    ) {}

    public function recordReviewRequested(QuotationRequest $quotation, ClientOfferReview $review, User $user, string $oldStatus, int $versionId): void
    {
        $tracker = $this->trackerService->trackerForQuotation($quotation);
        $tracker->update([
            'operational_phase' => $quotation->commercial_phase,
            'operational_status' => $quotation->status,
        ]);

        $this->trackerService->addReference($tracker, 'client_offer_review', 'REV-'.$review->id, 'client_offer_reviews', $review->id);

        $this->trackerService->recordActivity(
            $tracker,
            'client_offer.review_requested',
            "Oferta enviada a revision del administrador por {$user->name}",
            $quotation->commercial_phase,
            'client_offer_reviews',
            $review->id,
            $oldStatus,
            $quotation->status,
            $user
        );

        ActivityLog::log('ventas', 'client_offer.review_requested', 'Oferta enviada a revision del administrador', [
            'quote_id' => $quotation->id,
            'review_id' => $review->id,
            'version_id' => $versionId,
        ]);
    }

    public function recordReviewProcessed(QuotationRequest $quotation, ClientOfferReview $review, User $admin, string $oldStatus, string $action, string $message, ?string $notes): void
    {
        $tracker = $this->trackerService->trackerForQuotation($quotation);
        $tracker->update([
            'operational_phase' => $quotation->commercial_phase,
            'operational_status' => $quotation->status,
        ]);

        $this->trackerService->recordActivity(
            $tracker,
            $action,
            $notes ? "{$message}: {$notes}" : $message,
            $quotation->commercial_phase,
            'client_offer_reviews',
            $review->id,
            $oldStatus,
            $quotation->status,
            $admin
        );

        ActivityLog::log('ventas', $action, $message, [
            'quote_id' => $quotation->id,
            'review_id' => $review->id,
        ]);
    }

    public function recordSentToCustomer(QuotationRequest $quotation, User $user, string $oldStatus): void
    {
        $tracker = $this->trackerService->trackerForQuotation($quotation);
        $tracker->update([
            'operational_phase' => $quotation->commercial_phase,
            'operational_status' => $quotation->status,
        ]);

        $this->trackerService->recordActivity(
            $tracker,
            'client_offer.sent_to_customer',
            "Oferta enviada al cliente por {$user->name}",
            $quotation->commercial_phase,
            'quotation_requests',
            $quotation->id,
            $oldStatus,
            $quotation->status,
            $user
        );

        ActivityLog::log('ventas', 'client_offer.sent_to_customer', 'Oferta enviada al cliente', [
            'quote_id' => $quotation->id,
        ]);
    }

    public function recordCustomerChangesRequested(QuotationRequest $quotation, ClientOfferChangeRequest $change, User $user, string $oldStatus, string $reason, string $changeType): void
    {
        $tracker = $this->trackerService->trackerForQuotation($quotation);
        $tracker->update([
            'operational_phase' => $quotation->commercial_phase,
            'operational_status' => $quotation->status,
        ]);

        $this->trackerService->addReference($tracker, 'client_offer_change_request', 'CCR-'.$change->id, 'client_offer_change_requests', $change->id);

        $this->trackerService->recordActivity(
            $tracker,
            'client_offer.customer_changes_requested',
            "Cliente solicito cambios: {$reason}",
            $quotation->commercial_phase,
            'client_offer_change_requests',
            $change->id,
            $oldStatus,
            $quotation->status,
            $user
        );

        ActivityLog::log('ventas', 'client_offer.customer_changes_requested', 'Cliente solicito cambios', [
            'quote_id' => $quotation->id,
            'change_request_id' => $change->id,
            'change_type' => $changeType,
        ]);
    }

    public function recordReopenedForRevision(QuotationRequest $quotation, User $user, string $oldStatus, string $message): void
    {
        $tracker = $this->trackerService->trackerForQuotation($quotation);
        $this->trackerService->recordActivity(
            $tracker,
            'client_offer.reopened_for_revision',
            $message,
            $quotation->commercial_phase,
            'quotation_requests',
            $quotation->id,
            $oldStatus,
            $quotation->status,
            $user
        );
    }
}
