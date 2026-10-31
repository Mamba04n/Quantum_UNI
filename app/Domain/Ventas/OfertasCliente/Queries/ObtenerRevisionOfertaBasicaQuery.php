<?php

namespace App\Domain\Ventas\OfertasCliente\Queries;

use App\Models\ClientOfferReview;

class ObtenerRevisionOfertaBasicaQuery
{
    public function ejecutar(?int $revisionId): ?ClientOfferReview
    {
        if (! $revisionId) {
            return null;
        }

        return ClientOfferReview::with('quotationRequest', 'offerVersion')->find($revisionId);
    }
}
