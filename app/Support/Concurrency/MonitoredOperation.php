<?php

namespace App\Support\Concurrency;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class MonitoredOperation
{
    public function run(
        string $operation,
        string $resourceType,
        int|string|null $resourceId,
        Closure $callback
    ): mixed {
        $operationId = (string) Str::uuid();
        $startedAt = hrtime(true);

        try {
            $result = $callback();
            $durationMs = $this->durationMs($startedAt);
            $context = $this->context($operationId, $operation, $resourceType, $resourceId, $durationMs);

            if ($durationMs >= (int) config('concurrency.slow_operation_ms', 1000)) {
                Log::warning('concurrency.operation.slow', $context);
            } elseif (config('concurrency.log_successful_operations', true)) {
                Log::info('concurrency.operation.completed', $context);
            }

            return $result;
        } catch (Throwable $exception) {
            $durationMs = $this->durationMs($startedAt);
            $context = array_merge(
                $this->context($operationId, $operation, $resourceType, $resourceId, $durationMs),
                [
                    'exception' => $exception::class,
                    'sql_state' => $exception instanceof QueryException ? $exception->errorInfo[0] ?? null : null,
                    'driver_code' => $exception instanceof QueryException ? $exception->errorInfo[1] ?? null : null,
                    'concurrency_failure' => $this->isConcurrencyFailure($exception),
                ]
            );

            Log::warning('concurrency.operation.failed', $context);

            throw $exception;
        }
    }

    private function context(
        string $operationId,
        string $operation,
        string $resourceType,
        int|string|null $resourceId,
        float $durationMs
    ): array {
        return [
            'operation_id' => $operationId,
            'operation' => $operation,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'user_id' => Auth::id(),
            'duration_ms' => $durationMs,
        ];
    }

    private function durationMs(int $startedAt): float
    {
        return round((hrtime(true) - $startedAt) / 1_000_000, 2);
    }

    private function isConcurrencyFailure(Throwable $exception): bool
    {
        if (! $exception instanceof QueryException) {
            return false;
        }

        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);

        return $sqlState === '40001' || in_array($driverCode, [1205, 1213], true);
    }
}
