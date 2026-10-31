<?php

namespace App\Domain\Finanzas\Api\Support;

use Illuminate\Http\Request;

final class ApiPagination
{
    public static function perPage(Request $request): int
    {
        return max(1, min(100, $request->integer('per_page', 25)));
    }
}
