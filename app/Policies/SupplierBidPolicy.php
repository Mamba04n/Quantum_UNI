<?php

namespace App\Policies;

use App\Models\QuotationRequest;
use App\Models\User;

class SupplierBidPolicy
{
    public function view(User $user, QuotationRequest $bid): bool
    {
        return $user->can('supplier_bid.view');
    }

    public function create(User $user): bool
    {
        return $user->can('supplier_bid.create');
    }

    public function addSupplierResponse(User $user, QuotationRequest $bid): bool
    {
        return $user->can('supplier_bid.add_supplier_response')
            && in_array($bid->status, [
                QuotationRequest::STATUS_RECEIVED,
                QuotationRequest::STATUS_PENDING_BID,
                QuotationRequest::STATUS_RFQ_GENERATED,
                QuotationRequest::STATUS_SENT_TO_SUPPLIER,
                QuotationRequest::STATUS_SUPPLIER_RESPONSE_RECEIVED,
            ], true);
    }

    public function editSupplierResponse(User $user, QuotationRequest $bid): bool
    {
        return $user->can('supplier_bid.edit_supplier_response')
            && in_array($bid->status, [
                QuotationRequest::STATUS_PENDING_BID,
                QuotationRequest::STATUS_RFQ_GENERATED,
                QuotationRequest::STATUS_SENT_TO_SUPPLIER,
                QuotationRequest::STATUS_SUPPLIER_RESPONSE_RECEIVED,
            ], true);
    }

    public function deleteSupplierResponse(User $user, QuotationRequest $bid): bool
    {
        return $user->can('supplier_bid.delete_supplier_response')
            && in_array($bid->status, [
                QuotationRequest::STATUS_PENDING_BID,
                QuotationRequest::STATUS_SUPPLIER_RESPONSE_RECEIVED,
            ], true);
    }

    public function selectWinner(User $user, QuotationRequest $bid): bool
    {
        return $user->can('supplier_bid.select_winner')
            && in_array($bid->status, [
                QuotationRequest::STATUS_SUPPLIER_RESPONSE_RECEIVED,
                QuotationRequest::STATUS_SUPPLIER_SELECTED,
            ], true);
    }

    public function sendToApproval(User $user, QuotationRequest $bid): bool
    {
        return $user->can('supplier_bid.send_to_approval')
            && in_array($bid->status, [
                QuotationRequest::STATUS_SUPPLIER_SELECTED,
                QuotationRequest::STATUS_CLIENT_OFFER_GENERATED,
                QuotationRequest::STATUS_CLIENT_OFFER_SENT,
            ], true);
    }

    public function close(User $user, QuotationRequest $bid): bool
    {
        return $user->can('supplier_bid.close')
            && ! in_array($bid->status, [
                QuotationRequest::STATUS_APPROVED_TOTAL,
                QuotationRequest::STATUS_CANCELLED,
            ], true);
    }

    public function viewCosts(User $user, QuotationRequest $bid): bool
    {
        return $user->can('supplier_bid.view_costs');
    }

    public function viewSupplierDocuments(User $user, QuotationRequest $bid): bool
    {
        return $user->can('supplier_bid.view_supplier_documents');
    }
}
