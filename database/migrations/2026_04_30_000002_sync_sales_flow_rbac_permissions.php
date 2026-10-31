<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            return;
        }

        $now = now();

        foreach (config('rbac.permissions', []) as $module => $permissions) {
            foreach ($permissions as $slug => $name) {
                DB::table('permissions')->updateOrInsert(
                    ['slug' => $slug],
                    [
                        'name' => $name,
                        'module' => $module,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }

        foreach (config('rbac.roles', []) as $slug => $roleConfig) {
            DB::table('roles')->updateOrInsert(
                ['slug' => $slug],
                [
                    'name' => $roleConfig['name'],
                    'description' => $roleConfig['description'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            $roleId = DB::table('roles')->where('slug', $slug)->value('id');
            $permissionIds = DB::table('permissions')
                ->whereIn('slug', $roleConfig['permissions'] ?? [])
                ->pluck('id');

            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->updateOrInsert(
                    ['role_id' => $roleId, 'permission_id' => $permissionId],
                    ['created_at' => $now, 'updated_at' => $now]
                );
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        DB::table('permissions')
            ->whereIn('slug', collect(config('rbac.permissions', []))->flatMap(fn ($permissions) => array_keys($permissions))->all())
            ->delete();
    }
};
