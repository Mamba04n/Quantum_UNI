<?php

namespace App\Domain\Ventas\LicitacionesProveedor\Support;

use App\Models\Entity;
use App\Models\QuotationRequest;
use App\Models\SupplierBid;
use App\Models\SupplierBidResponse;
use App\Models\User;

class SupplierBidResponseRepository
{
    public function bidForSupplier(QuotationRequest $request, Entity $supplier, User $user): ?SupplierBid
    {
        if (! class_exists(SupplierBid::class)) {
            return null;
        }

        return SupplierBid::firstOrCreate(
            [
                'quotation_request_id' => $request->id,
                'supplier_id' => $supplier->id,
            ],
            [
                'reference' => 'BID-'.$request->reference.'-'.str_pad((string) (SupplierBid::where('quotation_request_id', $request->id)->count() + 1), 3, '0', STR_PAD_LEFT),
                'status' => 'response_received',
                'currency' => 'USD',
                'created_by' => $user->id,
            ]
        );
    }

    public function nextResponseCode(QuotationRequest $request, Entity $supplier): string
    {
        $prefix = str($supplier->name)->upper()->replaceMatches('/[^A-Z0-9]+/', '')->substr(0, 5)->toString() ?: 'SUP';
        $next = SupplierBidResponse::where('quotation_request_id', $request->id)
            ->where('supplier_id', $supplier->id)
            ->count() + 1;

        return "{$prefix}-".str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }
}
