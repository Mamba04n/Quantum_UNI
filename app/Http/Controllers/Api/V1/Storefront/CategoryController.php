<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CategoryController
{
    public function index(): JsonResponse
    {
        $categories = DB::table('products')
            ->selectRaw('type as slug, type as name, COUNT(*) as products_count')
            ->where('active', true)
            ->groupBy('type')
            ->orderBy('type')
            ->get()
            ->map(fn ($category) => [
                'id' => null,
                'slug' => (string) ($category->slug ?: 'sin_categoria'),
                'name' => (string) ($category->name ?: 'Sin categoria'),
                'products_count' => (int) $category->products_count,
            ])
            ->values();

        return ApiResponse::success($categories, 'Categories found.', ['count' => $categories->count()]);
    }
}
