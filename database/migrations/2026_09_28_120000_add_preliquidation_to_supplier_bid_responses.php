<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('supplier_bid_responses', function (Blueprint $table) {
            $table->json('preliquidation')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_bid_responses', fn (Blueprint $table) => $table->dropColumn('preliquidation'));
    }
};
