<?php

namespace App\Services\Integration;

use App\Models\IntegrationIdempotencyKey;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class IdempotencyService
{
    /**
     * Calcula el hash SHA-256 canónico de un array de datos, ordenando recursivamente
     * las claves para que el mismo contenido semántico genere idéntico hash.
     */
    public function canonicalHash(array $data): string
    {
        $normalized = $this->normalizeArray($data);

        return hash('sha256', json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function normalizeArray(mixed $value): mixed
    {
        if (! is_array($value)) {
            if (is_float($value)) {
                return (string) round($value, 4);
            }

            return $value;
        }

        $isAssoc = array_keys($value) !== range(0, count($value) - 1);
        if ($isAssoc) {
            ksort($value);
        }

        foreach ($value as $k => $v) {
            $value[$k] = $this->normalizeArray($v);
        }

        return $value;
    }

    /**
     * Verifica si existe un registro de idempotencia previo para el cliente y la clave.
     * Si coincide exactamente el hash, retorna la respuesta previa en caché.
     * Si el hash difiere, arroja un 409 Conflict.
     * Si no existe, retorna null para proceder con la ejecución.
     */
    public function check(int $clientId, string $idempotencyKey, string $requestHash): ?JsonResponse
    {
        $existing = IntegrationIdempotencyKey::query()
            ->where('integration_client_id', $clientId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if (! $existing) {
            return null;
        }

        if ($existing->request_hash !== $requestHash) {
            throw new HttpException(409, 'La clave de idempotencia ya fue utilizada previamente con un payload diferente.', null, [], 409);
        }

        $payload = $existing->response_payload ?? [];
        $payload['meta']['idempotent_replayed'] = true;

        return response()->json($payload, $existing->response_status);
    }

    /**
     * Registra el resultado exitoso o definitivo de la operación idempotente.
     */
    public function record(
        int $clientId,
        string $idempotencyKey,
        string $requestHash,
        string $operation,
        int $statusCode,
        array $payload,
        ?string $resourceType = null,
        ?int $resourceId = null
    ): IntegrationIdempotencyKey {
        return IntegrationIdempotencyKey::updateOrCreate(
            [
                'integration_client_id' => $clientId,
                'idempotency_key' => $idempotencyKey,
            ],
            [
                'request_hash' => $requestHash,
                'operation' => $operation,
                'response_status' => $statusCode,
                'response_payload' => $payload,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
            ]
        );
    }
}
