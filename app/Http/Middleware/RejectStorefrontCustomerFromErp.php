<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RejectStorefrontCustomerFromErp
{
    /**
     * Clientes de la tienda no pueden ver el panel ERP (dashboard, etc.).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && $user->hasRole(User::ROLE_CLIENTE_TIENDA)) {
            return redirect()->away(rtrim(config('app.storefront_url'), '/').'/');
        }

        return $next($request);
    }
}
