<?php

namespace App\Support;

use App\Models\QuotationRequest;
use Illuminate\Support\Facades\Storage;

class QuotationCover
{
    protected static array $resolvedCache = [];

    public function resolve(QuotationRequest $quote, bool $includeSupplierDocuments = false): ?array
    {
        $cacheKey = ($quote->id ?? 'new') . ':' . ($includeSupplierDocuments ? '1' : '0');
        if (array_key_exists($cacheKey, self::$resolvedCache)) {
            return self::$resolvedCache[$cacheKey];
        }

        $quote->loadMissing('items.product');
        $paths = $quote->items->pluck('image_path')
            ->prepend($quote->evidence_attachment_path);
        if ($includeSupplierDocuments) {
            $quote->loadMissing('supplierBidResponses.supplierDocumentAttachments');
            $paths = $paths->merge($quote->supplierBidResponses->pluck('attachment_path'))
                ->merge($quote->supplierBidResponses->pluck('supplier_document_path'))
                ->merge($quote->supplierBidResponses->flatMap(fn ($response) => $response->supplierDocumentAttachments->pluck('file_path')));
        }
        $paths = $paths->merge($quote->items->map(fn ($item) => $item->product?->image_path))
            ->filter()->unique();

        foreach ($paths as $path) {
            $path = str_replace('\\', '/', $path);
            if (str_starts_with($path, '/') || str_contains($path, ':') || str_contains($path, "\0") || in_array('..', explode('/', $path), true)) {
                continue;
            }
            if (! in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
                continue;
            }
            foreach (['private', 'public'] as $disk) {
                $storage = Storage::disk($disk);
                if ($storage->exists($path) && in_array($storage->mimeType($path), ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
                    return self::$resolvedCache[$cacheKey] = ['disk' => $disk, 'path' => $path];
                }
            }
        }

        return self::$resolvedCache[$cacheKey] = null;
    }
}
