<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFinanceApiUserActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless(
            $user instanceof User
            && $user->is_active
            && ! $user->hasRole(User::ROLE_CLIENTE_TIENDA)
            && $user->hasPermissionTo('finance.view'),
            403
        );

        return $next($request);
    }
}
