<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_statuses')) {
            return;
        }

        // Internal purchases have no sale order. Make that legacy relationship
        // optional and link the tracking record directly to its purchase order.
        try {
            DB::statement('ALTER TABLE order_statuses DROP FOREIGN KEY order_statuses_sale_order_id_foreign');
        } catch (Throwable) {
            // The constraint name varies on older installations.
        }

        DB::statement('ALTER TABLE order_statuses MODIFY sale_order_id BIGINT UNSIGNED NULL');

        try {
            DB::statement('ALTER TABLE order_statuses ADD CONSTRAINT order_statuses_sale_order_id_foreign FOREIGN KEY (sale_order_id) REFERENCES sale_orders(id) ON DELETE SET NULL');
        } catch (Throwable) {
            // Constraint may already exist after a partial deployment.
        }

        Schema::table('order_statuses', function (Blueprint $table) {
            if (! Schema::hasColumn('order_statuses', 'purchase_order_id')) {
                $table->foreignId('purchase_order_id')->nullable()->unique()->after('sale_order_id')->constrained('purchase_orders')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('order_statuses', 'purchase_order_id')) {
            Schema::table('order_statuses', function (Blueprint $table) {
                $table->dropConstrainedForeignId('purchase_order_id');
            });
        }
    }
};
