<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pedidos_web')) {
            return;
        }

        Schema::table('pedidos_web', function (Blueprint $table) {
            if (! Schema::hasColumn('pedidos_web', 'payment_method')) {
                $table->string('payment_method', 30)->nullable()->after('moneda');
            }

            if (! Schema::hasColumn('pedidos_web', 'paypal_order_id')) {
                $table->string('paypal_order_id', 80)->nullable()->after('payment_method');
            }

            if (! Schema::hasColumn('pedidos_web', 'paypal_capture_id')) {
                $table->string('paypal_capture_id', 80)->nullable()->after('paypal_order_id');
            }

            if (! Schema::hasColumn('pedidos_web', 'payment_status')) {
                $table->string('payment_status', 30)->nullable()->after('paypal_capture_id');
            }

            if (! Schema::hasColumn('pedidos_web', 'raw_response')) {
                $table->json('raw_response')->nullable()->after('payment_status');
            }
        });

        $this->addIndexIfMissing('pedidos_web', 'idx_paypal_order_id', ['paypal_order_id']);
        $this->addIndexIfMissing('pedidos_web', 'idx_payment_status', ['payment_status']);
    }

    public function down(): void
    {
        // Keep payment audit data intact. Rollbacks should not drop payment traces.
    }

    private function addIndexIfMissing(string $table, string $index, array $columns): void
    {
        $exists = collect(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]))->isNotEmpty();

        if (! $exists) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns, $index) {
                $blueprint->index($columns, $index);
            });
        }
    }
};
