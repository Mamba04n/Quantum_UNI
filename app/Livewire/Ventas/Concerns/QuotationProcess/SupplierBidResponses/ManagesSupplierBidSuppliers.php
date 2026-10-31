<?php

namespace App\Livewire\Ventas\Concerns\QuotationProcess\SupplierBidResponses;

use App\Models\Entity;
use App\Models\QuotationRequest;
use App\Models\SupplierRfqDocument;
use Illuminate\Validation\ValidationException;

trait ManagesSupplierBidSuppliers
{
    public function updatedSelectedSupplierId(string $supplierId): void
    {
        $this->supplierSearch = '';

        if (blank($supplierId)) {
            $this->supplierProformas = [];
            $this->supplierContactName = '';
            $this->activeSupplierBidResponseId = null;
            $this->loadSupplierRfqDocumentDefaults();
            return;
        }

        $supplier = Entity::where('type', 'proveedor')
            ->where('status', '!=', 'inactivo')
            ->find($supplierId);

        $this->supplierContactName = $supplier?->contact_name ?: '';
        $this->responseSupplierName = '';
        $this->supplierProformas = [];
        $this->activeSupplierBidResponseId = null;
        $this->loadSupplierRfqDocumentDefaults();

        $quote = $this->selectedQuote();
        if ($quote && filled($supplierId)) {
            $savedResponse = $quote->supplierBidResponses
                ->where('supplier_id', (int) $supplierId)
                ->sortByDesc('id')->first();
            if ($savedResponse) {
                $this->loadSavedSupplierResponse($savedResponse);
            } else {
                $this->loadSupplierBidDefaults();
            }
        }
    }

    public function resetSelectedSupplierFromSearch(?string $search = null): void
    {
        if (blank($this->selectedSupplierId)) {
            return;
        }

        $supplier = Entity::where('type', 'proveedor')->find((int) $this->selectedSupplierId);
        if ($supplier && trim((string) ($search ?? $this->supplierSearch)) === (string) $supplier->name) {
            return;
        }

        $this->selectedSupplierId = '';
        $this->supplierContactName = '';
        $this->supplierProformas = [];
        $this->activeSupplierBidResponseId = null;
        $this->loadSupplierRfqDocumentDefaults();
    }

    public function selectSupplierOption(?int $supplierId = null): void
    {
        if (! $supplierId) {
            $this->selectedSupplierId = '';
            $this->supplierSearch = '';
            $this->supplierContactName = '';
            $this->supplierProformas = [];
            $this->activeSupplierBidResponseId = null;
            $this->loadSupplierRfqDocumentDefaults();
            return;
        }

        $supplier = Entity::where('type', 'proveedor')
            ->where('status', '!=', 'inactivo')
            ->find($supplierId);

        if (! $supplier) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'El proveedor seleccionado no esta disponible.']);
            return;
        }

        $this->selectedSupplierId = (string) $supplier->id;
        $this->supplierSearch = (string) $supplier->name;
        $this->supplierContactName = $supplier->contact_name ?: '';
        $this->responseSupplierName = '';
        $this->supplierProformas = [];
        $this->activeSupplierBidResponseId = null;
        $this->loadSupplierRfqDocumentDefaults();
    }

    public function clearSupplierSearch(): void
    {
        $this->supplierSearch = '';
    }

    public function prepareAnotherSupplierResponse(): void
    {
        $quote = $this->selectedQuote();
        if (! $quote) {
            return;
        }
        if (SupplierRfqDocument::where('quotation_request_id', $quote->id)->whereNull('supplier_id')->exists()) {
            $this->resetSupplierBidForm();
            $this->supplierBidItems = [];
            $this->bidStep = 2;
            return;
        }

        $source = SupplierRfqDocument::query()
            ->where('quotation_request_id', $quote->id)
            ->whereIn('status', [SupplierRfqDocument::STATUS_GENERATED, SupplierRfqDocument::STATUS_SENT_TO_SUPPLIER, SupplierRfqDocument::STATUS_SUPPLIER_RESPONSE_RECEIVED, SupplierRfqDocument::STATUS_NO_RESPONSE])
            ->when(filled($this->selectedSupplierId), fn ($query) => $query->where('supplier_id', (int) $this->selectedSupplierId))
            ->latest('id')
            ->first();

        if (! $source) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Primero marca como enviado al menos un RFQ para poder registrar otra respuesta.']);
            return;
        }

        SupplierRfqDocument::create([
            'quotation_request_id' => $quote->id,
            'supplier_bid_id' => null,
            'supplier_id' => null,
            'status' => $source->status === SupplierRfqDocument::STATUS_GENERATED ? SupplierRfqDocument::STATUS_GENERATED : SupplierRfqDocument::STATUS_SENT_TO_SUPPLIER,
            'quote_to_name' => $source->quote_to_name,
            'consignee_name' => $source->consignee_name,
            'consignee_comment' => $source->consignee_comment,
            'consignee_urgent_comment' => $source->consignee_urgent_comment,
            'consignee_address_1' => $source->consignee_address_1,
            'consignee_address_2' => $source->consignee_address_2,
            'consignee_country' => $source->consignee_country,
            'consignee_city' => $source->consignee_city,
            'consignee_phone' => $source->consignee_phone,
            'consignee_emails' => $source->consignee_emails,
            'rfq_response_requirements' => $source->rfq_response_requirements,
            'selected_item_ids' => $source->selected_item_ids,
            'item_specifications' => $source->item_specifications,
            'sent_at' => $source->status === SupplierRfqDocument::STATUS_GENERATED ? null : now(),
            'generated_by' => auth()->id(),
        ]);

        $this->resetSupplierBidForm();
        $this->supplierBidItems = [];
        $this->selectedSupplierId = '';
        $this->bidStep = 2;
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Nuevo espacio listo. Indica el siguiente proveedor y registra su respuesta.']);
    }

    public function discardOpenRfqDocument(): void
    {
        $quote = $this->selectedQuote();
        if (! $quote) {
            return;
        }

        SupplierRfqDocument::where('quotation_request_id', $quote->id)
            ->whereNull('supplier_id')
            ->delete();

        if (blank($this->selectedSupplierId)) {
            $firstDoc = $quote->supplierRfqDocuments()->whereNotNull('supplier_id')->first();
            if ($firstDoc) {
                $this->selectedSupplierId = (string) $firstDoc->supplier_id;
                $this->updatedSelectedSupplierId($this->selectedSupplierId);
            }
        }

        $this->dispatch('notify', ['type' => 'info', 'message' => 'Espacio de nuevo proveedor descartado.']);
    }

    public function markSelectedSupplierNoResponse(?int $documentId = null): void
    {
        $document = $documentId
            ? SupplierRfqDocument::with('supplier')
                ->where('quotation_request_id', $this->selectedQuoteId)
                ->whereKey($documentId)
                ->first()
            : $this->supplierRfqDocumentForSelectedSupplier();

        if (! $document) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'No se encontró el RFQ que deseas marcar sin respuesta.']);
            return;
        }

        if ($document->status !== SupplierRfqDocument::STATUS_SENT_TO_SUPPLIER) {
            $this->dispatch('notify', ['type' => 'warning', 'message' => 'Solo un RFQ enviado pendiente puede marcarse sin respuesta.']);
            return;
        }

        $document->forceFill(['status' => SupplierRfqDocument::STATUS_NO_RESPONSE])->save();
        $label = $document->supplier?->name ?: 'El RFQ abierto';
        $this->dispatch('notify', ['type' => 'success', 'message' => "{$label} quedó marcado como sin respuesta."]);
    }

    public function itemsForSupplierResponse(QuotationRequest $quote, ?SupplierRfqDocument $rfqDocument)
    {
        return $quote->items->values();
    }

    private function selectedSupplierForRfq(): ?Entity
    {
        if (blank($this->selectedSupplierId)) {
            return null;
        }

        $supplier = Entity::where('type', 'proveedor')
            ->where('status', '!=', 'inactivo')
            ->find((int) $this->selectedSupplierId);

        if (! $supplier) {
            throw ValidationException::withMessages(['selectedSupplierId' => 'El proveedor seleccionado no esta disponible.']);
        }

        return $supplier;
    }

    private function supplierRfqDocumentForSelectedSupplier(): ?SupplierRfqDocument
    {
        if (blank($this->selectedQuoteId)) {
            return null;
        }

        $query = SupplierRfqDocument::with(['supplier', 'adminReviewer', 'generator'])
            ->where('quotation_request_id', $this->selectedQuoteId);

        if (filled($this->selectedSupplierId)) {
            $doc = (clone $query)->where('supplier_id', (int) $this->selectedSupplierId)->first();
            if ($doc) {
                return $doc;
            }
        }

        $openDoc = (clone $query)->whereNull('supplier_id')->first();
        if ($openDoc) {
            return $openDoc;
        }

        return (clone $query)->first();
    }

    private function supplierForResponse(SupplierRfqDocument $rfqDocument): Entity
    {
        if (filled($this->selectedSupplierId)) {
            $supplier = Entity::where('type', 'proveedor')
                ->where('status', '!=', 'inactivo')
                ->find((int) $this->selectedSupplierId);

            if (! $supplier) {
                throw ValidationException::withMessages(['selectedSupplierId' => 'El proveedor seleccionado no esta disponible.']);
            }

            return $supplier;
        }

        if (filled($rfqDocument->supplier_id)) {
            return $rfqDocument->supplier()->firstOrFail();
        }

        if (blank($this->responseSupplierName)) {
            throw ValidationException::withMessages(['responseSupplierName' => 'Indica el nombre del proveedor que respondio este RFQ abierto.']);
        }

        return Entity::firstOrCreate(
            [
                'type' => 'proveedor',
                'name' => trim($this->responseSupplierName),
            ],
            [
                'status' => 'activo',
            ]
        );
    }

    private function rfqItemIdsForResponse(QuotationRequest $quote, ?SupplierRfqDocument $rfqDocument): array
    {
        return $quote->items->pluck('id')->map(fn ($id) => (int) $id)->all();
    }
}
