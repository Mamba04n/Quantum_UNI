<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentLinkItem extends Model
{
    protected $guarded = [];

    public function getImageUrlAttribute(): string
    {
        $path = $this->image_path;

        return $path ? (str_starts_with($path, 'https://') ? $path : asset($path)) : asset('storefront/images/product-placeholder.svg');
    }

    protected $casts = ['unit_price' => 'decimal:2', 'line_total' => 'decimal:2', 'quantity' => 'integer'];
}
