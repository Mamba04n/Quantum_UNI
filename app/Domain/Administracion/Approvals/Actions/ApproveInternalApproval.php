<?php

namespace App\Domain\Administracion\Approvals\Actions;

use App\Domain\Administracion\Approvals\Support\ApprovalHelper;
use App\Models\ActivityLog;
use App\Models\ApprovalRequest;
use App\Models\PurchaseOrder;
use App\Models\QuotationRequest;
use App\Models\SaleOrder;
use App\Models\User;
use App\Services\ApprovedOrderReceivableService;
use App\Services\ConvertQuotationToSaleOrderService;
use App\Services\OrderStatusService;
use App\Services\OrderTrackerService;
use App\Services\PurchaseOrderDocumentService;
use App\Services\PurchaseOrderService;
use App\Services\SalesWorkflowService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ApproveInternalApproval
{
    public function __construct(
        private readonly OrderTrackerService $trackerService,
        private readonly ConvertQuotationToSaleOrderService $converter,
        private readonly OrderStatusService $orderStatusService,
        private readonly PurchaseOrderService $purchaseOrderService,
        private readonly PurchaseOrderDocumentService $purchaseOrderDocumentService,
        private readonly ApprovedOrderReceivableService $approvedOrderReceivableService
    ) {}

    public function execute(ApprovalRequest $approval, User $user): SaleOrder
    {
        if (! $user->can('internal_approval.approve') && ! $user->can('order_approval.approve') && ! $user->can('approval.approve')) {
            throw ValidationException::withMessages(['approval' => 'No tienes permiso para aprobar.']);
        }

        return DB::transaction(function () use ($approval, $user) {
            $approval = ApprovalRequest::whereKey($approval->id)->lockForUpdate()->firstOrFail();

            $approvalType = Schema::hasColumn('approval_requests', 'approval_type')
                ? ($approval->approval_type ?: $approval->request_type)
                : $approval->request_type;

            if ($approvalType !== ApprovalRequest::TYPE_INTERNAL_PURCHASE) {
                throw ValidationException::withMessages(['approval' => 'Solo las aprobaciones internas generan compra o pedido.']);
            }

            if ($approval->status !== ApprovalRequest::STATUS_PENDING) {
                $quotation = QuotationRequest::whereKey($approval->source_id)->first();
                $existing = ApprovalHelper::existingOrderForApproval($approval) ?: ($quotation ? ApprovalHelper::existingOrderForQuotation($quotation) : null);
                if ($existing) {
                    if ($quotation && $approval->status === ApprovalRequest::STATUS_APPROVED) {
                        $this->ensureReceivableWhenPurchased($existing, $quotation, $approval, $user);
                    }

                    return $existing->fresh(['items']);
                }

                throw ValidationException::withMessages(['approval' => 'Esta solicitud ya fue procesada.']);
            }

            $quotation = QuotationRequest::with([
                'items.supplierBidSelection',
                'supplierBidSelections',
                'offerVersions.items',
            ])
                ->whereKey($approval->source_id)
                ->lockForUpdate()
                ->firstOrFail();

            $preGeneratedPurchaseOrders = ApprovalHelper::existingPurchaseOrdersForApproval($approval);
            if ($preGeneratedPurchaseOrders->isNotEmpty()) {
                return $this->approveGeneratedPurchaseOrders($approval, $quotation, $preGeneratedPurchaseOrders, $user);
            }

            if ($quotation->status !== OrderTrackerService::QUOTE_PENDING_APPROVAL) {
                throw ValidationException::withMessages(['approval' => 'La oferta no esta pendiente de aprobacion interna.']);
            }

            $existing = ApprovalHelper::existingOrderForApproval($approval) ?: ApprovalHelper::existingOrderForQuotation($quotation);
            if ($existing) {
                $approval->forceFill([
                    'status' => ApprovalRequest::STATUS_APPROVED,
                    'approved_by' => $user->id,
                    'approved_at' => now(),
                ])->save();

                $this->ensureReceivableWhenPurchased($existing, $quotation, $approval, $user);

                return $existing->fresh(['items']);
            }

            $approvedItems = $quotation->items->where('is_approved', true);

            if ($approvedItems->isEmpty()) {
                throw ValidationException::withMessages(['approval' => 'No hay productos aprobados por el cliente para generar orden.']);
            }

            if (! $quotation->client_offer_pdf_path && $quotation->offerVersions->isEmpty()) {
                throw ValidationException::withMessages(['approval' => 'Debe existir oferta PDF o version de oferta antes de aprobar internamente.']);
            }

            $quotation->forceFill([
                'status' => QuotationRequest::STATUS_APPROVED_TOTAL,
                'commercial_phase' => SalesWorkflowService::PHASE_APPROVAL,
            ])->save();

            $approval->forceFill([
                'status' => ApprovalRequest::STATUS_APPROVED,
                'approved_by' => $user->id,
                'approved_at' => now(),
            ])->save();

            $order = $this->converter->convert($quotation->fresh(['items', 'offerVersions.items']), $user);

            if (Schema::hasColumn('sale_orders', 'approval_request_id')) {
                $order->forceFill(['approval_request_id' => $approval->id])->save();
            }

            $orderStatus = $this->orderStatusService->ensureForSaleOrder($order->fresh('items'), $user);

            $tracker = $approval->tracker ?: $this->trackerService->trackerForQuotation($quotation);
            if ($tracker) {
                $this->trackerService->recordActivity(
                    $tracker,
                    'internal_approval.approved',
                    "Aprobacion interna aprobada por {$user->name}",
                    'aprobacion_interna',
                    'approval_requests',
                    $approval->id,
                    OrderTrackerService::QUOTE_PENDING_APPROVAL,
                    ApprovalRequest::STATUS_APPROVED,
                    $user
                );

                $this->trackerService->recordActivity(
                    $tracker,
                    'foreign_order.generated_from_approval',
                    'Orden '.$order->reference.' generada desde aprobacion interna '.($approval->approval_code ?: '#'.$approval->id),
                    'order_generation',
                    'sale_orders',
                    $order->id,
                    null,
                    $order->status,
                    $user
                );
            }

            $purchaseOrders = collect();
            if ($quotation->supplierBidSelections->isNotEmpty()) {
                $existingPurchaseOrders = ApprovalHelper::existingPurchaseOrdersForApproval($approval);
                $purchaseOrders = $existingPurchaseOrders->isNotEmpty()
                    ? $existingPurchaseOrders
                    : $this->purchaseOrderService->createOrdersFromQuotationSelections($quotation->fresh(['items', 'supplierBidSelections.responseItem.response.supplier']), $user, $approval);
            }

            ActivityLog::log('approval', 'internal_approval.approved', "Aprobacion interna aprobada por {$user->name}.", [
                'approval_id' => $approval->id,
                'quote_id' => $quotation->id,
                'order_id' => $order->id,
            ]);

            ActivityLog::log('ventas', 'foreign_order.generated_from_approval', "Orden {$order->reference} generada desde aprobacion interna.", [
                'approval_id' => $approval->id,
                'quote_id' => $quotation->id,
                'order_id' => $order->id,
                'order_status_id' => $orderStatus->id,
            ]);

            if ($purchaseOrders->isNotEmpty()) {
                $this->approvedOrderReceivableService->ensure($order, $quotation, $user, $tracker);
                $this->generatePurchaseOrderDocumentsAfterCommit($approval->id, $purchaseOrders->pluck('id')->all(), $user->id);

                ActivityLog::log('compras', 'purchase_order.generated_from_approval', 'Orden de compra generada desde aprobacion interna.', [
                    'approval_id' => $approval->id,
                    'quote_id' => $quotation->id,
                    'purchase_order_ids' => $purchaseOrders->pluck('id')->all(),
                ]);
            }

            return $order->fresh(['items']);
        });
    }

    private function approveGeneratedPurchaseOrders(ApprovalRequest $approval, QuotationRequest $quotation, $purchaseOrders, User $user): SaleOrder
    {
        $order = ApprovalHelper::existingOrderForApproval($approval) ?: ApprovalHelper::existingOrderForQuotation($quotation);

        if (! $order) {
            $quotation->forceFill([
                'status' => QuotationRequest::STATUS_APPROVED_TOTAL,
                'commercial_phase' => SalesWorkflowService::PHASE_APPROVAL,
            ])->save();

            $order = $this->converter->convert($quotation->fresh(['items', 'offerVersions.items']), $user);
        }

        if (Schema::hasColumn('sale_orders', 'approval_request_id')) {
            $order->forceFill(['approval_request_id' => $approval->id])->save();
        }

        foreach ($purchaseOrders as $purchaseOrder) {
            if (! $purchaseOrder->sale_order_id) {
                $purchaseOrder->forceFill(['sale_order_id' => $order->id])->save();
            }

            $this->orderStatusService->syncPurchaseOrder($purchaseOrder->fresh('items'), $user);
        }

        $approvalData = [
            'status' => ApprovalRequest::STATUS_APPROVED,
            'approved_by' => $user->id,
            'approved_at' => now(),
        ];

        if (Schema::hasColumn('approval_requests', 'active_key')) {
            $approvalData['active_key'] = null;
        }

        $approval->forceFill($approvalData)->save();

        $tracker = $approval->tracker ?: $this->trackerService->trackerForQuotation($quotation);
        if ($tracker) {
            $tracker->update([
                'operational_phase' => 'purchase_order',
                'operational_status' => ApprovalRequest::STATUS_APPROVED,
            ]);

            $this->trackerService->recordActivity(
                $tracker,
                'purchase_order.approved_after_generation',
                "Orden de compra generada aprobada por {$user->name}.",
                'aprobacion_interna',
                'approval_requests',
                $approval->id,
                ApprovalRequest::STATUS_PENDING,
                ApprovalRequest::STATUS_APPROVED,
                $user
            );
        }

        ActivityLog::log('approval', 'purchase_order.approved_after_generation', "OC generada aprobada por {$user->name}.", [
            'approval_id' => $approval->id,
            'quote_id' => $quotation->id,
            'order_id' => $order->id,
            'purchase_order_ids' => $purchaseOrders->pluck('id')->all(),
        ]);

        if ($purchaseOrders->isNotEmpty()) {
            $this->approvedOrderReceivableService->ensure($order, $quotation, $user, $tracker);
            $this->generatePurchaseOrderDocumentsAfterCommit($approval->id, $purchaseOrders->pluck('id')->all(), $user->id);
        }

        return $order->fresh(['items']);
    }

    private function ensureReceivableWhenPurchased(
        SaleOrder $order,
        QuotationRequest $quotation,
        ApprovalRequest $approval,
        User $user
    ): void {
        if (! PurchaseOrder::query()->where('sale_order_id', $order->id)->exists()) {
            return;
        }

        $tracker = $approval->tracker ?: $this->trackerService->trackerForQuotation($quotation);
        $this->approvedOrderReceivableService->ensure($order, $quotation, $user, $tracker);
    }

    /** @param array<int, int> $purchaseOrderIds */
    private function generatePurchaseOrderDocumentsAfterCommit(int $approvalId, array $purchaseOrderIds, int $userId): void
    {
        DB::afterCommit(function () use ($approvalId, $purchaseOrderIds, $userId): void {
            $approval = ApprovalRequest::with('tracker')->find($approvalId);
            $user = User::find($userId);

            if (! $approval || ! $user) {
                Log::warning('purchase_order.documents.skipped', [
                    'approval_id' => $approvalId,
                    'purchase_order_ids' => $purchaseOrderIds,
                    'reason' => 'approval_or_user_missing',
                ]);

                return;
            }

            foreach (PurchaseOrder::whereKey($purchaseOrderIds)->get() as $purchaseOrder) {
                try {
                    $document = $this->purchaseOrderDocumentService->documentForPurchaseOrder($approval, $purchaseOrder);

                    if (blank($document->pdf_path)) {
                        $this->purchaseOrderDocumentService->generatePdf($document, $user);
                    }
                } catch (\Throwable $exception) {
                    Log::error('purchase_order.document_generation_failed', [
                        'approval_id' => $approvalId,
                        'purchase_order_id' => $purchaseOrder->id,
                        'exception' => $exception::class,
                    ]);
                }
            }
        });
    }
}
