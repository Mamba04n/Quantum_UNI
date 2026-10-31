<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('quotation_requests', 'sale_order_id')) {
                $table->foreignId('sale_order_id')->nullable()->after('user_id')->constrained('sale_orders')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('quotation_requests', function (Blueprint $table) {
            if (Schema::hasColumn('quotation_requests', 'sale_order_id')) {
                $table->dropConstrainedForeignId('sale_order_id');
            }
        });
    }
};
