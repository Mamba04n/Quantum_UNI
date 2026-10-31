<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Clarify tracking codes
        if (Schema::hasTable('order_statuses') && Schema::hasColumn('order_statuses', 'tracking_code')) {
            Schema::table('order_statuses', function (Blueprint $table) {
                $table->renameColumn('tracking_code', 'administrative_tracking_code');
                $table->unique('order_tracker_id');
            });
        }

        if (Schema::hasTable('order_trackers') && Schema::hasColumn('order_trackers', 'tracking_code')) {
            Schema::table('order_trackers', function (Blueprint $table) {
                $table->renameColumn('tracking_code', 'operational_tracking_code');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('order_statuses') && Schema::hasColumn('order_statuses', 'administrative_tracking_code')) {
            Schema::table('order_statuses', function (Blueprint $table) {
                $table->renameColumn('administrative_tracking_code', 'tracking_code');
                $table->dropUnique(['order_tracker_id']);
            });
        }

        if (Schema::hasTable('order_trackers') && Schema::hasColumn('order_trackers', 'operational_tracking_code')) {
            Schema::table('order_trackers', function (Blueprint $table) {
                $table->renameColumn('operational_tracking_code', 'tracking_code');
            });
        }
    }
};
