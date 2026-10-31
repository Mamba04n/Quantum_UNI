<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_request_items', function (Blueprint $table) {
            $table->foreignId('exchange_rate_id')->nullable()->after('exchange_rate')->constrained('exchange_rates')->nullOnDelete();
            $table->date('exchange_rate_date')->nullable()->after('exchange_rate_id');
            $table->string('exchange_rate_source_type', 40)->nullable()->after('exchange_rate_date');
            $table->boolean('exchange_rate_is_fallback')->default(false)->after('exchange_rate_source_type');
            $table->boolean('exchange_rate_is_stale')->default(false)->after('exchange_rate_is_fallback');
            $table->foreignId('exchange_rate_approved_by')->nullable()->after('exchange_rate_is_stale')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quotation_request_items', function (Blueprint $table) {
            $table->dropForeign(['exchange_rate_id']);
            $table->dropForeign(['exchange_rate_approved_by']);
            $table->dropColumn([
                'exchange_rate_id',
                'exchange_rate_date',
                'exchange_rate_source_type',
                'exchange_rate_is_fallback',
                'exchange_rate_is_stale',
                'exchange_rate_approved_by',
            ]);
        });
    }
};
