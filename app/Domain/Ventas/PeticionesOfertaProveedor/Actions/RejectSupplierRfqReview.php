<?php

namespace App\Domain\Ventas\PeticionesOfertaProveedor\Actions;

use App\Models\ActivityLog;
use App\Models\SupplierRfqDocument;
use App\Models\User;
use App\Services\OrderTrackerService;
use App\Services\SalesWorkflowService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RejectSupplierRfqReview
{
    public function __construct(private readonly OrderTrackerService $trackerService)
    {
    }

    public function execute(SupplierRfqDocument $document, User $admin, string $reason): SupplierRfqDocument
    {
        if (blank($reason)) {
            throw ValidationException::withMessages(['reviewReason' => 'El motivo es obligatorio.']);
        }

        return DB::transaction(function () use ($document, $admin, $reason) {
            $document = SupplierRfqDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();

            if ($document->status !== SupplierRfqDocument::STATUS_PENDING_ADMIN_REVIEW) {
                throw ValidationException::withMessages(['rfq' => 'Este RFQ no esta pendiente de revision admin.']);
            }

            $oldStatus = $document->status;
            $newStatus = SupplierRfqDocument::STATUS_REJECTED;
            $document->forceFill([
                'status' => $newStatus,
                'admin_reviewed_by' => $admin->id,
                'admin_reviewed_at' => now(),
                'admin_review_notes' => $reason,
            ])->save();

            $action = 'rfq.rejected';
            $message = 'Administrador rechazo RFQ';
            $fullMessage = "{$message}: {$reason}";

            $this->recordActivity($document, $admin, $action, $fullMessage, $oldStatus, $newStatus);

            ActivityLog::log('ventas', $action, $message, [
                'quote_id' => $document->quotation_request_id,
                'supplier_id' => $document->supplier_id,
                'rfq_document_id' => $document->id,
            ]);

            return $document->fresh(['quotationRequest.items', 'supplier', 'adminReviewer', 'generator']);
        });
    }

    private function recordActivity(SupplierRfqDocument $document, User $user, string $action, string $message, ?string $oldStatus, ?string $newStatus): void
    {
        $quotation = $document->quotationRequest;
        if (! $quotation) {
            return;
        }

        $tracker = $this->trackerService->trackerForQuotation($quotation);
        if ($tracker) {
            $tracker->update([
                'operational_phase' => SalesWorkflowService::PHASE_SUPPLIER_BID,
                'operational_status' => $newStatus ?: $document->status,
            ]);
            $this->trackerService->recordActivity(
                $tracker,
                $action,
                $message,
                SalesWorkflowService::PHASE_SUPPLIER_BID,
                'supplier_rfq_documents',
                $document->id,
                $oldStatus,
                $newStatus,
                $user
            );
        }
    }
}
