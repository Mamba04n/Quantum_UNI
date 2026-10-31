<?php

namespace App\Domain\Ventas\LicitacionesProveedor\Support;

use App\Models\Entity;
use App\Models\QuotationRequest;
use App\Models\SupplierBidResponse;
use App\Models\User;
use App\Services\OrderTrackerService;
use App\Services\SalesWorkflowService;

class SupplierBidResponseTracker
{
    public function __construct(
        private readonly OrderTrackerService $trackerService,
        private readonly SupplierBidActivity $activity
    ) {
    }

    public function trackerFor(QuotationRequest $request): mixed
    {
        return $this->trackerService->trackerForQuotation($request);
    }

    public function attachDocuments(mixed $tracker, SupplierBidResponse $response, Entity $supplier, QuotationRequest $request, array $attachments, bool $logGlobal = false): void
    {
        foreach ($attachments as $attachment) {
            $attached = $this->trackerService->addAttachment(
                $tracker,
                $attachment,
                SalesWorkflowService::PHASE_SUPPLIER_BID,
                "Documento proveedor {$supplier->name}",
                null,
                'supplier_bid_responses',
                $response->id
            );

            $response->forceFill([
                'attachment_path' => $attached->file_path,
                'supplier_document_path' => $attached->file_path,
            ])->save();

            if ($logGlobal) {
                $this->activity->logGlobal('supplier_bid.document_attached', "Documento proveedor adjuntado para {$supplier->name}", [
                    'quote_id' => $request->id,
                    'supplier_bid_response_id' => $response->id,
                    'attachment_id' => $attached->id,
                ]);
            }
        }
    }

    public function markRequestPendingResponse(QuotationRequest $request): void
    {
        $requestStatus = in_array($request->status, [
            QuotationRequest::STATUS_SUPPLIER_RESPONSE_RECEIVED,
            QuotationRequest::STATUS_SUPPLIER_SELECTED,
        ], true)
            ? $request->status
            : QuotationRequest::STATUS_SUPPLIER_RESPONSE_PENDING;

        $request->forceFill([
            'status' => $requestStatus,
            'commercial_phase' => SalesWorkflowService::PHASE_SUPPLIER_BID,
        ])->save();
    }

    public function syncTrackerToSupplier(mixed $tracker, QuotationRequest $request, Entity $supplier): void
    {
        $tracker->update([
            'operational_phase' => SalesWorkflowService::PHASE_SUPPLIER_BID,
            'operational_status' => $request->status,
            'supplier_id' => $supplier->id,
        ]);
    }

    public function addResponseReference(mixed $tracker, SupplierBidResponse $response, Entity $supplier): void
    {
        $this->trackerService->addReference($tracker, 'supplier_bid_response', $response->response_code ?: $supplier->name, 'supplier_bid_responses', $response->id);
    }

    public function recordActivity(mixed $tracker, string $action, string $description, string $sourceModule, int $sourceId, ?string $oldStatus, ?string $newStatus, User $user): void
    {
        $this->trackerService->recordActivity(
            $tracker,
            $action,
            $description,
            SalesWorkflowService::PHASE_SUPPLIER_BID,
            $sourceModule,
            $sourceId,
            $oldStatus,
            $newStatus,
            $user
        );
    }
}
