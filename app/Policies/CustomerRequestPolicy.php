<?php

namespace App\Policies;

use App\Models\QuotationRequest;
use App\Models\User;

class CustomerRequestPolicy
{
    public function view(User $user, QuotationRequest $request): bool
    {
        return $user->can('customer_request.view')
            || ($user->can('customer_request.view_own') && $request->user_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->can('customer_request.create');
    }

    public function update(User $user, QuotationRequest $request): bool
    {
        // Sólo editable mientras está en fase 1 (Solicitud Cliente)
        return $user->can('customer_request.edit')
            && in_array($request->status, [
                QuotationRequest::STATUS_RECEIVED,
                QuotationRequest::STATUS_IN_REVIEW,
            ], true);
    }

    public function cancel(User $user, QuotationRequest $request): bool
    {
        // No se puede cancelar si ya fue aprobada o convertida a orden
        return $user->can('customer_request.cancel')
            && ! in_array($request->status, [
                QuotationRequest::STATUS_APPROVED_TOTAL,
                QuotationRequest::STATUS_APPROVED_PARTIAL,
                QuotationRequest::STATUS_CANCELLED,
            ], true);
    }

    public function markInReview(User $user, QuotationRequest $request): bool
    {
        return $user->can('customer_request.mark_in_review')
            && $request->status === QuotationRequest::STATUS_RECEIVED;
    }

    public function markReadyForBid(User $user, QuotationRequest $request): bool
    {
        return $user->can('customer_request.mark_ready_for_bid')
            && in_array($request->status, [
                QuotationRequest::STATUS_RECEIVED,
                QuotationRequest::STATUS_IN_REVIEW,
            ], true);
    }

    public function sendToBid(User $user, QuotationRequest $request): bool
    {
        return $user->can('customer_request.send_to_bid')
            && in_array($request->status, [
                QuotationRequest::STATUS_IN_REVIEW,
                QuotationRequest::STATUS_READY_FOR_BID,
            ], true);
    }
}
