<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Storefront\CatalogService;
use Illuminate\Support\Facades\Storage;

class CatalogController extends Controller
{
    public function index(CatalogService $catalogs)
    {
        return view('storefront.catalogs.index', ['catalogs' => $catalogs->active()]);
    }

    public function download(string $file, CatalogService $catalogs)
    {
        $path = $catalogs->path($file);
        abort_unless($path, 404);

        return Storage::disk('public')->download($path, basename($path));
    }
}
