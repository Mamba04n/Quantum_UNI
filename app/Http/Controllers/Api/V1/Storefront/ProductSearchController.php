<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Http\Resources\Api\V1\ProductResource;
use App\Models\Product;
use App\Services\Storefront\ProductService;
use App\Support\ApiResponse;
use App\Support\StorefrontUrl;
use Illuminate\Http\Request;

class ProductSearchController
{
    public function __invoke(Request $request, ProductService $products)
    {
        $query = trim((string) $request->query('q', ''));

        if (mb_strlen($query) < 2) {
            return ApiResponse::success([], 'Search query is too short.', ['minimum_length' => 2]);
        }

        $products = $products
            ->search($query, (int) min(max((int) $request->query('limit', 8), 1), 24))
            ->map(function (Product $product) {
                $image = $product->image_path;
                $product->image_url = StorefrontUrl::productImage($image);

                return $product;
            });

        return ApiResponse::success(
            ProductResource::collection($products)->resolve(),
            'Products found.',
            ['count' => $products->count()]
        );
    }
}
