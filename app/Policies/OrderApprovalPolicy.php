<?php

namespace App\Policies;

use App\Models\ApprovalRequest;
use App\Models\User;

class OrderApprovalPolicy
{
    public function view(User $user, ApprovalRequest $approval): bool
    {
        return $user->can('order_approval.view');
    }

    public function approve(User $user, ApprovalRequest $approval): bool
    {
        return $user->can('order_approval.approve')
            && $approval->status === ApprovalRequest::STATUS_PENDING;
    }

    public function reject(User $user, ApprovalRequest $approval): bool
    {
        return $user->can('order_approval.reject')
            && $approval->status === ApprovalRequest::STATUS_PENDING;
    }

    public function partialApprove(User $user, ApprovalRequest $approval): bool
    {
        return $user->can('order_approval.partial_approve')
            && $approval->status === ApprovalRequest::STATUS_PENDING;
    }

    public function requestChanges(User $user, ApprovalRequest $approval): bool
    {
        return $user->can('order_approval.request_changes')
            && $approval->status === ApprovalRequest::STATUS_PENDING;
    }

    public function adjustQuantity(User $user, ApprovalRequest $approval): bool
    {
        return $user->can('order_approval.adjust_quantity')
            && $approval->status === ApprovalRequest::STATUS_PENDING;
    }

    public function viewCosts(User $user, ApprovalRequest $approval): bool
    {
        return $user->can('order_approval.view_costs');
    }

    public function sendToOrder(User $user, ApprovalRequest $approval): bool
    {
        return $user->can('order_approval.send_to_order')
            && $approval->status === ApprovalRequest::STATUS_APPROVED;
    }
}
