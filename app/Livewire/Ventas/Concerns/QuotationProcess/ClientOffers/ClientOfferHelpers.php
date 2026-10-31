<?php

namespace App\Livewire\Ventas\Concerns\QuotationProcess\ClientOffers;

use App\Models\QuotationRequest;
use App\Services\OrderTrackerService;
use App\Services\SalesWorkflowService;

trait ClientOfferHelpers
{
    private function canEditClientOfferData(?QuotationRequest $quote = null): bool
    {
        if (! $quote) {
            return false;
        }

        return in_array($quote->status, [
            QuotationRequest::STATUS_CLIENT_OFFER_GENERATED,
            QuotationRequest::STATUS_OFFER_DRAFT,
            QuotationRequest::STATUS_OFFER_PREVIEW_READY,
            QuotationRequest::STATUS_FINAL_PDF_GENERATED,
            QuotationRequest::STATUS_ADMIN_CHANGES_REQUESTED,
            QuotationRequest::STATUS_CUSTOMER_CHANGES_REQUESTED,
            QuotationRequest::STATUS_REVISED_AFTER_CUSTOMER_CHANGE,
        ], true);
    }

    public function canManageClientOfferEdits(): bool
    {
        return true;
    }

    private function prepareClientOfferForEditing(QuotationRequest $quote): QuotationRequest
    {
        if ($quote->status !== QuotationRequest::STATUS_CUSTOMER_CHANGES_REQUESTED) {
            return $quote;
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
            'Oferta reabierta automaticamente para editar partidas despues de cambios del cliente.',
            SalesWorkflowService::PHASE_CLIENT_OFFER,
            'quotation_requests',
            $quote->id,
            $oldStatus,
            QuotationRequest::STATUS_REVISED_AFTER_CUSTOMER_CHANGE,
            auth()->user()
        );

        return $quote->fresh(['items']);
    }

    private function recalculateClientOfferTotals(QuotationRequest $quote): void
    {
        $hasApprovedItems = $quote->items()->where('is_approved', true)->exists();
        $subtotalQuery = $hasApprovedItems ? $quote->items()->where('is_approved', true) : $quote->items()->where('line_total', '>', 0);
        $subtotal = round((float) $subtotalQuery->sum('line_total'), 2);
        $taxPercent = $this->defaultTaxPercent($quote->tax_percent ?? null);

        $quote->forceFill([
            'tax_percent' => $taxPercent,
            'subtotal' => $subtotal,
            'total_amount' => round($subtotal * (1 + ($taxPercent / 100)), 2),
        ])->save();
    }

    private function defaultTaxPercent(mixed $value = null): int
    {
        $tax = (int) round((float) ($value ?? 15));

        return max(0, $tax);
    }

    private function canReopenClientOffer(?QuotationRequest $quote = null): bool
    {
        if (! $quote) {
            return false;
        }

        return in_array($quote->status, [
            QuotationRequest::STATUS_CLIENT_REJECTED,
            QuotationRequest::STATUS_CLIENT_REQUIRES_ADJUSTMENT,
            QuotationRequest::STATUS_CUSTOMER_CHANGES_REQUESTED,
        ], true);
    }
}
