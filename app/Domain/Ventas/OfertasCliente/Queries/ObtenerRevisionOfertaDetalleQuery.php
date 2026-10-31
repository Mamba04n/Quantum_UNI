<?php

namespace App\Domain\Ventas\OfertasCliente\Queries;

use App\Models\ClientOfferReview;

class ObtenerRevisionOfertaDetalleQuery
{
    public function ejecutar(?int $revisionId): ?ClientOfferReview
    {
        if (! $revisionId) {
            return null;
        }

        return ClientOfferReview::with([
            'quotationRequest.items.supplierBidSelection.responseItem.response.supplier',
            'quotationRequest.supplierBidResponses.supplier',
            'quotationRequest.supplierBidResponses.supplierDocumentAttachments',
            'quotationRequest.supplierBidResponses.items.quotationItem',
            'quotationRequest.supplierBidSelections.responseItem.response.supplier',
            'quotationRequest.supplierRfqDocuments.supplier',
            'quotationRequest.user',
            'offerVersion.items.quotationItem.supplierBidSelection.responseItem.response.supplier',
            'offerVersion.items.quotationItem.selectedSupplier',
            'requester',
        ])->find($revisionId);
    }
}
