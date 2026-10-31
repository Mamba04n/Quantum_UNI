<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_statuses', function (Blueprint $table) {
            $table->renameColumn('current_status', 'administrative_status');
            $table->renameColumn('current_phase', 'administrative_phase');
        });

        Schema::table('order_trackers', function (Blueprint $table) {
            $table->renameColumn('current_status', 'operational_status');
            $table->renameColumn('current_phase', 'operational_phase');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_statuses', function (Blueprint $table) {
            $table->renameColumn('administrative_status', 'status');
            $table->renameColumn('administrative_phase', 'phase');
        });

        Schema::table('order_trackers', function (Blueprint $table) {
            $table->renameColumn('operational_status', 'status');
            $table->renameColumn('operational_phase', 'phase');
        });
    }
};
