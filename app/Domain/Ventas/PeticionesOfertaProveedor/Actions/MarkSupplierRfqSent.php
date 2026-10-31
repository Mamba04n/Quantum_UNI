<?php

namespace App\Domain\Ventas\PeticionesOfertaProveedor\Actions;

use App\Models\SupplierRfqDocument;
use App\Models\User;
use App\Services\OrderTrackerService;
use App\Services\SalesWorkflowService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarkSupplierRfqSent
{
    public function __construct(private readonly OrderTrackerService $trackerService)
    {
    }

    public function execute(SupplierRfqDocument $document, User $user): SupplierRfqDocument
    {
        return DB::transaction(function () use ($document, $user) {
            $document = SupplierRfqDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();

            if ($document->status !== SupplierRfqDocument::STATUS_GENERATED) {
                throw ValidationException::withMessages(['rfq' => 'Primero genera el PDF RFQ para este proveedor.']);
            }

            if (blank($document->pdf_path)) {
                throw ValidationException::withMessages(['rfq' => 'No existe PDF RFQ para enviar al proveedor.']);
            }

            $oldStatus = $document->status;
            $newStatus = SupplierRfqDocument::STATUS_SENT_TO_SUPPLIER;
            $document->forceFill([
                'status' => $newStatus,
                'sent_at' => now(),
            ])->save();

            $this->recordActivity(
                $document,
                $user,
                'rfq.sent_to_supplier',
                'RFQ enviado a proveedor '.($document->supplier?->name ?? 'Sin proveedor especifico'),
                $oldStatus,
                $newStatus
            );

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
