<?php

namespace App\Domain\Ventas\FlujoTrabajo\Queries;

use App\Models\QuotationStageChangeRequest;
use Illuminate\Database\Eloquent\Collection;

class ListPendingQuotationStageChanges
{
    /** @return Collection<int, QuotationStageChangeRequest> */
    public function handle(): Collection
    {
        return QuotationStageChangeRequest::query()
            ->with(['quotationRequest', 'requester'])
            ->where('status', QuotationStageChangeRequest::STATUS_PENDING)
            ->latest()
            ->get();
    }
}
