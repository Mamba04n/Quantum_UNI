<?php

namespace App\Domain\Finanzas\Services;

use App\Domain\Finanzas\ValueObjects\PaymentRequestFingerprint;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class HistoricalPaymentFingerprintBackfill
{
    public function run(): void
    {
        $groups = [];

        Payment::query()->orderBy('id')->each(function (Payment $payment) use (&$groups): void {
            $reference = trim((string) ($payment->payment_reference ?: $payment->receipt_reference));
            if ($reference !== '' && $payment->payment_type) {
                $fingerprint = PaymentRequestFingerprint::reference($payment->payment_type, $reference);
                $groups[$fingerprint][] = ['id' => $payment->id, 'reference' => $reference];
            }

            $evidenceHash = $payment->evidence_sha256;
            if (! $evidenceHash && $payment->payment_evidence_path && Storage::disk('private')->exists($payment->payment_evidence_path)) {
                $evidenceHash = hash('sha256', Storage::disk('private')->get($payment->payment_evidence_path));
                $payment->evidence_sha256 = $evidenceHash;
            }

            if ($evidenceHash) {
                try {
                    $payment->request_fingerprint = PaymentRequestFingerprint::fromPayment($payment);
                } catch (Throwable) {
                    // Los datos históricos incompletos se conservan sin inventar valores.
                }
            }

            if ($payment->isDirty()) {
                $payment->save();
            }
        });

        foreach ($groups as $fingerprint => $entries) {
            if (count($entries) > 1) {
                DB::table('finance_payment_migration_conflicts')->updateOrInsert(
                    ['conflict_type' => 'duplicate_reference', 'fingerprint' => $fingerprint],
                    [
                        'payment_ids' => json_encode(array_column($entries, 'id'), JSON_THROW_ON_ERROR),
                        'references' => json_encode(array_column($entries, 'reference'), JSON_THROW_ON_ERROR),
                        'detected_at' => now(),
                    ]
                );

                continue;
            }

            DB::table('payments')
                ->where('id', $entries[0]['id'])
                ->whereNull('reference_fingerprint')
                ->update(['reference_fingerprint' => $fingerprint]);
        }
    }
}
