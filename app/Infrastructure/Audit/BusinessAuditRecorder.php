<?php

namespace App\Infrastructure\Audit;

use App\Models\AuditLog;
use App\Models\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class BusinessAuditRecorder
{
    public function model(Model $model, string $action, bool $fromTrait = false): void
    {
        if (! str_starts_with($model::class, 'App\\Models\\')
            || in_array(class_basename($model), ['AuditLog', 'ActivityLog', 'PersonalAccessToken', 'FinanceDeviceEnrollment', 'IntegrationRequest', 'OrderPhaseNotificationDelivery'], true)
            || (! $fromTrait && in_array(LogsActivity::class, class_uses_recursive($model), true))) {
            return;
        }

        $changes = $action === 'updated' ? $model->getChanges() : $model->getAttributes();
        unset($changes['created_at'], $changes['updated_at']);
        if ($action === 'updated' && ! $changes) {
            return;
        }

        $this->record($action, $model::class, is_numeric($model->getKey()) ? (int) $model->getKey() : null, [
            'module' => AuditContext::module($model::class),
            'changes' => $changes,
            'original' => $action === 'updated' ? array_intersect_key($model->getRawOriginal(), $changes) : [],
            'method' => request()->method(),
            'path' => AuditContext::path(request()->path()),
            'ip' => request()->ip(),
        ]);
    }

    public function record(string $action, ?string $entityType, ?int $entityId, array $metadata, ?int $userId = null): void
    {
        try {
            AuditLog::create([
                'user_id' => $userId ?? auth()->id(),
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'metadata' => AuditContext::sanitize($metadata),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // An unavailable audit store must not replace the original response/error.
            Log::error('audit.record_failed', ['action' => $action, 'exception' => $e::class]);
        }
    }
}
