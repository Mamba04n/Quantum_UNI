<?php

namespace App\Support;

use App\Models\Product;

class StorefrontPrice
{
    public static function label(Product $product): string
    {
        if ((float) $product->sales_price <= 0) {
            return 'Consultar precio';
        }

        $currency = config('services.storefront.catalog_currency', 'NIO');
        $prefix = match ($currency) {
            'USD' => 'US$',
            'NIO' => 'C$',
            default => $currency.' ',
        };

        return $prefix.number_format((float) $product->sales_price, 2, '.', ',');
    }
}
