<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logistics_shipments', function (Blueprint $table) {
            try {
                $table->dropUnique(['order_id']);
            } catch (\Throwable $e) {
                // Older/local databases may already allow multiple shipments per order.
            }

            if (! Schema::hasColumn('logistics_shipments', 'order_status_item_id')) {
                $table->foreignId('order_status_item_id')
                    ->nullable()
                    ->after('order_id')
                    ->constrained('order_status_items')
                    ->nullOnDelete();
            }

            $table->index(['order_id', 'order_status_item_id']);
        });
    }

    public function down(): void
    {
        Schema::table('logistics_shipments', function (Blueprint $table) {
            try {
                $table->dropIndex(['order_id', 'order_status_item_id']);
            } catch (\Throwable $e) {
                //
            }

            if (Schema::hasColumn('logistics_shipments', 'order_status_item_id')) {
                $table->dropConstrainedForeignId('order_status_item_id');
            }

            try {
                $table->unique('order_id');
            } catch (\Throwable $e) {
                //
            }
        });
    }
};
