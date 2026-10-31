<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('sale_orders', 'idempotency_key')) {
                $table->uuid('idempotency_key')->nullable()->after('source_type');
                $table->unique('idempotency_key', 'sale_orders_idempotency_key_unique');
            }
            if (! Schema::hasColumn('sale_orders', 'exchange_rate')) {
                $table->decimal('exchange_rate', 16, 6)->nullable()->after('currency');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sale_orders', function (Blueprint $table): void {
            if (Schema::hasColumn('sale_orders', 'idempotency_key')) {
                $table->dropUnique('sale_orders_idempotency_key_unique');
                $table->dropColumn('idempotency_key');
            }
            if (Schema::hasColumn('sale_orders', 'exchange_rate')) {
                $table->dropColumn('exchange_rate');
            }
        });
    }
};
