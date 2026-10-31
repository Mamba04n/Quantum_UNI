<?php

namespace App\Domain\Administracion\Approvals\Support;

use App\Models\ApprovalRequest;
use App\Models\QuotationRequest;
use App\Models\SaleOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ApprovalHelper
{
    public static function activeKey(QuotationRequest $quotation): string
    {
        return 'quotation_requests:'.$quotation->id.':internal_purchase';
    }

    public static function nextApprovalCode(): string
    {
        $count = ApprovalRequest::whereYear('created_at', now()->year)->count() + 1;

        return 'APV-'.now()->year.'-'.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    public static function existingOrderForApproval(ApprovalRequest $approval): ?SaleOrder
    {
        if (! Schema::hasColumn('sale_orders', 'approval_request_id')) {
            return null;
        }

        return SaleOrder::where('approval_request_id', $approval->id)->first();
    }

    public static function existingOrderForQuotation(QuotationRequest $quotation): ?SaleOrder
    {
        if ($quotation->sale_order_id) {
            return SaleOrder::find($quotation->sale_order_id);
        }

        $offer = $quotation->offerVersions()->latest('version_number')->first();

        return $offer ? SaleOrder::where('client_offer_version_id', $offer->id)->first() : null;
    }

    public static function existingPurchaseOrdersForApproval(ApprovalRequest $approval): Collection
    {
        if (! Schema::hasColumn('purchase_orders', 'approval_request_id')) {
            return collect();
        }

        return \App\Models\PurchaseOrder::where('approval_request_id', $approval->id)->get();
    }

    public static function pendingOrApprovedForQuotation(QuotationRequest $quotation): ?ApprovalRequest
    {
        return ApprovalRequest::with(['tracker', 'requester', 'approver'])
            ->where('source_module', 'quotation_requests')
            ->where('source_id', $quotation->id)
            ->whereIn('status', [ApprovalRequest::STATUS_PENDING, ApprovalRequest::STATUS_APPROVED])
            ->latest()
            ->first();
    }
}
