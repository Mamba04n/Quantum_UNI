<?php

namespace App\Infrastructure\Audit;

use App\Models\AuditLog;
use Illuminate\Support\Carbon;

class AuditTrailService
{
    public function log(
        ?int $userId,
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        array $metadata = []
    ): AuditLog {
        return AuditLog::query()->create([
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => $metadata,
            'created_at' => Carbon::now(),
        ]);
    }
}
