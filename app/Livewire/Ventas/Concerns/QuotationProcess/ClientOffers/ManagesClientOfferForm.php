<?php

namespace App\Livewire\Ventas\Concerns\QuotationProcess\ClientOffers;

use App\Services\ClientOfferReviewService;
use App\Services\ExchangeRateService;
use App\Services\PdfService;
use Illuminate\Validation\ValidationException;
use Throwable;

trait ManagesClientOfferForm
{
    public function saveClientOfferTaxPercent(): void
    {
        $quote = $this->selectedQuote();
        if (! $quote) {
            return;
        }

        if (! $this->canEditClientOfferData($quote)) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'La oferta ya esta aprobada o emitida. No se pueden cambiar los datos comerciales.']);

            return;
        }

        $this->validate([
            'clientOfferTaxPercent' => ['required', 'integer', 'min:0', 'max:100'],
        ], [
            'clientOfferTaxPercent.required' => 'El IVA es obligatorio.',
            'clientOfferTaxPercent.numeric' => 'El IVA debe ser numerico.',
            'clientOfferTaxPercent.min' => 'El IVA no puede ser negativo.',
            'clientOfferTaxPercent.max' => 'El IVA no puede superar 100%.',
        ]);

        $quote = $this->prepareClientOfferForEditing($quote);
        $subtotal = round((float) $quote->items()->where('is_approved', true)->sum('line_total'), 2);
        $taxPercent = $this->defaultTaxPercent($this->clientOfferTaxPercent);

        if ((float) $quote->tax_percent !== (float) $taxPercent) {
            // A previously generated PDF no longer represents the current offer.
            $quote->forceFill(['client_offer_pdf_path' => null, 'client_offer_generated_at' => null]);
        }

        $quote->forceFill([
            'tax_percent' => $taxPercent,
            'subtotal' => $subtotal,
            'total_amount' => round($subtotal * (1 + ($taxPercent / 100)), 2),
        ])->save();

        $this->clientOfferTaxPercent = (string) $taxPercent;
        $this->dispatch('notify', ['type' => 'success', 'message' => 'IVA de oferta cliente actualizado.']);
    }

    public function saveCompanyIssuer(): void
    {
        $quote = $this->selectedQuote();
        if (! $quote) {
            return;
        }

        $validCompanies = ['quantum', 'powersales', 'marcos_leiva'];
        $company = in_array($this->companyIssuer, $validCompanies, true) ? $this->companyIssuer : 'quantum';

        $quote->forceFill(['company_issuer' => $company])->save();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Empresa emisora / formato de oferta actualizado.']);
    }

    public function updatedCompanyIssuer(string $value): void
    {
        $this->saveCompanyIssuer();
    }

    public function saveClientOfferNotes(): void
    {
        $quote = $this->selectedQuote();
        if (! $quote || ! $this->canManageClientOfferEdits()) {
            return;
        }

        $this->validate([
            'offerNotes' => ['nullable', 'string', 'max:5000'],
            'offerValidity' => ['nullable', 'string', 'max:100'],
            'offerPaymentTerms' => ['nullable', 'string', 'max:100'],
            'offerDeliveryTime' => ['nullable', 'string', 'max:100'],
        ], [
            'offerPaymentTerms.max' => 'La condición de pago no puede superar 100 caracteres.',
            'offerDeliveryTime.max' => 'El tiempo de entrega no puede superar 100 caracteres.',
        ]);

        $validCompanies = ['quantum', 'powersales', 'marcos_leiva'];
        $company = in_array($this->companyIssuer, $validCompanies, true) ? $this->companyIssuer : 'quantum';

        $quote->forceFill([
            'company_issuer' => $company,
            'offer_notes' => $this->offerNotes,
            'validity_days' => $this->offerValidity,
            'payment_terms' => $this->offerPaymentTerms,
            'delivery_time' => $this->offerDeliveryTime,
            'currency' => 'NIO',
        ])->save();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Detalles de la oferta guardados.']);
    }

    public function generateClientOfferPdf(): void
    {
        $quote = $this->selectedQuote();
        if (! $quote) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'No se encontró la solicitud seleccionada.']);

            return;
        }

        try {
            $pdfService = app(PdfService::class);

            app(ExchangeRateService::class)->applyToClientOffer($quote);

            $validCompanies = ['quantum', 'powersales', 'marcos_leiva'];
            $company = in_array($this->companyIssuer, $validCompanies, true) ? $this->companyIssuer : 'quantum';

            $quote->forceFill([
                'company_issuer' => $company,
                'offer_notes' => $this->offerNotes,
                'validity_days' => $this->offerValidity,
                'payment_terms' => $this->offerPaymentTerms,
                'delivery_time' => $this->offerDeliveryTime,
                'currency' => 'NIO',
            ])->save();

            if ($this->canEditClientOfferData($quote)) {
                $this->saveClientOfferTaxPercent();
            }

            $pdfService->generateClientOfferPdf($quote->fresh(), auth()->id());
            $this->selectedQuoteId = $quote->id;

            $downloadUrl = route('sales.offers.download-final-pdf', $quote->id);
            $this->dispatch('download-file', ['url' => $downloadUrl]);
            $this->dispatch('notify', ['type' => 'success', 'message' => 'PDF final generado. La descarga iniciará automáticamente.']);
        } catch (ValidationException $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => collect($e->errors())->flatten()->first()]);
        } catch (Throwable $e) {
            report($e);
            $this->dispatch('notify', ['type' => 'error', 'message' => \App\Support\UserMessage::error($e, 'No se pudo generar el PDF. Intenta de nuevo.')]);
        }
    }

    public function requestClientOfferReview(ClientOfferReviewService $reviewService): void
    {
        $quote = $this->selectedQuote();
        if (! $quote) {
            return;
        }

        try {
            app(ExchangeRateService::class)->applyToClientOffer($quote);
            $this->saveClientOfferTaxPercent();
            $validCompanies = ['quantum', 'powersales', 'marcos_leiva'];
            $company = in_array($this->companyIssuer, $validCompanies, true) ? $this->companyIssuer : 'quantum';

            $quote->forceFill([
                'company_issuer' => $company,
                'offer_notes' => $this->offerNotes,
                'validity_days' => $this->offerValidity,
                'payment_terms' => $this->offerPaymentTerms,
                'delivery_time' => $this->offerDeliveryTime,
                'currency' => 'NIO',
            ])->save();

            $reviewService->requestReview($quote, auth()->user(), 'Revisión solicitada para emitir Oferta al Cliente');

            $this->dispatch('notify', ['type' => 'success', 'message' => 'Oferta enviada a revisión administrativa.']);
        } catch (ValidationException $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => collect($e->errors())->flatten()->first()]);
        } catch (Throwable $e) {
            report($e);
            $this->dispatch('notify', ['type' => 'error', 'message' => \App\Support\UserMessage::error($e, 'No se pudo completar la acción. Revisa los datos e intenta de nuevo.')]);
        }
    }
}
