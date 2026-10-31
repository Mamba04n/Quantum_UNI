<?php

namespace App\Support\Concurrency;

use RuntimeException;

final class StaleModelException extends RuntimeException
{
    public function __construct(
        public readonly string $resourceType,
        public readonly int|string $resourceId,
        public readonly int $expectedVersion
    ) {
        parent::__construct('El registro fue modificado por otro usuario.');
    }
}
