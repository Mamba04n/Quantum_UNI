<?php

namespace App\Domain\Ventas\OfertasCliente\Queries;

use App\Models\ClientOfferReview;
use Illuminate\Database\Eloquent\Collection;

class ListarRevisionesPendientesOfertaQuery
{
    public function ejecutar(): Collection
    {
        return ClientOfferReview::with([
            'quotationRequest.items.supplierBidSelection.responseItem.response.supplier',
            'quotationRequest.user',
            'offerVersion.items.quotationItem.supplierBidSelection.responseItem.response.supplier',
            'offerVersion.items.quotationItem.selectedSupplier',
            'requester',
        ])
            ->where('status', ClientOfferReview::STATUS_PENDING)
            ->latest('requested_at')
            ->get();
    }
}
