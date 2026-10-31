<?php

namespace App\Livewire\Administracion\Concerns;

use App\Models\ApprovalRequest;
use App\Services\OrderTrackerService;
use Illuminate\Support\Facades\Schema;

trait ManagesApprovalList
{
    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user && (
            $this->canAny(['internal_approval.view', 'approval.view', 'order_approval.view', 'dispatch_authorization.view', 'client_offer_review.view', 'rfq_review.view', 'finance.view', 'full_access'])
            || $user->hasRole('admin')
        ), 403);

        $this->selectedApprovalId = null;
        $this->managerSection = match (request()->query('section')) {
            'dispatch-authorizations' => 'dispatch_authorizations',
            'purchase-orders' => 'purchase_orders',
            'stage-changes' => 'stage_changes',
            default => 'overview',
        };
    }

    public function backToInternalList(): void
    {
        $this->selectedApprovalId = null;
        $this->activeTab = 'summary';
    }

    public function selectApproval(int $approvalId): void
    {
        abort_unless($this->canAny(['internal_approval.view', 'approval.view', 'order_approval.view']), 403);

        $approval = $this->approvalListQuery()->whereKey($approvalId)->firstOrFail();
        $this->managerSection = 'purchase_orders';
        $this->selectedApprovalId = $approval->id;
        $this->activeTab = 'summary';
        $this->dispatch('approval-selected');

        if ($approval->tracker) {
            app(OrderTrackerService::class)->recordActivity(
                $approval->tracker,
                'internal_approval.viewed',
                'Aprobacion interna revisada',
                'aprobacion_interna',
                'approval_requests',
                $approval->id,
                null,
                $approval->status,
                auth()->user()
            );
        }
    }

    public function selectedApproval(): ApprovalRequest
    {
        abort_unless($this->selectedApprovalId, 404);

        return ApprovalRequest::with($this->detailRelations())
            ->where(fn ($query) => $this->internalApprovalTypeFilter($query))
            ->findOrFail($this->selectedApprovalId);
    }

    private function approvalListQuery()
    {
        return ApprovalRequest::query()
            ->with([
                'tracker.references',
                'requester.roles',
                'approver',
                'quotationRequest.items',
                'quotationRequest.supplierBidSelections',
            ])
            ->where(fn ($query) => $this->internalApprovalTypeFilter($query));
    }

    private function detailRelations(): array
    {
        return [
            'requester.roles',
            'approver',
            'tracker.customer',
            'tracker.references',
            'tracker.activities.user',
            'tracker.attachments.uploader',
            'tracker.comments.user',
            'quotationRequest.user',
            'quotationRequest.clientMachine',
            'quotationRequest.clientApprovals.approver',
            'quotationRequest.offerVersions.creator',
            'quotationRequest.offerVersions.items.quotationItem',
            'quotationRequest.purchasePreparationItems.quotationItem.supplierBidResponseItems.response.supplier',
            'quotationRequest.purchasePreparationItems.originalSupplier',
            'quotationRequest.purchasePreparationItems.finalSupplier',
            'quotationRequest.purchasePreparationItems.originalSupplierQuote.response.supplier',
            'quotationRequest.purchasePreparationItems.finalSupplierQuote.response.supplier',
            'quotationRequest.items.product',
            'quotationRequest.items.supplierBidResponseItems.response.supplier',
            'quotationRequest.items.clientMachine',
            'quotationRequest.items.selectedSupplier',
            'quotationRequest.items.supplierBidSelection.supplier',
            'quotationRequest.items.supplierBidSelection.responseItem.response.supplier',
            'quotationRequest.supplierBidSelections.supplier',
            'quotationRequest.supplierBidSelections.responseItem.response.supplier',
            'purchaseOrders.supplier',
            'purchaseOrders.items.supplierResponseItem.response.supplier',
            'purchaseOrders.items.quotationItem',
        ];
    }

    private function internalApprovalTypeFilter($query): void
    {
        if (Schema::hasColumn('approval_requests', 'approval_type')) {
            $query->where(function ($typeQuery) {
                $typeQuery->where('approval_type', ApprovalRequest::TYPE_INTERNAL_PURCHASE)
                    ->orWhere('request_type', ApprovalRequest::TYPE_INTERNAL_PURCHASE);
            });

            return;
        }

        $query->where('request_type', ApprovalRequest::TYPE_INTERNAL_PURCHASE);
    }
}
