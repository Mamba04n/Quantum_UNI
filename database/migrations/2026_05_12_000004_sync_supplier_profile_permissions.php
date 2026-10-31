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

        foreach (config('rbac.permissions.purchases', []) as $slug => $name) {
            if (! str_starts_with($slug, 'supplier.')) {
                continue;
            }

            DB::table('permissions')->updateOrInsert(
                ['slug' => $slug],
                ['name' => $name, 'module' => 'purchases', 'created_at' => $now, 'updated_at' => $now]
            );
        }

        foreach (['admin', 'compras'] as $roleSlug) {
            $roleId = DB::table('roles')->where('slug', $roleSlug)->value('id');
            if (! $roleId) {
                continue;
            }

            $permissions = $roleSlug === 'admin'
                ? ['full_access']
                : config('rbac.roles.compras.permissions', []);

            $permissionIds = DB::table('permissions')->whereIn('slug', $permissions)->pluck('id');

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
        //
    }
};
