<?php

namespace App\Domain\Ventas\OfertasCliente\Actions;

use App\Domain\Ventas\OfertasCliente\Support\ClientOfferReviewActivity;
use App\Models\QuotationRequest;
use App\Models\User;
use App\Services\SalesWorkflowService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarkClientOfferSentToCustomer
{
    public function __construct(
        private readonly ClientOfferReviewActivity $activity
    ) {
    }

    public function execute(QuotationRequest $quotation, User $user): QuotationRequest
    {
        return DB::transaction(function () use ($quotation, $user) {
            $quotation = QuotationRequest::whereKey($quotation->id)->lockForUpdate()->firstOrFail();

            if ($quotation->status !== QuotationRequest::STATUS_FINAL_PDF_GENERATED) {
                throw ValidationException::withMessages(['offer' => 'Primero genera el PDF final autorizado.']);
            }

            if (blank($quotation->client_offer_pdf_path)) {
                throw ValidationException::withMessages(['offer' => 'No existe PDF final de oferta para enviar.']);
            }

            $oldStatus = $quotation->status;
            $quotation->forceFill([
                'status' => QuotationRequest::STATUS_CLIENT_RESPONSE_PENDING,
                'commercial_phase' => SalesWorkflowService::PHASE_APPROVAL,
                'client_offer_sent_at' => now(),
            ])->save();

            $version = $quotation->offerVersions()->latest('version_number')->first();
            $version?->forceFill(['status' => 'sent_to_customer', 'sent_at' => now()])->save();

            $this->activity->recordSentToCustomer($quotation, $user, $oldStatus);

            return $quotation->fresh('items');
        });
    }
}
