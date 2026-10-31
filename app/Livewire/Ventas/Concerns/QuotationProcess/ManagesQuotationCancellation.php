<?php

namespace App\Livewire\Ventas\Concerns\QuotationProcess;

use App\Models\ActivityLog;
use App\Models\ApprovalRequest;
use App\Models\Entity;
use App\Models\PurchasePreparationItem;
use App\Models\QuotationRequest;
use App\Models\QuotationRequestItem;
use App\Models\SupplierBidResponse;
use App\Models\SupplierBidResponseItem;
use App\Models\SupplierRfqDocument;
use App\Services\ApprovalService;
use App\Services\ClientOfferReviewService;
use App\Services\ExchangeRateService;
use App\Services\InventoryReservationService;
use App\Services\OrderTrackerService;
use App\Services\PdfService;
use App\Services\PurchasePreparationService;
use App\Services\SalesWorkflowService;
use App\Services\SupplierBidResponseService;
use App\Services\SupplierRfqReviewService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

trait ManagesQuotationCancellation
{
    public function deleteQuote(int $id): void
    {
        $this->selectedQuoteId = $id;
        $this->openCancelQuoteModal();
    }

    public function openCancelQuoteModal(): void
    {
        $quote = $this->selectedQuote();
        if (! $quote) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Seleccione una solicitud antes de cancelar.']);
            return;
        }

        if (! $this->canCancelQuote($quote)) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Esta cotizacion ya no puede cancelarse desde este modulo porque tiene orden, compra o cierre posterior.']);
            return;
        }

        $this->cancelQuoteCategory = 'proveedor_sin_stock';
        $this->cancelQuoteReason = '';
        $this->showCancelQuoteModal = true;
    }

    public function closeCancelQuoteModal(): void
    {
        $this->showCancelQuoteModal = false;
        $this->cancelQuoteReason = '';
        $this->resetValidation(['cancelQuoteCategory', 'cancelQuoteReason']);
    }

    public function cancelQuote(OrderTrackerService $trackerService): void
    {
        $quote = $this->selectedQuote();
        if (! $quote) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Seleccione una solicitud antes de cancelar.']);
            return;
        }

        if (! $this->canCancelQuote($quote)) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'No se puede cancelar porque esta cotizacion ya tiene orden, compra o proceso posterior.']);
            return;
        }

        $this->validate([
            'cancelQuoteCategory' => ['required', 'string', 'in:' . implode(',', array_keys($this->cancelQuoteCategories))],
            'cancelQuoteReason' => ['required', 'string', 'min:10', 'max:2000'],
        ], [
            'cancelQuoteCategory.required' => 'Seleccione el motivo general de cancelacion.',
            'cancelQuoteReason.required' => 'Debe justificar por que se cancela la cotizacion.',
            'cancelQuoteReason.min' => 'La justificacion debe explicar el motivo con mas detalle.',
        ]);

        $oldStatus = $quote->status;
        $categoryLabel = $this->cancelQuoteCategories[$this->cancelQuoteCategory] ?? 'Otro motivo';

        try {
            $quote->forceFill([
                'status' => QuotationRequest::STATUS_CANCELLED,
                'commercial_phase' => SalesWorkflowService::PHASE_REQUEST,
                'cancellation_category' => $this->cancelQuoteCategory,
                'cancellation_reason' => trim($this->cancelQuoteReason),
                'cancelled_by' => auth()->id(),
                'cancelled_at' => now(),
            ])->save();

            ActivityLog::log('ventas', 'quotation.cancelled', "Cotizacion {$quote->reference} cancelada: {$categoryLabel}", [
                'quotation_request_id' => $quote->id,
                'old_status' => $oldStatus,
                'new_status' => QuotationRequest::STATUS_CANCELLED,
                'cancellation_category' => $this->cancelQuoteCategory,
                'cancellation_reason' => $this->cancelQuoteReason,
            ]);

            $tracker = $trackerService->trackerForQuotation($quote);
            if ($tracker) {
                $trackerService->recordActivity(
                    $tracker,
                    'quotation.cancelled',
                    "Cotizacion cancelada: {$categoryLabel}. {$this->cancelQuoteReason}",
                    SalesWorkflowService::PHASE_REQUEST,
                    'quotation_requests',
                    $quote->id,
                    $oldStatus,
                    QuotationRequest::STATUS_CANCELLED,
                    auth()->user()
                );
            }

            $this->showCancelQuoteModal = false;
            $this->selectedQuoteId = null;
            $this->showDetail = false;
            $this->cancelQuoteReason = '';
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Cotizacion cancelada con justificacion. Se conserva el historial del proceso.']);
        } catch (Throwable $e) {
            report($e);
            $this->dispatch('notify', ['type' => 'error', 'message' => 'No se pudo cancelar la cotización. Revisa los datos e intenta de nuevo.']);
        }
    }

    private function canCancelQuote(QuotationRequest $quote): bool
    {
        if ($quote->status === QuotationRequest::STATUS_CANCELLED) {
            return false;
        }

        if ($quote->sale_order_id || $quote->saleOrder()->exists() || $quote->purchaseOrders()->exists()) {
            return false;
        }

        return ! in_array($quote->status, [
            QuotationRequest::STATUS_CLOSED,
        ], true);
    }
}
