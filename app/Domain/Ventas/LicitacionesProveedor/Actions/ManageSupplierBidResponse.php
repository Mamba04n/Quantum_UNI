<?php

namespace App\Domain\Ventas\LicitacionesProveedor\Actions;

use App\Domain\Ventas\LicitacionesProveedor\Support\SupplierBidResponseRepository;
use App\Models\SupplierBidResponse;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageSupplierBidResponse
{
    public function __construct(private readonly SupplierBidResponseRepository $responses)
    {
    }

    public function delete(SupplierBidResponse $response, User $user): void
    {
        DB::transaction(function () use ($response) {
            $itemIds = $response->items()->pluck('id');
            \App\Models\SupplierBidSelection::whereIn('supplier_bid_response_item_id', $itemIds)->delete();
            $response->delete();
        });
    }

    public function duplicate(SupplierBidResponse $response, User $user): SupplierBidResponse
    {
        return DB::transaction(function () use ($response, $user) {
            $response->loadMissing('quotationRequest', 'supplier', 'items');

            $copy = $response->replicate(['response_code', 'status', 'created_by']);
            $copy->response_code = $this->responses->nextResponseCode($response->quotationRequest, $response->supplier);
            $copy->status = 'draft';
            $copy->created_by = $user->id;
            $copy->save();

            foreach ($response->items as $item) {
                $copy->items()->create($item->replicate(['supplier_bid_response_id'])->getAttributes());
            }

            return $copy->fresh(['supplier', 'items.quotationItem']);
        });
    }
}
