<?php

namespace App\Livewire\Ventas\Concerns\QuotationProcess\SupplierBidResponses;

use App\Models\QuotationRequestItem;
use App\Models\SupplierBidResponse;
use App\Models\SupplierBidResponseItem;
use App\Services\SupplierBidResponseService;
use Illuminate\Validation\ValidationException;

trait ManagesSupplierBidSelectionFlow
{
    public function deleteSupplierResponse(int $responseId, SupplierBidResponseService $bidService): void
    {
        try {
            $bidService->deleteResponse(SupplierBidResponse::findOrFail($responseId), auth()->user());
            if ($this->editingSupplierResponseId === $responseId || $this->activeSupplierBidResponseId === $responseId) {
                $this->clearSupplierResponseForm();
            }
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Cotizacion de proveedor eliminada.']);
        } catch (ValidationException $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => collect($e->errors())->flatten()->first()]);
        }
    }

    public function duplicateSupplierResponse(int $responseId, SupplierBidResponseService $bidService): void
    {
        try {
            $copy = $bidService->duplicateResponse(SupplierBidResponse::findOrFail($responseId), auth()->user());
            $this->editSupplierResponse($copy->id);
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Cotizacion duplicada como nueva.']);
        } catch (ValidationException $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => collect($e->errors())->flatten()->first()]);
        }
    }

    public function selectResponseItem(int $responseItemId, SupplierBidResponseService $bidService): void
    {
        try {
            $item = SupplierBidResponseItem::with('response.supplier', 'quotationItem.quotationRequest')->findOrFail($responseItemId);
            $bidService->selectItem($item, auth()->user());
        } catch (ValidationException $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => collect($e->errors())->flatten()->first()]);
        }
    }

    public function removeSelectedItem(int $quoteItemId, SupplierBidResponseService $bidService): void
    {
        try {
            $item = QuotationRequestItem::findOrFail($quoteItemId);
            $bidService->removeSelection($item, auth()->user());
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Seleccion removida.']);
        } catch (ValidationException $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => collect($e->errors())->flatten()->first()]);
        }
    }

    public function excludeSupplierSelectionItem(int $quoteItemId, SupplierBidResponseService $bidService): void
    {
        try {
            $this->validate([
                "supplierSelectionExclusionReasons.{$quoteItemId}" => ['required', 'string', 'min:3', 'max:500'],
            ]);
            $item = QuotationRequestItem::findOrFail($quoteItemId);
            $bidService->excludeItem($item, $this->supplierSelectionExclusionReasons[$quoteItemId], auth()->user());
            unset($this->supplierSelectionExclusionReasons[$quoteItemId]);
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Partida excluida con su motivo registrado.']);
        } catch (ValidationException $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => collect($e->errors())->flatten()->first()]);
        }
    }

    public function useAllItemsFromSupplier(int $responseId, SupplierBidResponseService $bidService): void
    {
        try {
            $response = SupplierBidResponse::with('items')->findOrFail($responseId);
            $count = $bidService->useAllItemsFromSupplier($response, auth()->user());
            $this->dispatch('notify', ['type' => 'success', 'message' => "{$count} partidas cotizadas seleccionadas para este proveedor."]);
        } catch (ValidationException $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => collect($e->errors())->flatten()->first()]);
        }
    }

    public function saveSelections(SupplierBidResponseService $bidService): void
    {
        $this->validateSelectionsBeforeOffer($bidService);
    }

    public function validateSelectionsBeforeOffer(SupplierBidResponseService $bidService): bool
    {
        $quote = $this->selectedQuote();
        if (!$quote) {
            return false;
        }

        try {
            $bidService->validateSelectionsBeforeOffer($quote);
            return true;
        } catch (ValidationException $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => collect($e->errors())->flatten()->first()]);
            return false;
        }
    }

    public function sendSelectionsToClientOffer(SupplierBidResponseService $bidService): mixed
    {
        $quote = $this->selectedQuote();
        if (!$quote) {
            return null;
        }

        try {
            $bidService->sendSelectionsToClientOffer($quote, auth()->user());
            $this->activeTab = 'offer';
            session(['sales_quotation_active_tab' => 'offer']);
            $this->bidStep = 3;
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Licitacion enviada a Oferta Cliente con las partidas seleccionadas.']);
        } catch (ValidationException $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => collect($e->errors())->flatten()->first()]);
        }

        return null;
    }

    public function continueToSelection(): void
    {
        $quote = $this->selectedQuote();
        if (! $quote) {
            return;
        }

        try {
            app(SupplierBidResponseService::class)->assertHasValidResponseForSelection($quote);
            $this->bidStep = 3;
        } catch (ValidationException $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => collect($e->errors())->flatten()->first()]);
        }
    }

    public function markBidReadyForClientOffer(SupplierBidResponseService $bidService): void
    {
        $quote = $this->selectedQuote();
        if (!$quote) {
            return;
        }

        try {
            $bidService->validateSelectionsBeforeOffer($quote);
            $this->bidStep = 3;
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Licitacion lista para Oferta Cliente.']);
        } catch (ValidationException $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => collect($e->errors())->flatten()->first()]);
        }
    }

    public function sendSelectedItemsToOffer(SupplierBidResponseService $bidService): mixed
    {
        return $this->sendSelectionsToClientOffer($bidService);
    }
}
