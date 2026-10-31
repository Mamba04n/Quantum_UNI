<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('order_statuses') && ! Schema::hasColumn('order_statuses', 'tracking_groups')) {
            Schema::table('order_statuses', function (Blueprint $table) {
                $table->json('tracking_groups')->nullable()->after('progress_percentage');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('order_statuses') && Schema::hasColumn('order_statuses', 'tracking_groups')) {
            Schema::table('order_statuses', function (Blueprint $table) {
                $table->dropColumn('tracking_groups');
            });
        }
    }
};
