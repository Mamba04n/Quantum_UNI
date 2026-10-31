<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class InventoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'product_id' => $this->id,
            'sku' => $this->sku,
            'part_number' => $this->part_number,
            'stock_on_hand' => (int) ($this->stock_on_hand ?? 0),
            'cost' => $this->cost,
            'total_cost' => $this->total_cost,
            'active' => (bool) $this->active,
        ];
    }
}
