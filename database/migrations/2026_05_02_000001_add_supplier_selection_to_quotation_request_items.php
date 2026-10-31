<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_request_items', function (Blueprint $table) {
            $table->foreignId('selected_supplier_bid_item_id')->nullable()->after('unit_cost')->constrained('supplier_bid_items')->nullOnDelete();
            $table->foreignId('selected_supplier_id')->nullable()->after('selected_supplier_bid_item_id')->constrained('entities')->nullOnDelete();
            $table->string('supplier_delivery_time')->nullable()->after('selected_supplier_id');
            $table->text('supplier_response_notes')->nullable()->after('supplier_delivery_time');
        });
    }

    public function down(): void
    {
        Schema::table('quotation_request_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('selected_supplier_bid_item_id');
            $table->dropConstrainedForeignId('selected_supplier_id');
            $table->dropColumn(['supplier_delivery_time', 'supplier_response_notes']);
        });
    }
};
