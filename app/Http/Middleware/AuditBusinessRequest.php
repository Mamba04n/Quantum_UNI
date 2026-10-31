<?php

namespace App\Http\Middleware;

use App\Infrastructure\Audit\AuditContext;
use App\Infrastructure\Audit\BusinessAuditRecorder;
use Closure;
use Illuminate\Http\Request;

class AuditBusinessRequest
{
    public function handle(Request $request, Closure $next)
    {
        $actor = $request->user()?->id;
        $response = $next($request);
        $status = $response->getStatusCode();
        $calls = [];
        if ($request->hasHeader('X-Livewire')) {
            foreach (array_slice((array) $request->input('components', []), 0, 20) as $component) {
                foreach (array_slice((array) ($component['calls'] ?? []), 0, 20) as $call) {
                    $method = $call['method'] ?? '';
                    if (is_string($method) && ! str_starts_with($method, '$')) {
                        $calls[] = mb_substr($method, 0, 100);
                    }
                }
            }
        }

        // Polling/filter updates are not business movements. Payloads and parameters are never stored.
        $mutation = ! $request->isMethodSafe() && (! $request->hasHeader('X-Livewire') || $calls);
        if (($actor || $request->user()) && ($mutation || $status >= 400)) {
            app(BusinessAuditRecorder::class)->record('http.operation', null, null, [
                'module' => 'security', 'method' => $request->method(),
                'path' => AuditContext::path($request->path()), 'route' => $request->route()?->getName(),
                'status' => $status, 'calls' => $calls, 'ip' => $request->ip(),
                'outcome' => $status >= 400 ? 'rechazada' : 'respuesta emitida; consultar cambios asociados',
            ], $actor);
        }

        return $response;
    }
}
