<?php

namespace App\Domain\Ventas\PeticionesOfertaProveedor\Services;

use App\Domain\Ventas\PeticionesOfertaProveedor\Actions\ApproveSupplierRfqReview;
use App\Domain\Ventas\PeticionesOfertaProveedor\Actions\MarkSupplierRfqGenerated;
use App\Domain\Ventas\PeticionesOfertaProveedor\Actions\MarkSupplierRfqSent;
use App\Domain\Ventas\PeticionesOfertaProveedor\Actions\RejectSupplierRfqReview;
use App\Domain\Ventas\PeticionesOfertaProveedor\Actions\RequestSupplierRfqChanges;
use App\Domain\Ventas\PeticionesOfertaProveedor\Actions\RequestSupplierRfqReview;
use App\Domain\Ventas\PeticionesOfertaProveedor\Actions\SaveSupplierRfqDraft;
use App\Domain\Ventas\PeticionesOfertaProveedor\Support\SupplierRfqValidator;
use App\Models\Entity;
use App\Models\QuotationRequest;
use App\Models\SupplierRfqDocument;
use App\Models\User;

class SupplierRfqReviewService
{
    public function __construct(
        private readonly SaveSupplierRfqDraft $saveDraftAction,
        private readonly RequestSupplierRfqReview $requestReviewAction,
        private readonly ApproveSupplierRfqReview $approveAction,
        private readonly RequestSupplierRfqChanges $requestChangesAction,
        private readonly RejectSupplierRfqReview $rejectAction,
        private readonly MarkSupplierRfqGenerated $markGeneratedAction,
        private readonly MarkSupplierRfqSent $markSentAction
    ) {
    }

    public function saveDraft(QuotationRequest $quotation, ?Entity $supplier, array $data, User $user): SupplierRfqDocument
    {
        return $this->saveDraftAction->execute($quotation, $supplier, $data, $user);
    }

    public function requestReview(QuotationRequest $quotation, ?Entity $supplier, array $data, User $user): SupplierRfqDocument
    {
        return $this->requestReviewAction->execute($quotation, $supplier, $data, $user);
    }

    public function approve(SupplierRfqDocument $document, User $admin, ?string $notes = null): SupplierRfqDocument
    {
        return $this->approveAction->execute($document, $admin, $notes);
    }

    public function requestChanges(SupplierRfqDocument $document, User $admin, string $reason): SupplierRfqDocument
    {
        return $this->requestChangesAction->execute($document, $admin, $reason);
    }

    public function reject(SupplierRfqDocument $document, User $admin, string $reason): SupplierRfqDocument
    {
        return $this->rejectAction->execute($document, $admin, $reason);
    }

    public function markGenerated(SupplierRfqDocument $document, string $path, User $user): SupplierRfqDocument
    {
        return $this->markGeneratedAction->execute($document, $path, $user);
    }

    public function markSent(SupplierRfqDocument $document, User $user): SupplierRfqDocument
    {
        return $this->markSentAction->execute($document, $user);
    }

    public function assertAllItemsHaveSentRfq(QuotationRequest $quotation): void
    {
        SupplierRfqValidator::assertAllItemsHaveSentRfq($quotation);
    }

    public function assertReadyForOfficialRfq(SupplierRfqDocument $document): void
    {
        SupplierRfqValidator::assertReadyForOfficialRfq($document);
    }
}
