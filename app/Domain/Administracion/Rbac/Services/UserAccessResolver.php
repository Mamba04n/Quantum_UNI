<?php

namespace App\Domain\Administracion\Rbac\Services;

use App\Domain\Administracion\Rbac\Data\UserAccess;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

final class UserAccessResolver
{
    /** @var array<int, UserAccess> */
    private array $resolved = [];

    private ?bool $tablesAvailable = null;

    public function resolve(User $user): UserAccess
    {
        if (! $user->exists || ! $this->tablesAvailable()) {
            return new UserAccess([], [], []);
        }

        return $this->resolved[$user->getKey()] ??= $this->load($user);
    }

    public function forget(User $user): void
    {
        if ($user->exists) {
            unset($this->resolved[$user->getKey()]);
        }

        $user->unsetRelation('roles');
        $user->unsetRelation('permissions');
    }

    private function tablesAvailable(): bool
    {
        return $this->tablesAvailable ??= Schema::hasTable('roles')
            && Schema::hasTable('permissions')
            && Schema::hasTable('role_user')
            && Schema::hasTable('permission_role')
            && Schema::hasTable('permission_user');
    }

    private function load(User $user): UserAccess
    {
        $user->loadMissing(['roles.permissions', 'permissions']);

        $roles = $user->roles;
        $permissions = $user->permissions
            ->concat($roles->flatMap(fn (Role $role) => $role->permissions))
            ->pluck('slug')
            ->filter()
            ->unique()
            ->values()
            ->all();

        return new UserAccess(
            roleSlugs: $roles->pluck('slug')->filter()->unique()->values()->all(),
            roleLabels: $roles->pluck('name')->filter()->unique()->values()->all(),
            permissionSlugs: $permissions,
        );
    }
}
