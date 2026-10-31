<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('order_status_items') && ! Schema::hasColumn('order_status_items', 'waiting_for_item_ids')) {
            Schema::table('order_status_items', function (Blueprint $table) {
                $table->json('waiting_for_item_ids')->nullable()->after('quantity_pending');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('order_status_items') && Schema::hasColumn('order_status_items', 'waiting_for_item_ids')) {
            Schema::table('order_status_items', function (Blueprint $table) {
                $table->dropColumn('waiting_for_item_ids');
            });
        }
    }
};
