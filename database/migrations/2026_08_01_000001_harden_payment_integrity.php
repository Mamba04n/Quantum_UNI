<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('idempotency_key', 100)->nullable()->after('payment_code');
            $table->char('reference_fingerprint', 64)->nullable()->after('payment_reference');
        });

        DB::table('payments')
            ->whereNull('payment_code')
            ->orWhere('payment_code', '')
            ->orderBy('id')
            ->eachById(function (object $payment): void {
                DB::table('payments')->where('id', $payment->id)->update([
                    'payment_code' => 'PAY-'.Str::ulid(),
                ]);
            });

        $duplicateCodes = DB::table('payments')
            ->select('payment_code')
            ->whereNotNull('payment_code')
            ->groupBy('payment_code')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('payment_code');

        foreach ($duplicateCodes as $code) {
            DB::table('payments')
                ->where('payment_code', $code)
                ->orderBy('id')
                ->skip(1)
                ->each(function (object $payment): void {
                    DB::table('payments')->where('id', $payment->id)->update([
                        'payment_code' => 'PAY-'.Str::ulid(),
                    ]);
                });
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->unique('payment_code', 'payments_payment_code_unique');
            $table->unique('idempotency_key', 'payments_idempotency_key_unique');
            $table->unique('reference_fingerprint', 'payments_reference_fingerprint_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_payment_code_unique');
            $table->dropUnique('payments_idempotency_key_unique');
            $table->dropUnique('payments_reference_fingerprint_unique');
            $table->dropColumn(['idempotency_key', 'reference_fingerprint']);
        });
    }
};
