<?php

namespace App\Http\Middleware;

use App\Infrastructure\Audit\AuditTrailService;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuditFinanceApiRequest
{
    public function __construct(private readonly AuditTrailService $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->requestId($request);
        $request->attributes->set('request_id', $requestId);

        $response = $next($request);
        $response->headers->set('X-Request-ID', $requestId);

        if ($request->isMethod('GET') && $request->user() instanceof User) {
            $token = $request->user()->currentAccessToken();
            $this->audit->log(
                $request->user()->id,
                $request->routeIs('api.v1.finance.documents.show')
                    ? 'finance.api_document.read'
                    : 'finance.api.read',
                null,
                null,
                [
                    'request_id' => $requestId,
                    'route' => $request->route()?->getName(),
                    'status' => $response->getStatusCode(),
                    'device_id' => $token instanceof PersonalAccessToken ? $token->device_id : null,
                    'ip_hash' => hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')),
                ]
            );
        }

        return $response;
    }

    private function requestId(Request $request): string
    {
        $provided = $request->header('X-Request-ID');

        return is_string($provided) && Str::isUuid($provided)
            ? $provided
            : (string) Str::uuid();
    }
}
