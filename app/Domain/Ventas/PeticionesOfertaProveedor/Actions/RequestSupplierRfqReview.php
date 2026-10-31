<?php

namespace App\Domain\Ventas\PeticionesOfertaProveedor\Actions;

use App\Domain\Ventas\PeticionesOfertaProveedor\Support\SupplierRfqValidator;
use App\Models\ActivityLog;
use App\Models\Entity;
use App\Models\QuotationRequest;
use App\Models\SupplierRfqDocument;
use App\Models\User;
use App\Services\OrderTrackerService;
use App\Services\SalesWorkflowService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequestSupplierRfqReview
{
    public function __construct(
        private readonly OrderTrackerService $trackerService,
        private readonly SaveSupplierRfqDraft $saveDraftAction
    ) {
    }

    public function execute(QuotationRequest $quotation, ?Entity $supplier, array $data, User $user): SupplierRfqDocument
    {
        return DB::transaction(function () use ($quotation, $supplier, $data, $user) {
            $supplierLabel = $supplier?->name ?? 'Sin proveedor especifico';
            $document = $this->saveDraftAction->execute($quotation, $supplier, $data, $user);
            $document = SupplierRfqDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();

            if (! in_array($document->status, [
                SupplierRfqDocument::STATUS_DRAFT,
                SupplierRfqDocument::STATUS_ADMIN_CHANGES_REQUESTED,
            ], true)) {
                throw ValidationException::withMessages(['rfq' => 'Este RFQ no puede enviarse a revision en su estado actual.']);
            }

            SupplierRfqValidator::assertReadyForOfficialRfq($document);

            $oldStatus = $document->status;
            $document->forceFill([
                'status' => SupplierRfqDocument::STATUS_PENDING_ADMIN_REVIEW,
                'admin_review_notes' => null,
            ])->save();

            $tracker = $this->trackerService->trackerForQuotation($quotation);
            if ($tracker) {
                $tracker->update([
                    'operational_phase' => SalesWorkflowService::PHASE_SUPPLIER_BID,
                    'operational_status' => SupplierRfqDocument::STATUS_PENDING_ADMIN_REVIEW,
                ]);

                $this->trackerService->addReference($tracker, 'supplier_rfq', 'RFQ-'.$document->id, 'supplier_rfq_documents', $document->id);
                $this->trackerService->recordActivity(
                    $tracker,
                    'rfq.review_requested',
                    "RFQ enviado a revision del administrador para {$supplierLabel}",
                    SalesWorkflowService::PHASE_SUPPLIER_BID,
                    'supplier_rfq_documents',
                    $document->id,
                    $oldStatus,
                    SupplierRfqDocument::STATUS_PENDING_ADMIN_REVIEW,
                    $user
                );
            }

            ActivityLog::log('ventas', 'rfq.review_requested', "RFQ enviado a revision admin para {$supplierLabel}", [
                'quote_id' => $quotation->id,
                'supplier_id' => $supplier?->id,
                'rfq_document_id' => $document->id,
            ]);

            return $document->fresh(['quotationRequest.items', 'supplier', 'adminReviewer', 'generator']);
        });
    }
}
