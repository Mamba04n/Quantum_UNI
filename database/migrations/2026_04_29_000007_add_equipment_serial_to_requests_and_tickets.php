<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_requests', function (Blueprint $table) {
            $table->string('equipment_serial')->nullable()->after('machine_model');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->string('equipment_serial')->nullable()->after('machine_model');
        });
    }

    public function down(): void
    {
        Schema::table('quotation_requests', function (Blueprint $table) {
            $table->dropColumn('equipment_serial');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('equipment_serial');
        });
    }
};
