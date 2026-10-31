<?php

use App\Domain\Finanzas\Services\HistoricalPaymentFingerprintBackfill;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->char('evidence_sha256', 64)->nullable()->after('payment_evidence_path');
            $table->char('request_fingerprint', 64)->nullable()->after('idempotency_key')->index();
        });

        Schema::create('finance_payment_migration_conflicts', function (Blueprint $table) {
            $table->id();
            $table->string('conflict_type', 50);
            $table->char('fingerprint', 64)->index();
            $table->json('payment_ids');
            $table->json('references');
            $table->timestamp('detected_at');
        });

        app(HistoricalPaymentFingerprintBackfill::class)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_payment_migration_conflicts');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['request_fingerprint']);
            $table->dropColumn(['evidence_sha256', 'request_fingerprint']);
        });
    }
};
