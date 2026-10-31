<?php

namespace App\Livewire\Ventas\Concerns\QuotationProcess\ClientOffers;

use App\Services\ApprovalService;
use App\Services\ClientOfferReviewService;
use App\Services\InventoryReservationService;
use App\Services\OrderTrackerService;
use App\Services\SalesWorkflowService;
use App\Models\QuotationRequest;
use Illuminate\Validation\ValidationException;

trait ManagesClientOfferStatus
{
    public function registerClientApproval(string $decision): void
    {
        $quote = $this->selectedQuote();
        if (! $quote) {
            return;
        }

        $quote->loadMissing('offerVersions');
        if (! $quote->client_offer_pdf_path && $quote->offerVersions->isEmpty()) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'No se puede registrar aprobación sin oferta generada. Genera primero el PDF de la oferta.',
            ]);

            return;
        }

        try {
            app(ApprovalService::class)->registerClientApproval($quote, auth()->user(), $decision, $this->approvalReason ?: null);
            $this->activeTab = 'approval';
            session(['sales_quotation_active_tab' => 'approval']);
            $this->approvalReason = '';
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Decision del cliente registrada.']);
        } catch (ValidationException $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => collect($e->errors())->flatten()->first()]);
        }
    }

    public function approveClientOfferAdmin(ClientOfferReviewService $reviewService): void
    {
        $quote = $this->selectedQuote();
        if (! $quote) {
            return;
        }

        try {
            $review = $quote->offerReviews()->where('status', \App\Models\ClientOfferReview::STATUS_PENDING)->first();
            if (!$review) {
                $this->dispatch('notify', ['type' => 'error', 'message' => 'No hay una revisión pendiente para aprobar.']);
                return;
            }

            $reviewService->approve($review, auth()->user(), 'Aprobado desde kanban comercial');
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Oferta aprobada. Ya puedes generar el PDF final.']);
        } catch (ValidationException $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => collect($e->errors())->flatten()->first()]);
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('notify', ['type' => 'error', 'message' => \App\Support\UserMessage::error($e, 'No se pudo completar la acción. Revisa los datos e intenta de nuevo.')]);
        }
    }

    public function markClientOfferSent(ClientOfferReviewService $reviewService): void
    {
        $quote = $this->selectedQuote();
        if (! $quote) {
            return;
        }

        try {
            $reviewService->markSentToCustomer($quote, auth()->user());
            $this->activeTab = 'approval';
            session(['sales_quotation_active_tab' => 'approval']);
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Oferta marcada como enviada al cliente. Queda pendiente de decision.']);
        } catch (ValidationException $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => collect($e->errors())->flatten()->first()]);
        }
    }

    public function registerCustomerChangeRequest(ClientOfferReviewService $reviewService)
    {
        $quote = $this->selectedQuote();
        if (! $quote) {
            return;
        }

        try {
            if (blank($this->customerChangeReason) && filled($this->approvalReason)) {
                $this->customerChangeReason = $this->approvalReason;
            }

            $this->validate([
                'customerChangeType' => ['required', 'string', 'max:80'],
                'customerChangeReason' => ['required', 'string', 'max:2000'],
                'customerChangeComment' => ['nullable', 'string', 'max:2000'],
                'customerChangeAttachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx', 'max:10240'],
            ]);

            $reviewService->registerCustomerChangeRequest(
                $quote,
                auth()->user(),
                $this->customerChangeType,
                $this->customerChangeReason,
                $this->customerChangeComment ?: null,
                $this->customerChangeAffectedItems,
                $this->customerChangeAttachment
            );

            $this->customerChangeReason = '';
            $this->customerChangeComment = '';
            $this->approvalReason = '';
            $this->customerChangeAffectedItems = [];
            $this->customerChangeAttachment = null;
            session()->flash('notify', ['type' => 'success', 'message' => 'Cambios solicitados por cliente registrados.']);
            return redirect()->route('sales.quotations.corrections');
        } catch (ValidationException $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => collect($e->errors())->flatten()->first()]);
        }
    }

    public function reserveInventory(InventoryReservationService $reservationService): void
    {
        $quote = $this->selectedQuote();
        if (!$quote) {
            return;
        }

        try {
            $reservationService->reserveForQuotation($quote, auth()->user());
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Inventario reservado para esta solicitud.']);
        } catch (ValidationException $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => collect($e->errors())->flatten()->first()]);
        }
    }

    public function reopenClientOfferForRevision(): void
    {
        $quote = $this->selectedQuote();
        if (! $quote) {
            return;
        }

        if (! $this->canManageClientOfferEdits()) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'No tienes permiso para editar la oferta en su estado actual.']);
            return;
        }

        if (! $this->canReopenClientOffer($quote)) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Solo se puede rehacer la oferta despues de una respuesta de cliente que requiera correccion.']);
            return;
        }

        $oldStatus = $quote->status;
        $quote->forceFill([
            'status' => QuotationRequest::STATUS_REVISED_AFTER_CUSTOMER_CHANGE,
            'commercial_phase' => SalesWorkflowService::PHASE_CLIENT_OFFER,
            'client_offer_pdf_path' => null,
            'client_offer_generated_at' => null,
        ])->save();

        $tracker = app(OrderTrackerService::class)->trackerForQuotation($quote);
        app(OrderTrackerService::class)->recordActivity(
            $tracker,
            'client_offer.reopened_for_revision',
            'Oferta reabierta para correccion despues de respuesta del cliente.',
            SalesWorkflowService::PHASE_CLIENT_OFFER,
            'quotation_requests',
            $quote->id,
            $oldStatus,
            QuotationRequest::STATUS_REVISED_AFTER_CUSTOMER_CHANGE,
            auth()->user()
        );

        $this->activeTab = 'offer';
        session(['sales_quotation_active_tab' => 'offer']);
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Oferta reabierta. Ajusta datos y genera nuevamente el PDF final.']);
    }

    public function sendToClientApproval(SalesWorkflowService $workflow, OrderTrackerService $trackerService): void
    {
        $this->moveToApproval($workflow, $trackerService);
    }
}
