<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait AuthorizesFinanceDocuments
{
    /** @param array<int, string> $legacyAssignedPermissions */
    protected function canViewFinanceDocument(
        User $user,
        ?int $responsibleUserId,
        array $legacyAssignedPermissions = []
    ): bool {
        if ($user->hasPermissionTo('finance.documents.view_all')) {
            return true;
        }

        if ($responsibleUserId === null || $responsibleUserId !== $user->id) {
            return false;
        }

        return $user->hasAnyPermission([
            'finance.documents.view_assigned',
            ...$legacyAssignedPermissions,
        ]);
    }
}
