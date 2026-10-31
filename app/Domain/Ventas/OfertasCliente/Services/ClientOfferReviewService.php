<?php

namespace App\Domain\Ventas\OfertasCliente\Services;

use App\Domain\Ventas\OfertasCliente\Actions\ApproveClientOfferReview;
use App\Domain\Ventas\OfertasCliente\Actions\MarkClientOfferSentToCustomer;
use App\Domain\Ventas\OfertasCliente\Actions\RejectClientOfferReview;
use App\Domain\Ventas\OfertasCliente\Actions\RequestClientOfferChanges;
use App\Domain\Ventas\OfertasCliente\Actions\RequestClientOfferReview;
use App\Domain\Ventas\OfertasCliente\Actions\RegisterCustomerChangeRequest;
use App\Domain\Ventas\OfertasCliente\Support\ClientOfferValidator;
use App\Models\ClientOfferChangeRequest;
use App\Models\ClientOfferReview;
use App\Models\QuotationRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;

class ClientOfferReviewService
{
    public function __construct(
        private readonly RequestClientOfferReview $requestReviewAction,
        private readonly ApproveClientOfferReview $approveAction,
        private readonly RequestClientOfferChanges $requestChangesAction,
        private readonly RejectClientOfferReview $rejectAction,
        private readonly MarkClientOfferSentToCustomer $markSentAction,
        private readonly RegisterCustomerChangeRequest $registerChangeAction,
        private readonly ClientOfferValidator $validator
    ) {
    }

    public function requestReview(QuotationRequest $quotation, User $user, ?string $notes = null): ClientOfferReview
    {
        return $this->requestReviewAction->execute($quotation, $user, $notes);
    }

    public function approve(ClientOfferReview $review, User $admin, ?string $notes = null): ClientOfferReview
    {
        return $this->approveAction->execute($review, $admin, $notes);
    }

    public function requestChanges(ClientOfferReview $review, User $admin, string $reason): ClientOfferReview
    {
        return $this->requestChangesAction->execute($review, $admin, $reason);
    }

    public function reject(ClientOfferReview $review, User $admin, string $reason): ClientOfferReview
    {
        return $this->rejectAction->execute($review, $admin, $reason);
    }

    public function markSentToCustomer(QuotationRequest $quotation, User $user): QuotationRequest
    {
        return $this->markSentAction->execute($quotation, $user);
    }

    public function registerCustomerChangeRequest(
        QuotationRequest $quotation,
        User $user,
        string $changeType,
        string $reason,
        ?string $comment = null,
        array $affectedItems = [],
        ?UploadedFile $attachment = null
    ): ClientOfferChangeRequest {
        return $this->registerChangeAction->execute(
            $quotation,
            $user,
            $changeType,
            $reason,
            $comment,
            $affectedItems,
            $attachment
        );
    }

    public function assertOfferComplete(QuotationRequest $quotation): void
    {
        $this->validator->assertOfferComplete($quotation);
    }
}
