<?php

namespace App\Models\Traits;

use App\Infrastructure\Audit\BusinessAuditRecorder;

trait LogsActivity
{
    protected static function bootLogsActivity()
    {
        static::created(function ($model) {
            static::logActivity($model, 'created');
        });

        static::updated(function ($model) {
            // Solo loggear si hubo cambios reales
            if ($model->isDirty()) {
                static::logActivity($model, 'updated', [
                    'changes' => $model->getChanges(),
                    'original' => array_intersect_key($model->getOriginal(), $model->getDirty()),
                ]);
            }
        });

        static::deleted(function ($model) {
            static::logActivity($model, 'deleted');
        });
    }

    protected static function logActivity($model, $action, $metadata = [])
    {
        app(BusinessAuditRecorder::class)->model($model, $action, true);
    }
}
