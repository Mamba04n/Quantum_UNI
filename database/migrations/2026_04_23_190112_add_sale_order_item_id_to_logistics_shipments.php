<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('logistics_shipments', function (Blueprint $table) {
            $table->unsignedBigInteger('sale_order_item_id')->nullable()->after('order_id');
            $table->foreign('sale_order_item_id')
                  ->references('id')->on('sale_order_items')
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('logistics_shipments', function (Blueprint $table) {
            $table->dropForeign(['sale_order_item_id']);
            $table->dropColumn('sale_order_item_id');
        });
    }
};
