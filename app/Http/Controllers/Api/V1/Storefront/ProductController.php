<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Http\Resources\Api\V1\ProductResource;
use App\Models\Product;
use App\Services\Storefront\ProductService;
use App\Support\ApiResponse;
use App\Support\StorefrontUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController
{
    public function show(Product $product, ProductService $products): JsonResponse
    {
        if (! $product->active) {
            return ApiResponse::error('Product not found.', [], 404);
        }

        $product->image_url = StorefrontUrl::productImage($this->primaryImage($product));

        $gallery = [];

        if ($gallery === []) {
            $gallery[] = $product->image_url;
        }

        $related = $products->related($product)->map(fn (Product $item) => $this->withImage($item));

        return ApiResponse::success([
            'product' => (new ProductResource($product))->resolve(),
            'gallery' => $gallery,
            'related' => ProductResource::collection($related)->resolve(),
        ], 'Product found.');
    }

    public function featured(Request $request, ProductService $service): JsonResponse
    {
        $limit = (int) min(max((int) $request->query('limit', 8), 1), 24);

        $products = $service->featured($limit)
            ->map(fn (Product $product) => $this->withImage($product));

        return ApiResponse::success(
            ProductResource::collection($products)->resolve(),
            'Featured products found.',
            ['count' => $products->count()]
        );
    }

    private function withImage(Product $product): Product
    {
        $product->image_url = StorefrontUrl::productImage($this->primaryImage($product));

        return $product;
    }

    private function primaryImage(Product $product): ?string
    {
        return $product->image_path;
    }
}
