<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Orphan Column Cleanup
        if (Schema::hasColumn('catalogs', 'marca_id')) {
            // Validate there's no data first before dropping. If all are NULL, drop it.
            $count = DB::table('catalogs')->whereNotNull('marca_id')->count();
            if ($count === 0) {
                Schema::table('catalogs', function (Blueprint $table) {
                    $table->dropColumn('marca_id');
                });
            }
        }

        // 2. Fix Storefront Order Items relation
        if (Schema::hasTable('storefront_order_items')) {
            Schema::table('storefront_order_items', function (Blueprint $table) {
                if (Schema::hasColumn('storefront_order_items', 'producto_id')) {
                    $table->renameColumn('producto_id', 'product_id');
                }
            });

            Schema::table('storefront_order_items', function (Blueprint $table) {
                // Ensure foreign key exists for the renamed column
                $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');
            });
        }

        // 3. Fix Defaults for Order Statuses / Trackers
        if (Schema::hasTable('order_statuses')) {
            DB::statement("ALTER TABLE order_statuses ALTER COLUMN administrative_status SET DEFAULT 'solicitud'");
        }
        
        if (Schema::hasTable('order_trackers')) {
            DB::statement("ALTER TABLE order_trackers ALTER COLUMN operational_status SET DEFAULT 'abierto'");
        }
    }

    public function down(): void
    {
        // Reverse operations
        if (Schema::hasTable('storefront_order_items')) {
            Schema::table('storefront_order_items', function (Blueprint $table) {
                $table->dropForeign(['product_id']);
                $table->renameColumn('product_id', 'producto_id');
            });
        }

        if (Schema::hasColumn('catalogs', 'brand_id')) {
            Schema::table('catalogs', function (Blueprint $table) {
                $table->unsignedBigInteger('marca_id')->nullable();
            });
        }
    }
};
