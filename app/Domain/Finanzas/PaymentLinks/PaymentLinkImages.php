<?php

namespace App\Domain\Finanzas\PaymentLinks;

use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PaymentLinkImages
{
    private function source(?Product $product): ?string
    {
        if (! $product) {
            return null;
        }

        return $product->image_path ?: $product->images()->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id')->value('image_path');
    }

    public function preview(?Product $product): string
    {
        $source = $this->source($product);
        if (! $source) {
            return asset('storefront/images/product-placeholder.svg');
        }
        if (filter_var($source, FILTER_VALIDATE_URL) && str_starts_with($source, 'https://')) {
            return $source;
        }
        $path = ltrim(str_replace('\\', '/', $source), '/');
        if (str_contains($path, '..')) {
            return asset('storefront/images/product-placeholder.svg');
        }
        $storagePath = str_starts_with($path, 'storage/') ? substr($path, 8) : $path;
        if (Storage::disk('public')->exists($storagePath)) {
            return asset('storage/'.$storagePath);
        }
        if (str_starts_with($path, 'storefront/images/') && is_file(public_path($path))) {
            return asset($path);
        }

        return asset('storefront/images/product-placeholder.svg');
    }

    public function snapshot(?Product $product): ?string
    {
        $source = $this->source($product);
        if (! $source) {
            return null;
        }
        if (filter_var($source, FILTER_VALIDATE_URL) && str_starts_with($source, 'https://')) {
            return $source;
        }
        $path = ltrim(str_replace('\\', '/', $source), '/');
        if (str_contains($path, '..')) {
            return null;
        }
        $storagePath = str_starts_with($path, 'storage/') ? substr($path, 8) : $path;
        if (Storage::disk('public')->exists($storagePath)) {
            $bytes = Storage::disk('public')->get($storagePath);
        } elseif (str_starts_with($path, 'storefront/images/') && is_file(public_path($path))) {
            $bytes = file_get_contents(public_path($path));
        } else {
            return null;
        }
        if (strlen($bytes) > 5 * 1024 * 1024) {
            return null;
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
        if (! $ext) {
            return null;
        }
        $dest = 'payment-link-images/'.Str::uuid().'.'.$ext;
        Storage::disk('public')->put($dest,$bytes);

        return 'storage/'.$dest;
    }
}
