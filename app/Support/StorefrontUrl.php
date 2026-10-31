<?php

namespace App\Support;

use App\Enums\TechnicalCategory;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StorefrontUrl
{
    public static function productSlug(Product $product): string
    {
        $name = Str::slug((string) $product->name);
        $words = array_filter(explode('-', $name), fn ($word) => ! in_array($word, ['de', 'del', 'la', 'el', 'los', 'las', 'para', 'con', 'y'], true));
        $name = implode('-', $words);
        $part = Str::slug((string) $product->part_number);
        if ($part !== '' && ! str_contains('-'.$name.'-', '-'.$part.'-')) {
            $name = trim($name.'-'.$part, '-');
        }

        return $name !== '' ? $name : 'repuesto';
    }

    public static function product(Product $product): string
    {
        return route('storefront.products.show', ['product' => $product->getKey(), 'slug' => self::productSlug($product)]);
    }

    public static function catalog(?string $category = null, array $filters = []): string
    {
        $category = $category ?: null;
        abort_if($category !== null && TechnicalCategory::tryFrom($category) === null, 404);
        $filters = array_intersect_key($filters, array_flip(['search', 'marca', 'stock', 'type', 'sort', 'page']));
        $filters = array_filter($filters, fn ($value) => is_scalar($value) && (string) $value !== '' && $value !== false);

        return $category === null
            ? route('storefront.products.index', $filters)
            : route('storefront.products.category', ['category' => $category, ...$filters]);
    }

    public static function productImage(?string $image, ?string $fallback = null): string
    {
        $image = ltrim(str_replace('\\', '/', (string) $image), '/');

        if ($image === '') {
            return $fallback ?: asset('storefront/images/modules/products-spare-parts.webp');
        }

        if (preg_match('/^(https?:\/\/|data:image\/)/i', $image)) {
            return $image;
        }

        if (str_starts_with($image, 'products/')) {
            return Storage::disk('public')->url($image);
        }

        if (str_starts_with($image, 'storefront/')) {
            return Storage::disk('public')->url($image);
        }

        if (str_starts_with($image, 'storage/') || str_starts_with($image, 'assets/')) {
            return url($image);
        }

        if (str_contains($image, '/')) {
            return url($image);
        }

        return Storage::disk('public')->url('storefront/products/'.rawurlencode($image));
    }

    public static function blogImage(?string $image): string
    {
        $image = (string) $image;

        if (preg_match('/^(https?:\/\/|data:image\/)/i', $image)) {
            return $image;
        }

        return Storage::disk('public')->url('storefront/blog/'.rawurlencode(basename($image)));
    }
}
