<?php

namespace App\Domain\Administracion\Approvals\Support;

use App\Models\QuotationRequest;
use App\Services\OrderTrackerService;
use Illuminate\Validation\ValidationException;

class ApprovalValidator
{
    public static function assertCanRequestApproval(QuotationRequest $quotation): void
    {
        if (! in_array($quotation->status, [
            QuotationRequest::STATUS_CLIENT_APPROVED_TOTAL,
            QuotationRequest::STATUS_CLIENT_APPROVED_PARTIAL,
            OrderTrackerService::QUOTE_PENDING_APPROVAL,
        ], true)) {
            throw ValidationException::withMessages(['approval' => 'Primero debe registrarse la aprobacion del cliente.']);
        }

        if (! $quotation->client_offer_pdf_path) {
            throw ValidationException::withMessages(['approval' => 'Debe existir PDF de oferta cliente antes de solicitar aprobacion.']);
        }

        if ($quotation->items->isEmpty()) {
            throw ValidationException::withMessages(['approval' => 'La oferta no tiene partidas.']);
        }

        foreach ($quotation->items as $item) {
            if ((float) $item->quantity <= 0 || (float) $item->unit_price <= 0) {
                throw ValidationException::withMessages(['approval' => 'Todas las partidas deben tener cantidad y precio final.']);
            }
        }
    }
}
