<?php

namespace App\Livewire\Ventas\Concerns\QuotationProcess\SupplierRfq;

use App\Models\SupplierRfqDocument;

trait SupplierRfqHelpers
{
    public function loadSupplierRfqDocumentDefaults(): void
    {
        $quote = $this->selectedQuote();
        $document = null;

        if ($this->editingRfqDocumentId) {
            $document = SupplierRfqDocument::where('quotation_request_id', $this->selectedQuoteId)
                ->whereKey($this->editingRfqDocumentId)
                ->first();
        }

        if (! $document) {
            $document = $this->supplierRfqDocumentForSelectedSupplier();
            if ($document && ($document->pdf_path || $document->status !== SupplierRfqDocument::STATUS_DRAFT)) {
                $document = null;
            }
        }

        $user = auth()->user();

        $this->rfqQuoteToName = (string) ($document?->quote_to_name ?: (config('company.quote_to_name') ?: 'Quantum Soluciones S.A. - Compras'));
        $this->rfqConsigneeName = (string) ($document?->consignee_name ?: (config('company.legal_name') ?: 'Quantum Soluciones S.A.'));
        $this->rfqConsigneeContactName = (string) ($document?->consignee_contact_name ?: ($user?->name ?: (config('company.contact_name') ?: 'Departamento de Compras')));
        $this->rfqConsigneeComment = (string) ($document?->consignee_comment ?: '');
        $this->rfqConsigneeUrgentComment = (string) ($document?->consignee_urgent_comment ?: ($quote?->request_urgency === 'urgente' ? 'URGENTE: Requerido a la brevedad posible para equipo detenido.' : ''));
        $this->rfqConsigneeAddress1 = (string) ($document?->consignee_address_1 ?: (config('company.address_1') ?: 'Rotonda Jean Paul Genie 100m al Este, 50m al Sur'));
        $this->rfqConsigneeAddress2 = (string) ($document?->consignee_address_2 ?: (config('company.address_2') ?: 'Edificio Quantum, Managua'));
        $this->rfqConsigneeCountry = (string) ($document?->consignee_country ?: (config('company.country') ?: 'Nicaragua'));
        $this->rfqConsigneeCity = (string) ($document?->consignee_city ?: (config('company.city') ?: 'Managua'));
        $this->rfqConsigneePhone = (string) ($document?->consignee_phone ?: (config('company.phone') ?: '+505 7669 8164'));
        $this->rfqConsigneeEmails = (string) ($document?->consignee_emails ?: (config('company.purchasing_emails') ?: 'compras@quantumsolucionesnic.com, operaciones@quantumsolucionesnic.com'));
        $this->rfqResponseRequirements = (string) ($document?->effective_response_requirements ?: SupplierRfqDocument::DEFAULT_RESPONSE_REQUIREMENTS);
        $this->rfqSpecifications = (string) ($document?->rfq_specifications ?: '');
        $this->selectedRfqItemIds = collect($document?->selected_item_ids ?: $quote?->items?->pluck('id')->all() ?: [])
            ->map(fn ($id) => (string) $id)
            ->all();
        $savedQuantities = $document?->rfq_item_quantities ?: [];
        $this->rfqItemQuantities = collect($quote?->items ?: [])
            ->mapWithKeys(fn ($item) => [
                $item->id => max(1, (int) data_get($savedQuantities, (string) $item->id, $item->quantity)),
            ])
            ->all();
        $this->editingRfqItemQuantities = [];
        $this->rfqItemSpecifications = collect($quote?->items ?: [])
            ->mapWithKeys(fn ($item) => [
                $item->id => (string) data_get($document?->item_specifications ?: [], (string) $item->id, $item->technical_specs ?: ''),
            ])
            ->all();
    }

    public function toggleAllRfqItems(): void
    {
        $quote = $this->selectedQuote();
        if (! $quote) {
            return;
        }

        $allItemIds = $quote->items->pluck('id')->map(fn ($id) => (string) $id)->all();

        if (count($this->selectedRfqItemIds) === count($allItemIds)) {
            $this->selectedRfqItemIds = [];
        } else {
            $this->selectedRfqItemIds = $allItemIds;
        }
    }

    public function supplierRfqFormData(): array
    {
        return [
            'rfq_document_id' => $this->editingRfqDocumentId,
            'quote_to_name' => $this->rfqQuoteToName,
            'consignee_name' => $this->rfqConsigneeName,
            'consignee_contact_name' => $this->rfqConsigneeContactName,
            'consignee_comment' => $this->rfqConsigneeComment,
            'consignee_urgent_comment' => $this->rfqConsigneeUrgentComment,
            'consignee_address_1' => $this->rfqConsigneeAddress1,
            'consignee_address_2' => $this->rfqConsigneeAddress2,
            'consignee_country' => $this->rfqConsigneeCountry,
            'consignee_city' => $this->rfqConsigneeCity,
            'consignee_phone' => $this->rfqConsigneePhone,
            'consignee_emails' => $this->rfqConsigneeEmails,
            'rfq_response_requirements' => $this->rfqResponseRequirements ?: SupplierRfqDocument::DEFAULT_RESPONSE_REQUIREMENTS,
            'rfq_specifications' => $this->rfqSpecifications,
            'selected_item_ids' => $this->selectedRfqItemIds,
            'item_specifications' => $this->rfqItemSpecifications,
            'rfq_item_quantities' => $this->rfqItemQuantities,
        ];
    }

    public function editRfqItemQuantity(int $itemId): void
    {
        $this->editingRfqItemQuantities[$itemId] = true;
    }

    public function saveRfqItemQuantity(int $itemId): void
    {
        $this->rfqItemQuantities[$itemId] = max(1, (int) ($this->rfqItemQuantities[$itemId] ?? 1));
        unset($this->editingRfqItemQuantities[$itemId]);
    }
}
