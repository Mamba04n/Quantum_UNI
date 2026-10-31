<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'part_number' => $this->part_number,
            'brand' => $this->brand,
            'model' => $this->model,
            'type' => $this->type?->value ?? $this->type,
            'technical_category' => $this->technical_category?->value ?? $this->technical_category,
            'stock_on_hand' => (int) ($this->stock_on_hand ?? 0),
            'sales_price' => $this->sales_price,
            'active' => (bool) $this->active,
            'image_url' => $this->when(isset($this->image_url), $this->image_url),
        ];
    }
}
