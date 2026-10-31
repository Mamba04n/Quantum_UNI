<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_bid_items', function (Blueprint $table) {
            $table->boolean('is_quoted')->default(true)->after('currency');
            $table->date('estimated_arrival_date')->nullable()->after('delivery_time');
            $table->string('part_model')->nullable()->after('estimated_arrival_date');
            $table->string('serial_number')->nullable()->after('part_model');
            $table->string('brand')->nullable()->after('serial_number');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_bid_items', function (Blueprint $table) {
            $table->dropColumn(['is_quoted', 'estimated_arrival_date', 'part_model', 'serial_number', 'brand']);
        });
    }
};
