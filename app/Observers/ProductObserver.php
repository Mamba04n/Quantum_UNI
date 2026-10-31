<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\ProductSkuGenerator;

class ProductObserver
{
    public function creating(Product $product): void
    {
        $product->sku = app(ProductSkuGenerator::class)->generate($product);
    }
}
