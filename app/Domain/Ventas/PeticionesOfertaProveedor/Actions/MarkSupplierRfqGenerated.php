<?php

namespace App\Domain\Ventas\PeticionesOfertaProveedor\Actions;

use App\Domain\Ventas\PeticionesOfertaProveedor\Support\SupplierRfqValidator;
use App\Models\SupplierRfqDocument;
use App\Models\User;
use App\Services\OrderTrackerService;
use App\Services\SalesWorkflowService;
use Illuminate\Support\Facades\DB;

class MarkSupplierRfqGenerated
{
    public function __construct(private readonly OrderTrackerService $trackerService)
    {
    }

    public function execute(SupplierRfqDocument $document, string $path, User $user): SupplierRfqDocument
    {
        return DB::transaction(function () use ($document, $path, $user) {
            $document = SupplierRfqDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();

            SupplierRfqValidator::assertReadyForOfficialRfq($document);

            $oldStatus = $document->status;
            $newStatus = SupplierRfqDocument::STATUS_GENERATED;
            $document->forceFill([
                'status' => $newStatus,
                'pdf_path' => $path,
                'generated_by' => $user->id,
                'generated_at' => now(),
            ])->save();

            $this->recordActivity(
                $document,
                $user,
                'rfq.pdf_generated',
                'PDF RFQ generado para '.($document->supplier?->name ?? 'Sin proveedor especifico'),
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
