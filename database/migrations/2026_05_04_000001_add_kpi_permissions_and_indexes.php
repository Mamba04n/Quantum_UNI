<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        if (Schema::hasTable('permissions')) {
            foreach (config('rbac.permissions.kpi', []) as $slug => $name) {
                DB::table('permissions')->updateOrInsert(
                    ['slug' => $slug],
                    ['name' => $name, 'module' => 'kpi', 'created_at' => $now, 'updated_at' => $now]
                );
            }

            foreach (config('rbac.roles', []) as $roleSlug => $roleConfig) {
                $roleId = DB::table('roles')->where('slug', $roleSlug)->value('id');

                if (! $roleId) {
                    continue;
                }

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

        $this->indexIfMissing('client_offer_versions', 'kpi_offer_status_created_idx', ['status', 'created_at']);
        $this->indexIfMissing('client_offer_versions', 'kpi_offer_created_by_idx', ['created_by']);
        $this->indexIfMissing('client_offer_version_items', 'kpi_offer_item_brand_idx', ['brand']);
        $this->indexIfMissing('client_offer_version_items', 'kpi_offer_item_cost_price_idx', ['unit_cost', 'unit_price']);
        $this->indexIfMissing('quotation_requests', 'kpi_request_status_date_idx', ['status', 'date']);
        $this->indexIfMissing('sale_orders', 'kpi_order_status_date_idx', ['status', 'date']);
        $this->indexIfMissing('sale_orders', 'kpi_order_customer_idx', ['customer_id']);
        $this->indexIfMissing('sale_order_items', 'kpi_order_item_brand_idx', ['brand']);
        $this->indexIfMissing('sale_order_items', 'kpi_order_item_product_idx', ['product_id']);
        $this->indexIfMissing('products', 'kpi_product_brand_category_idx', ['brand', 'technical_category']);
        $this->indexIfMissing('products', 'kpi_product_stock_cost_idx', ['stock_on_hand', 'cost']);
    }

    public function down(): void
    {
        foreach ([
            ['client_offer_versions', 'kpi_offer_status_created_idx'],
            ['client_offer_versions', 'kpi_offer_created_by_idx'],
            ['client_offer_version_items', 'kpi_offer_item_brand_idx'],
            ['client_offer_version_items', 'kpi_offer_item_cost_price_idx'],
            ['quotation_requests', 'kpi_request_status_date_idx'],
            ['sale_orders', 'kpi_order_status_date_idx'],
            ['sale_orders', 'kpi_order_customer_idx'],
            ['sale_order_items', 'kpi_order_item_brand_idx'],
            ['sale_order_items', 'kpi_order_item_product_idx'],
            ['products', 'kpi_product_brand_category_idx'],
            ['products', 'kpi_product_stock_cost_idx'],
        ] as [$table, $index]) {
            if (Schema::hasTable($table)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($index));
            }
        }
    }

    private function indexIfMissing(string $table, string $index, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $existing = collect(DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$index]))->isNotEmpty();

        if ($existing) {
            return;
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns, $index));
    }
};
