<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('purchase_orders')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                if (! Schema::hasColumn('purchase_orders', 'approval_request_id')) {
                    $table->foreignId('approval_request_id')
                        ->nullable()
                        ->after('supplier_bid_response_id')
                        ->constrained('approval_requests')
                        ->nullOnDelete();
                }
            });

            try {
                Schema::table('purchase_orders', function (Blueprint $table) {
                    $table->unique(['approval_request_id', 'supplier_id'], 'purchase_orders_approval_supplier_unique');
                });
            } catch (\Throwable $e) {
                //
            }
        }

        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            return;
        }

        $now = now();
        $permissions = [
            'internal_approval.view_costs' => ['Ver costos en aprobacion interna', 'internal_approval'],
            'internal_approval.view_margin' => ['Ver margen en aprobacion interna', 'internal_approval'],
            'internal_approval.view_documents' => ['Ver documentos de aprobacion interna', 'internal_approval'],
            'purchase_order.generate' => ['Generar OC proveedor desde aprobacion', 'approval'],
            'foreign_order.generate' => ['Generar pedido extranjero desde aprobacion', 'approval'],
        ];

        foreach ($permissions as $slug => [$name, $module]) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $slug],
                ['name' => $name, 'module' => $module, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        $comprasRoleId = DB::table('roles')->where('slug', 'compras')->value('id');
        if ($comprasRoleId) {
            $permissionIds = DB::table('permissions')
                ->whereIn('slug', array_keys($permissions))
                ->pluck('id');

            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->updateOrInsert(
                    ['role_id' => $comprasRoleId, 'permission_id' => $permissionId],
                    ['created_at' => $now, 'updated_at' => $now]
                );
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('purchase_orders') && Schema::hasColumn('purchase_orders', 'approval_request_id')) {
            try {
                Schema::table('purchase_orders', function (Blueprint $table) {
                    $table->dropUnique('purchase_orders_approval_supplier_unique');
                });
            } catch (\Throwable $e) {
                //
            }

            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->dropConstrainedForeignId('approval_request_id');
            });
        }
    }
};
