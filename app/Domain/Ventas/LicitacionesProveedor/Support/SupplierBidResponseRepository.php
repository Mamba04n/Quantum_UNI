<?php

namespace App\Domain\Ventas\LicitacionesProveedor\Support;

use App\Models\Entity;
use App\Models\QuotationRequest;
use App\Models\SupplierBid;
use App\Models\SupplierBidResponse;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SupplierBidResponseRepository
{
    public function bidForSupplier(QuotationRequest $request, Entity $supplier, User $user): SupplierBid
    {
        return DB::transaction(function () use ($request, $supplier, $user): SupplierBid {
            // All creation paths lock the same request, including legacy bid
            // entry and individual response saves, before checking existence.
            $request = QuotationRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();

            return SupplierBid::firstOrCreate(
                [
                    'quotation_request_id' => $request->id,
                    'supplier_id' => $supplier->id,
                ],
                [
                    // Historical bid labels survive request renumbering. Never
                    // derive uniqueness from those labels or from row counts.
                    'reference' => 'BID-'.$request->reference.'-Q'.$request->id.'-S'.$supplier->id,
                    'status' => 'response_received',
                    'currency' => 'USD',
                    'created_by' => $user->id,
                ]
            );
        });
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
