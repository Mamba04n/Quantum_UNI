<?php

namespace App\Domain\Ventas\PeticionesOfertaProveedor\Actions;

use App\Models\QuotationRequest;
use App\Models\SupplierRfqDocument;
use App\Models\User;
use App\Services\OrderTrackerService;
use App\Services\SalesWorkflowService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DeleteSupplierRfqDocument
{
    public function __construct(private readonly OrderTrackerService $trackerService)
    {
    }

    /**
     * @return array{rfq_number: string, supplier_id: int|null}
     */
    public function execute(int $quotationId, int $documentId, User $user): array
    {
        $result = DB::transaction(function () use ($quotationId, $documentId, $user) {
            $document = SupplierRfqDocument::with(['quotationRequest', 'supplier'])
                ->where('quotation_request_id', $quotationId)
                ->whereKey($documentId)
                ->lockForUpdate()
                ->first();

            if (! $document) {
                throw ValidationException::withMessages(['rfq' => 'No se encontro el RFQ seleccionado.']);
            }

            $quote = $document->quotationRequest;
            $supplierLabel = $document->supplier?->name ?? 'RFQ abierto / sin proveedor';
            $supplierId = $document->supplier_id;
            $rfqNumber = $document->effective_rfq_number;
            $pdfPath = $document->pdf_path;
            $oldStatus = $document->status;

            if ($supplierId && $quote) {
                $otherRfqsForSupplier = SupplierRfqDocument::where('quotation_request_id', $quotationId)
                    ->where('id', '!=', $documentId)
                    ->where('supplier_id', $supplierId)
                    ->exists();

                if (! $otherRfqsForSupplier) {
                    \App\Models\SupplierBidSelection::where('quotation_request_id', $quotationId)
                        ->where('selected_supplier_id', $supplierId)
                        ->delete();
                }
            }

            $document->delete();

            if ($quote && $quote->supplierRfqDocuments()->doesntExist() && in_array($quote->status, [QuotationRequest::STATUS_RFQ_GENERATED, QuotationRequest::STATUS_PENDING_BID, QuotationRequest::STATUS_SENT_TO_SUPPLIER, QuotationRequest::STATUS_SUPPLIER_RESPONSE_PENDING, QuotationRequest::STATUS_SUPPLIER_RESPONSE_RECEIVED], true)) {
                $quote->forceFill([
                    'status' => QuotationRequest::STATUS_PENDING_BID,
                    'commercial_phase' => SalesWorkflowService::PHASE_SUPPLIER_BID,
                    'rfq_pdf_path' => null,
                ])->save();
            }

            if ($quote) {
                $tracker = $this->trackerService->trackerForQuotation($quote);
                $this->trackerService->recordActivity(
                    $tracker,
                    'supplier_rfq.deleted',
                    "RFQ {$rfqNumber} eliminado para {$supplierLabel}.",
                    SalesWorkflowService::PHASE_SUPPLIER_BID,
                    'supplier_rfq_documents',
                    $documentId,
                    $oldStatus,
                    null,
                    $user
                );
            }

            return [
                'rfq_number' => $rfqNumber,
                'supplier_id' => $supplierId,
                'pdf_path' => $pdfPath,
            ];
        });

        if (filled($result['pdf_path'] ?? null)) {
            Storage::disk('private')->delete($result['pdf_path']);
        }

        return [
            'rfq_number' => $result['rfq_number'],
            'supplier_id' => $result['supplier_id'],
        ];
    }
}
