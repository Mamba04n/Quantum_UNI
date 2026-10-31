<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table): void {
            $table->string('email_fingerprint', 64)->nullable()->after('status');
            $table->string('tax_id_fingerprint', 64)->nullable()->after('email_fingerprint');
        });

        $emailCounts = [];
        $taxIdCounts = [];
        DB::table('entities')
            ->where('type', 'cliente')
            ->select(['id', 'email', 'rfc_ruc'])
            ->orderBy('id')
            ->chunkById(500, function ($customers) use (&$emailCounts, &$taxIdCounts): void {
                foreach ($customers as $customer) {
                    $emailFingerprint = $this->emailFingerprint($customer->email);
                    $taxIdFingerprint = $this->taxIdFingerprint($customer->rfc_ruc);
                    if ($emailFingerprint !== null) {
                        $emailCounts[$emailFingerprint] = ($emailCounts[$emailFingerprint] ?? 0) + 1;
                    }
                    if ($taxIdFingerprint !== null) {
                        $taxIdCounts[$taxIdFingerprint] = ($taxIdCounts[$taxIdFingerprint] ?? 0) + 1;
                    }
                }
            });

        DB::table('entities')
            ->where('type', 'cliente')
            ->select(['id', 'email', 'rfc_ruc'])
            ->orderBy('id')
            ->chunkById(500, function ($customers) use ($emailCounts, $taxIdCounts): void {
                foreach ($customers as $customer) {
                    $emailFingerprint = $this->emailFingerprint($customer->email);
                    $taxIdFingerprint = $this->taxIdFingerprint($customer->rfc_ruc);
                    $updates = [];
                    if ($emailFingerprint !== null && ($emailCounts[$emailFingerprint] ?? 0) === 1) {
                        $updates['email_fingerprint'] = $emailFingerprint;
                    }
                    if ($taxIdFingerprint !== null && ($taxIdCounts[$taxIdFingerprint] ?? 0) === 1) {
                        $updates['tax_id_fingerprint'] = $taxIdFingerprint;
                    }
                    if ($updates !== []) {
                        DB::table('entities')->where('id', $customer->id)->update($updates);
                    }
                }
            });

        Schema::table('entities', function (Blueprint $table): void {
            $table->unique('email_fingerprint', 'entities_email_fingerprint_unique');
            $table->unique('tax_id_fingerprint', 'entities_tax_id_fingerprint_unique');
        });
    }

    public function down(): void
    {
        Schema::table('entities', function (Blueprint $table): void {
            $table->dropUnique('entities_email_fingerprint_unique');
            $table->dropUnique('entities_tax_id_fingerprint_unique');
            $table->dropColumn(['email_fingerprint', 'tax_id_fingerprint']);
        });
    }

    private function emailFingerprint(?string $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));

        return $email === '' ? null : hash('sha256', 'email:'.$email);
    }

    private function taxIdFingerprint(?string $taxId): ?string
    {

        $taxId = strtoupper(preg_replace('/[\s-]+/', '', trim((string) $taxId)) ?? '');

        return $taxId === '' ? null : hash('sha256', 'tax:'.$taxId);
    }
};
