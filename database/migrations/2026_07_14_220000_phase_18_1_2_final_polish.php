<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Huérfanos: OC0001 (sale_order_id = 1 no existe)
        DB::statement('DELETE FROM order_statuses WHERE sale_order_id NOT IN (SELECT id FROM sale_orders)');
        DB::statement('DELETE FROM order_status_items WHERE sale_order_item_id IS NOT NULL AND sale_order_item_id NOT IN (SELECT id FROM sale_order_items)');
        DB::statement('DELETE FROM order_status_items WHERE purchase_order_item_id IS NOT NULL AND purchase_order_item_id NOT IN (SELECT id FROM purchase_order_items)');
        
        // Product orphans
        DB::statement('DELETE FROM product_images WHERE product_id NOT IN (SELECT id FROM products)');

        // 2. UNIQUE Code para administrativo
        try {
            DB::statement('ALTER TABLE order_statuses ADD UNIQUE INDEX order_statuses_admin_code_unique (administrative_tracking_code)');
        } catch (\Exception $e) { }

        // 3. Alinear cantidades decimales en todo el sistema
        $decimal18_4 = [
            'products' => ['stock_on_hand', 'reserved_stock'],
            'stock_moves' => ['quantity', 'balance_after'],
            'purchase_order_items' => ['received_quantity']
        ];
        
        foreach ($decimal18_4 as $table => $columns) {
            if (Schema::hasTable($table)) {
                foreach ($columns as $column) {
                    if (Schema::hasColumn($table, $column)) {
                        try {
                            DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` DECIMAL(18,4) NOT NULL DEFAULT 0.0000");
                        } catch (\Exception $e) { }
                    }
                }
            }
        }
        
        // 4. Quitar cascade de order_statuses -> sale_orders
        if (Schema::hasTable('order_statuses')) {
            try {
                DB::statement("ALTER TABLE `order_statuses` DROP FOREIGN KEY `order_statuses_sale_order_id_foreign`");
            } catch (\Exception $e) { }

            try {
                Schema::table('order_statuses', function (Blueprint $table) {
                    $table->foreign('sale_order_id')->references('id')->on('sale_orders')->nullOnDelete();
                });
            } catch (\Exception $e) {}
        }
    }

    public function down(): void
    {
    }
};
