<?php

namespace App\Livewire\Ventas\Concerns\QuotationProcess;

use App\Models\ActivityLog;
use App\Models\QuotationRequest;
use App\Models\QuotationRequestItem;
use App\Services\ExchangeRateService;
use App\Support\Concurrency\OptimisticLock;
use App\Support\Concurrency\StaleModelException;

trait ManagesQuotationNavigation
{
    public function setTab(string $tab): void
    {
        if (! isset($this->tabs[$tab])) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'La seccion solicitada no existe.']);

            return;
        }

        $this->activeTab = $tab;
        session(['sales_quotation_active_tab' => $tab]);
        $this->selectedQuoteId = null;
        $this->showDetail = false;
        $this->showAllProductsInResponse = false;
    }

    public function selectQuote(int $id): void
    {
        $quote = QuotationRequest::find($id);
        if ($quote) {
            $stageKey = $this->stageKeyForQuote($quote);
            if (property_exists($this, 'flowStages') && isset($this->flowStages[$stageKey]['tab'])) {
                $this->activeTab = $this->flowStages[$stageKey]['tab'];
            }
            session(['sales_quotation_active_tab' => $this->activeTab]);
        }

        $this->selectedQuoteId = $id;
        $this->reset('commentFiles', 'newComment', 'commentVisibility');
        $this->showDetail = true;
        $this->showAllProductsInResponse = false;
        $this->activeSupplierBidResponseId = null;
        $this->editingSupplierResponseId = null;
        $this->selectedSupplierId = '';
        $this->bidStep = 1;
        $this->showCancelQuoteModal = false;
        $this->cancelQuoteReason = '';

        $quote = $this->selectedQuote();
        if ($quote && blank($this->selectedSupplierId)) {
            $firstDoc = $quote->supplierRfqDocuments->first(fn ($d) => filled($d->supplier_id));
            if ($firstDoc) {
                $this->selectedSupplierId = (string) $firstDoc->supplier_id;
                $this->supplierContactName = (string) ($firstDoc->supplier?->contact_name ?: '');
            }
        }

        $this->loadSupplierBidDefaults();
        $this->loadSupplierRfqDocumentDefaults();
        $quote = $this->selectedQuote();
        if ($quote && filled($this->selectedSupplierId)) {
            $savedResponse = $quote->supplierBidResponses
                ->where('supplier_id', (int) $this->selectedSupplierId)
                ->sortByDesc('id')->first();
            if ($savedResponse) {
                $this->loadSavedSupplierResponse($savedResponse);
            }
        }
        if ($quote && $this->activeTab === 'offer') {
            app(ExchangeRateService::class)->applyToClientOffer($quote);
            $quote = $this->selectedQuote();
        }
        $this->companyIssuer = (string) ($quote->company_issuer ?: 'quantum');
        $this->clientOfferTaxPercent = (string) $this->defaultTaxPercent($quote->tax_percent ?? null);
        $this->offerNotes = (string) ($quote->offer_notes ?? '');
        $this->offerValidity = $quote->validity_days ?? '15 dias';
        $this->offerPaymentTerms = $quote->payment_terms ?: 'Contado';
        $this->offerDeliveryTime = $quote->delivery_time ?: '';
        $this->offerCurrency = 'NIO';
    }

    public function backToList(): void
    {
        $this->selectedQuoteId = null;
        $this->showDetail = false;
        $this->showAllProductsInResponse = false;
        $this->resetValidation();
    }

    public function toggleAllProductsInResponse(): void
    {
        $this->showAllProductsInResponse = ! $this->showAllProductsInResponse;
    }

    public function updateItemField(int $itemId, string $field, mixed $value, ?int $expectedVersion = null): void
    {
        if ($field === 'is_approved' && ! auth()->user()?->can('order_approval.partial_approve') && ! auth()->user()?->can('order_approval.approve')) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'No tienes permiso para aprobar partidas.']);

            return;
        }

        $item = QuotationRequestItem::find($itemId);
        if (! $item) {
            return;
        }

        if ($expectedVersion === null || (int) $item->version !== $expectedVersion) {
            $this->dispatchConcurrentEditConflict();

            return;
        }

        $parent = $item->quotationRequest()->with('items')->first();
        if (in_array($field, ['unit_price', 'quantity'], true) && ! $this->canEditClientOfferData($parent)) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'La oferta ya esta aprobada o emitida. Registra una respuesta/cambio del cliente para rehacerla.']);

            return;
        }

        if (in_array($field, ['unit_price', 'quantity'], true)) {
            $parent = $this->prepareClientOfferForEditing($parent);
            $value = $field === 'quantity'
                ? max(1, (int) round((float) $value))
                : max(0, round((float) $value, 2));
        }

        $previousValue = $item->{$field};
        $changes = [$field => $value];
        if (in_array($field, ['unit_price', 'quantity', 'is_approved'], true)) {
            $quantity = $field === 'quantity' ? $value : $item->quantity;
            $unitPrice = $field === 'unit_price' ? $value : ($item->unit_price ?? 0);
            $changes['line_total'] = round((float) $quantity * (float) $unitPrice, 2);
        }

        try {
            $item = app(OptimisticLock::class)->update($item, $expectedVersion, $changes);
        } catch (StaleModelException) {
            $this->dispatchConcurrentEditConflict();

            return;
        }

        if ($field === 'quantity') {
            // The supplier's original quoted quantity remains historical evidence,
            // while the selected quantity is the operational amount that must flow
            // into purchase preparation after a customer correction.
            $item->supplierBidSelection()->update(['selected_quantity' => $value]);

            ActivityLog::log('ventas', 'client_offer.quantity_updated', 'Cantidad de partida actualizada durante corrección de oferta.', [
                'quote_id' => $parent->id,
                'quote_item_id' => $item->id,
                'previous_quantity' => (float) $previousValue,
                'new_quantity' => (float) $value,
            ]);
        }

        $this->recalculateClientOfferTotals($parent);

        if ($field === 'unit_cost' && (float) $value > 0 && $parent->status === QuotationRequest::STATUS_SENT_TO_SUPPLIER) {
            $parent->forceFill(['status' => QuotationRequest::STATUS_SUPPLIER_RESPONSE_PENDING])->save();
        }
    }

    private function dispatchConcurrentEditConflict(): void
    {
        $this->dispatch('notify', [
            'type' => 'error',
            'message' => 'Esta partida fue modificada por otro usuario. Se recargaron los datos actuales; revisalos antes de guardar nuevamente.',
        ]);
    }
}
