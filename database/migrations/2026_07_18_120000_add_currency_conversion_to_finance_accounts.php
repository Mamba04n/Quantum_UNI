<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['account_payables', 'account_receivables'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'exchange_rate')) {
                    $table->decimal('exchange_rate', 16, 6)->nullable()->after('amount_usd');
                }
            });
        }

        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'amount_original')) {
                $table->decimal('amount_original', 15, 2)->nullable()->after('amount');
            }
            if (! Schema::hasColumn('payments', 'exchange_rate')) {
                $table->decimal('exchange_rate', 16, 6)->nullable()->after('currency');
            }
        });

        // Earlier purchase-order CXP records copied a USD FOB total into the
        // NIO balance. Normalize only those clearly identifiable records.
        $fallbackRate = (float) (DB::table('exchange_rates')
            ->where('base_currency', 'USD')
            ->where('quote_currency', 'NIO')
            ->latest('rate_date')
            ->value('rate') ?: 36.5);

        $payables = DB::table('account_payables as payable')
            ->join('purchase_orders as purchase_order', 'purchase_order.reference_number', '=', 'payable.invoice_number')
            ->leftJoin('quotation_requests as quotation', 'quotation.id', '=', 'purchase_order.quotation_request_id')
            ->where('purchase_order.currency', 'USD')
            ->whereNotNull('payable.amount_usd')
            ->select('payable.id', 'payable.total_amount', 'payable.amount_usd', 'quotation.exchange_rate as quotation_exchange_rate')
            ->get();

        foreach ($payables as $payable) {
            if (abs((float) $payable->total_amount - (float) $payable->amount_usd) > 0.01) {
                continue;
            }

            $rate = (float) $payable->quotation_exchange_rate > 0
                ? (float) $payable->quotation_exchange_rate
                : $fallbackRate;

            DB::table('account_payables')->where('id', $payable->id)->update([
                'total_amount' => round((float) $payable->amount_usd * $rate, 2),
                'exchange_rate' => $rate,
                'currency' => 'NIO',
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $columns = array_filter(['amount_original', 'exchange_rate'], fn (string $column) => Schema::hasColumn('payments', $column));
            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
