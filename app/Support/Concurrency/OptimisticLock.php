<?php

namespace App\Support\Concurrency;

use Illuminate\Database\Eloquent\Model;

final class OptimisticLock
{
    /**
     * @param  array<string, mixed>  $changes
     */
    public function update(Model $model, int $expectedVersion, array $changes): Model
    {
        $nextVersion = $expectedVersion + 1;

        if ($model->usesTimestamps()) {
            $changes[$model->getUpdatedAtColumn()] = $model->freshTimestampString();
        }

        $changes['version'] = $nextVersion;

        $updated = $model->newQuery()
            ->whereKey($model->getKey())
            ->where('version', $expectedVersion)
            ->update($changes);

        if ($updated !== 1) {
            throw new StaleModelException(
                $model->getTable(),
                $model->getKey(),
                $expectedVersion
            );
        }

        return $model->fresh();
    }
}
