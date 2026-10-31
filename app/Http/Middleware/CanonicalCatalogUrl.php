<?php

namespace App\Http\Middleware;

use App\Enums\TechnicalCategory;
use App\Support\StorefrontUrl;
use Closure;
use Illuminate\Http\Request;

class CanonicalCatalogUrl
{
    public function handle(Request $request, Closure $next)
    {
        $category = $request->route('category') ?: $request->query('categoria');
        $category = $category === '' ? null : $category;
        abort_if($category !== null && (! is_string($category) || TechnicalCategory::tryFrom($category) === null), 404);
        if ($request->hasAny(['categoria', 'brand', 'inStock'])) {
            $filters = $request->query();
            $filters['marca'] = $filters['marca'] ?? $filters['brand'] ?? '';
            $filters['stock'] = $filters['stock'] ?? $filters['inStock'] ?? '';

            return redirect()->to(StorefrontUrl::catalog($category, $filters), 301);
        }

        return $next($request);
    }
}
