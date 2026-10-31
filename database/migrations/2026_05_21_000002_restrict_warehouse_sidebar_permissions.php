<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions') || ! Schema::hasTable('permission_role')) {
            return;
        }

        $warehouseRoleId = DB::table('roles')->where('slug', 'bodega')->value('id');

        if (! $warehouseRoleId) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('slug', [
                'direct_order.view',
                'purchase_order.receive',
                'purchase_order.view',
                'order_status.view',
                'tracker.view',
                'tracker.comment',
                'tracker.attach_files',
                'tracker.view_kardex',
                'tracker.view_kardex_documents',
            ])
            ->pluck('id');

        DB::table('permission_role')
            ->where('role_id', $warehouseRoleId)
            ->whereIn('permission_id', $permissionIds)
            ->delete();
    }

    public function down(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions') || ! Schema::hasTable('permission_role')) {
            return;
        }

        $warehouseRoleId = DB::table('roles')->where('slug', 'bodega')->value('id');

        if (! $warehouseRoleId) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('slug', [
                'direct_order.view',
                'purchase_order.receive',
                'purchase_order.view',
                'order_status.view',
                'tracker.view',
                'tracker.comment',
                'tracker.attach_files',
                'tracker.view_kardex',
                'tracker.view_kardex_documents',
            ])
            ->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::table('permission_role')->updateOrInsert([
                'role_id' => $warehouseRoleId,
                'permission_id' => $permissionId,
            ], [
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
