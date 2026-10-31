<?php

namespace App\Services\Storefront;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class CatalogService
{
    public function active(): Collection
    {
        if (! Schema::hasTable('catalogs')) {
            return collect();
        }

        return DB::table('catalogs')
            ->where('activo', true)
            ->orderByDesc('destacado')
            ->orderByDesc('created_at')
            ->get();
    }

    public function path(string $file): ?string
    {
        $file = basename($file);

        if ($file === '' || ! preg_match('/\.(pdf|docx?|xlsx?|pptx?)$/i', $file)) {
            return null;
        }

        $path = 'storefront/catalogs/'.$file;

        return Storage::disk('public')->exists($path) ? $path : null;
    }
}
