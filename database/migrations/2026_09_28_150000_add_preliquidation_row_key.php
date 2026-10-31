<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('supplier_bid_response_items', function (Blueprint $table) {
            $table->uuid('preliquidation_key')->nullable()->unique();
            $table->unsignedBigInteger('quotation_request_item_id')->nullable()->change();
        });
    }
    public function down(): void
    {
        Schema::table('supplier_bid_response_items', fn (Blueprint $table) => $table->dropColumn('preliquidation_key'));
    }
};
