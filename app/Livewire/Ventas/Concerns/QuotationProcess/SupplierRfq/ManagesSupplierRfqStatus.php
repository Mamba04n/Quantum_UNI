<?php

namespace App\Livewire\Ventas\Concerns\QuotationProcess\SupplierRfq;

use App\Domain\Ventas\PeticionesOfertaProveedor\Actions\DeleteSupplierRfqDocument;
use App\Domain\Ventas\PeticionesOfertaProveedor\Services\SupplierRfqReviewService;
use App\Models\QuotationRequest;
use App\Models\SupplierRfqDocument;
use App\Services\OrderTrackerService;
use App\Services\PdfService;
use App\Services\SalesWorkflowService;
use Illuminate\Validation\ValidationException;
use Throwable;

trait ManagesSupplierRfqStatus
{
    public function generateSupplierRFQPdf(PdfService $pdfService, SalesWorkflowService $workflow): void
    {
        $quote = $this->selectedQuote();
        if (!$quote) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'No se encontro la solicitud para generar el PDF.']);
            return;
        }

        try {
            $workflow->assertRequestItemsAreNormalized($quote);
            $supplier = $this->selectedSupplierForRfq();
            $supplierLabel = $supplier?->name ?? 'Sin proveedor especifico';

            if ($quote->items->isEmpty()) {
                throw ValidationException::withMessages(['rfq' => 'No se puede generar RFQ sin partidas.']);
            }

            $document = app(SupplierRfqReviewService::class)
                ->saveDraft($quote, $supplier, $this->supplierRfqFormData(), auth()->user());

            $pdfService->generateSupplierRFQPdf($document, auth()->id(), $supplier);

            $document->update(['status' => SupplierRfqDocument::STATUS_SENT_TO_SUPPLIER]);

            if (in_array($quote->status, [QuotationRequest::STATUS_PENDING_BID, QuotationRequest::STATUS_READY_FOR_BID], true)) {
                $workflow->transition($quote->fresh('items'), QuotationRequest::STATUS_RFQ_GENERATED, auth()->user(), true);
            }

            $this->editingRfqDocumentId = null;
            $this->loadSupplierRfqDocumentDefaults();
            $this->dispatch('notify', ['type' => 'success', 'message' => "PDF RFQ {$document->effective_rfq_number} generado para {$supplierLabel}."]);
        } catch (ValidationException $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => collect($e->errors())->flatten()->first() ?: 'No se pudo generar el PDF']);
        } catch (Throwable $e) {
            report($e);
            $this->dispatch('notify', ['type' => 'error', 'message' => 'No se pudo generar el PDF']);
        }
    }

    public function regenerateSupplierRfqDocument(int $documentId): void
    {
        $document = SupplierRfqDocument::with('supplier')
            ->where('quotation_request_id', $this->selectedQuoteId)
            ->whereKey($documentId)
            ->first();

        if (! $document) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'No se encontro el RFQ seleccionado.']);
            return;
        }

        $this->editingRfqDocumentId = $document->id;
        $this->selectedSupplierId = (string) ($document->supplier_id ?? '');
        $this->loadSupplierRfqDocumentDefaults();
        $this->generateSupplierRFQPdf(app(PdfService::class), app(SalesWorkflowService::class));
    }

    public function markRfqDocumentSent(int $documentId): void
    {
        $document = SupplierRfqDocument::where('quotation_request_id', $this->selectedQuoteId)
            ->whereKey($documentId)
            ->first();

        if (! $document) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'No se encontro el RFQ seleccionado.']);
            return;
        }

        $this->selectedSupplierId = (string) ($document->supplier_id ?? '');
        $this->editingRfqDocumentId = $document->id;
        $this->markRfqSent(app(SalesWorkflowService::class), app(OrderTrackerService::class));
    }

    public function markRfqSent(SalesWorkflowService $workflow, OrderTrackerService $trackerService): void
    {
        $quote = $this->selectedQuote();
        if (!$quote) {
            return;
        }

        $document = $this->editingRfqDocumentId
            ? SupplierRfqDocument::find($this->editingRfqDocumentId)
            : $this->supplierRfqDocumentForSelectedSupplier();

        if (! $document) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'No hay RFQ para este proveedor.']);
            return;
        }

        try {
            $supplier = $this->selectedSupplierForRfq();
            $supplierLabel = $supplier?->name ?? 'Sin proveedor especifico';
            app(SupplierRfqReviewService::class)->markSent($document, auth()->user());

            $tracker = $trackerService->trackerForQuotation($quote);
            if ($tracker) {
                $trackerService->recordActivity($tracker, 'supplier_rfq.sent', "RFQ enviado a {$supplierLabel}", 'licitacion');
            }
            $this->editingRfqDocumentId = null;
            $this->dispatch('notify', ['type' => 'success', 'message' => "RFQ marcado como enviado a {$supplierLabel}."]);
        } catch (ValidationException $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => collect($e->errors())->flatten()->first()]);
        }
    }

    public function deleteSupplierRfqDocument(int $documentId, DeleteSupplierRfqDocument $deleteAction): void
    {
        if (! $this->selectedQuoteId) {
            return;
        }

        try {
            $deleted = $deleteAction->execute($this->selectedQuoteId, $documentId, auth()->user());

            if ($this->editingRfqDocumentId === $documentId) {
                $this->editingRfqDocumentId = null;
                $this->loadSupplierRfqDocumentDefaults();
            } elseif ((string) $this->selectedSupplierId === (string) ($deleted['supplier_id'] ?? '')) {
                $this->loadSupplierRfqDocumentDefaults();
            }

            $this->dispatch('notify', ['type' => 'success', 'message' => "RFQ {$deleted['rfq_number']} eliminado."]);
        } catch (ValidationException $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => collect($e->errors())->flatten()->first()]);
        }
    }
}
