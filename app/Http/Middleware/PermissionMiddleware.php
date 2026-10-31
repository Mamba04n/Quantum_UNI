<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    /**
     * Handle an incoming request.
     * Usage: ->middleware('permission:approve_order')
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $permissions = collect($permissions)
            ->flatMap(fn (string $permission) => array_map('trim', explode(',', $permission)))
            ->filter()
            ->values()
            ->all();

        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->hasAnyPermission($permissions)) {
            ActivityLog::log('security', 'permission_denied', 'Intento de acceso denegado por RBAC.', [
                'permissions' => $permissions,
                'path' => $request->path(),
                'method' => $request->method(),
            ]);

            abort(403, 'No tienes permiso para ejecutar esta accion.');
        }

        $response = $next($request);

        $sensitive = collect($permissions)->contains(fn (string $permission) => str_contains($permission, 'view_costs')
            || str_contains($permission, 'view_sensitive')
            || str_contains($permission, 'view_supplier_documents')
            || str_contains($permission, 'view_supplier_quotes')
            || str_contains($permission, 'view_invoices')
            || str_contains($permission, 'view_kardex_documents')
            || str_contains($permission, 'manage_sensitive_documents')
            || str_contains($permission, 'change_phase')
            || str_contains($permission, 'reopen_phase'));

        if ($sensitive) {
            ActivityLog::log('security', 'sensitive_permission_used', 'Permiso sensible utilizado.', [
                'permissions' => $permissions,
                'path' => $request->path(),
                'method' => $request->method(),
            ]);
        }

        return $response;
    }
}
