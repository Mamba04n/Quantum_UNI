<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Services\Storefront\CatalogService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CatalogController
{
    public function index(Request $request, CatalogService $catalogs): JsonResponse
    {
        if (! Schema::hasTable('catalogs')) {
            return ApiResponse::success([], 'Catalogs found.', ['count' => 0]);
        }

        $catalogs = $catalogs->active()
            ->when($request->boolean('featured'), fn ($items) => $items->where('destacado', true))
            ->take((int) min(max((int) $request->query('limit', 24), 1), 50))
            ->map(fn ($catalog) => [
                'id' => (int) $catalog->id,
                'name' => (string) ($catalog->nombre ?? $catalog->titulo),
                'title' => (string) ($catalog->titulo ?? $catalog->nombre),
                'description' => $catalog->descripcion,
                'category' => $catalog->categoria,
                'featured' => (bool) $catalog->destacado,
                'requires_login' => (bool) $catalog->requiere_login,
                'file' => (string) $catalog->archivo,
                'download_url' => route('api.v1.storefront.catalogs.download', ['file' => $catalog->archivo]),
            ])
            ->values();

        return ApiResponse::success($catalogs, 'Catalogs found.', ['count' => $catalogs->count()]);
    }

    public function download(string $file, CatalogService $catalogs): JsonResponse|BinaryFileResponse|StreamedResponse
    {
        $path = $catalogs->path($file);

        if ($path === null) {
            return ApiResponse::error('Catalog file not found.', [], 404);
        }

        return Storage::disk('public')->download($path, basename($path), [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
