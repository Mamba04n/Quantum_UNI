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
        Schema::table('quotation_request_items', function (Blueprint $table) {
            $table->string('warranty_type')->nullable()->after('supplier_currency');
            $table->boolean('has_warranty_extension')->default(false)->after('warranty_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotation_request_items', function (Blueprint $table) {
            $table->dropColumn(['warranty_type', 'has_warranty_extension']);
        });
    }
};
