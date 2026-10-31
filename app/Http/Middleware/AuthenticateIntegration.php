<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Models\IntegrationClient;
use App\Models\PersonalAccessToken;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class AuthenticateIntegration
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $requestId = (string) Str::uuid();
        $provided = $request->header('X-Correlation-ID');
        $correlationId = is_string($provided) && Str::isUuid($provided) ? $provided : $requestId;
        $request->attributes->set('integration.request_id', $requestId);
        $request->attributes->set('integration.correlation_id', $correlationId);
        $client = null;
        $status = 500;

        try {
            $token = $request->bearerToken() ? PersonalAccessToken::findToken($request->bearerToken()) : null;
            $client = $token?->tokenable;
            $expiration = config('sanctum.expiration');
            if (! $token || ! $client instanceof IntegrationClient || ! $client->is_active
                || ! $token->expires_at || $token->expires_at->isPast()
                || ($expiration && $token->created_at->lte(now()->subMinutes((int) $expiration)))) {
                $response = $this->error(401, 'integration.unauthenticated', 'Credencial de integración inválida.');
            } elseif (in_array('*', $token->abilities, true) || ! in_array($ability, $token->abilities, true)) {
                $response = $this->error(403, 'integration.forbidden', 'La credencial no permite esta operación.');
            } else {
                $key = 'integration:client:'.$client->id;
                if (RateLimiter::tooManyAttempts($key, 60)) {
                    $response = $this->error(429, 'integration.rate_limited', 'Límite temporal de solicitudes.');
                    $response->headers->set('Retry-After', (string) RateLimiter::availableIn($key));
                } else {
                    $request->attributes->set('integration.client_id', $client->id);
                    RateLimiter::hit($key, 60);
                    $token->forceFill(['last_used_at' => now()])->save();
                    $response = $next($request);
                    // Laravel's routing pipeline may render controller exceptions
                    // before returning to this middleware. Normalize those too.
                    if ($response->getStatusCode() >= 500) {
                        $response = $this->error(503, 'integration.unavailable', 'Integración temporalmente no disponible.');
                    }
                }
            }
            $status = $response->getStatusCode();
        } catch (ValidationException) {
            $status = 422;
            $response = $this->error(422, 'integration.invalid_request', 'Parámetros de integración inválidos.');
        } catch (HttpExceptionInterface $e) {
            $status = $e->getStatusCode();
            $response = $this->error($status, $status === 422 ? 'integration.invalid_request' : 'integration.not_found', $status === 422 ? 'Parámetros de integración inválidos.' : 'Recurso no disponible.');
        } catch (\Throwable $e) {
            $status = 503;
            logger()->error('integration.request_failed', ['exception_type' => $e::class, 'request_id' => $requestId]);
            $response = $this->error(503, 'integration.unavailable', 'Integración temporalmente no disponible.');
        } finally {
            try {
                AuditLog::create([
                    'user_id' => null,
                    'action' => 'integration.request',
                    'entity_type' => 'integration_client',
                    'entity_id' => $client instanceof IntegrationClient ? $client->id : null,
                    'metadata' => [
                        'actor_type' => 'integration', 'request_id' => $requestId,
                        'correlation_id' => $correlationId, 'status' => $status,
                        'route' => $request->route()?->getName(),
                    ],
                    'created_at' => now(),
                ]);
            } catch (\Throwable) {
                // Fail closed without leaking SQL or stack traces even with APP_DEBUG.
                $response = $this->error(503, 'integration.audit_unavailable', 'Integración temporalmente no disponible.');
            }
        }

        if ($response instanceof JsonResponse) {
            $payload = $response->getData(true);
            $payload['meta']['request_id'] = $requestId;
            $payload['meta']['correlation_id'] = $correlationId;
            $response->setData($payload);
        }
        $response->headers->set('X-Request-ID', $requestId);
        $response->headers->set('X-Correlation-ID', $correlationId);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    private function error(int $status, string $code, string $message): JsonResponse
    {
        return response()->json(['error' => compact('code', 'message')], $status);
    }
}
