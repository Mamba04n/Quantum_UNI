<?php

namespace App\Livewire\Ventas\Concerns\QuotationProcess\SupplierRfq;

use App\Domain\Ventas\PeticionesOfertaProveedor\Services\SupplierRfqReviewService;
use App\Models\SupplierRfqDocument;
use Illuminate\Validation\ValidationException;

trait ManagesSupplierRfqForm
{
    public function saveSupplierRfqDraft(SupplierRfqReviewService $reviewService): void
    {
        $quote = $this->selectedQuote();
        if (! $quote) {
            return;
        }

        try {
            $supplier = $this->selectedSupplierForRfq();
            $supplierLabel = $supplier?->name ?? 'Sin proveedor especifico';
            $saved = $reviewService->saveDraft($quote, $supplier, $this->supplierRfqFormData(), auth()->user());
            $this->editingRfqDocumentId = $saved->id;
            $this->loadSupplierRfqDocumentDefaults();
            $this->dispatch('notify', ['type' => 'success', 'message' => "Datos RFQ {$saved->effective_rfq_number} guardados para {$supplierLabel}."]);
        } catch (ValidationException $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => collect($e->errors())->flatten()->first()]);
        }
    }

    public function editSupplierRfqDocument(int $documentId): void
    {
        $document = SupplierRfqDocument::where('quotation_request_id', $this->selectedQuoteId)
            ->whereKey($documentId)
            ->first();

        if (! $document) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'No se encontro el RFQ seleccionado.']);
            return;
        }

        $this->editingRfqDocumentId = $document->id;
        $this->selectedSupplierId = (string) ($document->supplier_id ?? '');
        $this->bidStep = 1;
        $this->loadSupplierRfqDocumentDefaults();
        $this->dispatch('notify', ['type' => 'success', 'message' => "Datos de {$document->effective_rfq_number} cargados para editar."]);
    }

    public function startNewRfqDocument(): void
    {
        $this->editingRfqDocumentId = null;
        $this->loadSupplierRfqDocumentDefaults();
        $this->dispatch('notify', ['type' => 'info', 'message' => 'Formulario listo para crear una nueva peticion RFQ.']);
    }
}
