<?php

namespace App\Domain\Administracion\Approvals\Services;

use App\Domain\Administracion\Approvals\Actions\ApproveInternalApproval;
use App\Domain\Administracion\Approvals\Actions\RegisterClientApproval;
use App\Domain\Administracion\Approvals\Actions\RejectInternalApproval;
use App\Domain\Administracion\Approvals\Actions\RequestChangesInternalApproval;
use App\Domain\Administracion\Approvals\Actions\RequestInternalApproval;
use App\Domain\Administracion\Approvals\Support\ApprovalHelper;
use App\Models\ApprovalRequest;
use App\Models\ClientApproval;
use App\Models\QuotationRequest;
use App\Models\SaleOrder;
use App\Models\User;

class ApprovalService
{
    public function __construct(
        private readonly RegisterClientApproval $registerClientApprovalAction,
        private readonly RequestInternalApproval $requestInternalApprovalAction,
        private readonly ApproveInternalApproval $approveAction,
        private readonly RejectInternalApproval $rejectAction,
        private readonly RequestChangesInternalApproval $requestChangesAction
    ) {
    }

    public function registerClientApproval(QuotationRequest $quotation, User $user, string $decision, ?string $notes = null): ClientApproval
    {
        return $this->registerClientApprovalAction->execute($quotation, $user, $decision, $notes);
    }

    public function requestApproval(QuotationRequest $quotation, User $user, ?string $reason = null): ApprovalRequest
    {
        return $this->requestInternalApprovalAction->execute($quotation, $user, $reason);
    }

    public function requestInternalApproval(QuotationRequest $quotation, User $user, ?string $reason = null): ApprovalRequest
    {
        return $this->requestInternalApprovalAction->execute($quotation, $user, $reason);
    }

    public function approveRequest(ApprovalRequest $approval, User $user): SaleOrder
    {
        return $this->approveAction->execute($approval, $user);
    }

    public function rejectRequest(ApprovalRequest $approval, User $user, string $reason): void
    {
        $this->rejectAction->execute($approval, $user, $reason);
    }

    public function requestChanges(ApprovalRequest $approval, User $user, string $reason): void
    {
        $this->requestChangesAction->execute($approval, $user, $reason);
    }

    public function pendingOrApprovedForQuotation(QuotationRequest $quotation): ?ApprovalRequest
    {
        return ApprovalHelper::pendingOrApprovedForQuotation($quotation);
    }
}
