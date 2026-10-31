<?php

return [
    'transaction_attempts' => max(1, (int) env('CONCURRENCY_TRANSACTION_ATTEMPTS', 3)),
    'slow_operation_ms' => max(1, (int) env('CONCURRENCY_SLOW_OPERATION_MS', 1000)),
    'log_successful_operations' => (bool) env('CONCURRENCY_LOG_SUCCESSFUL_OPERATIONS', true),
];
