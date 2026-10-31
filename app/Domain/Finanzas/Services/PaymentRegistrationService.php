<?php

namespace App\Domain\Finanzas\Services;

use App\Domain\Finanzas\Documents\Services\FinanceDocumentService;
use App\Models\Payment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class PaymentRegistrationService
{
    public function __construct(
        private readonly FinanceService $financeService,
        private readonly FinanceDocumentService $documentService,
    ) {}

    public function register(
        string $type,
        int $accountId,
        UploadedFile $evidence,
        ?string $partyName,
        array $paymentData,
    ): Payment {
        $evidenceHash = hash_file('sha256', $evidence->getRealPath());
        if ($evidenceHash === false) {
            throw new RuntimeException('No fue posible calcular la huella de la evidencia.');
        }

        $path = $this->documentService->store(
            $evidence,
            'payments',
            (string) ($paymentData['reference'] ?? ''),
            $partyName,
        );

        try {
            $payment = $this->financeService->applyPayment($type, $accountId, [
                ...$paymentData,
                'evidence_path' => $path,
                'evidence_sha256' => $evidenceHash,
            ]);

            if ($payment->payment_evidence_path !== $path) {
                $this->deleteEvidence($path, 'idempotent_replay', $type, $accountId);
            }

            return $payment;
        } catch (Throwable $exception) {
            $this->deleteEvidence($path, 'failed_registration', $type, $accountId);

            throw $exception;
        }
    }

    private function deleteEvidence(string $path, string $reason, string $type, int $accountId): void
    {
        try {
            $disk = Storage::disk('private');
            $deleted = $disk->delete($path);

            if (! $deleted && $disk->exists($path)) {
                Log::warning('No fue posible eliminar una evidencia financiera huerfana.', [
                    'path' => $path,
                    'reason' => $reason,
                    'account_type' => $type,
                    'account_id' => $accountId,
                ]);
            }
        } catch (Throwable $cleanupException) {
            Log::error('Fallo al intentar eliminar una evidencia financiera huerfana.', [
                'path' => $path,
                'reason' => $reason,
                'account_type' => $type,
                'account_id' => $accountId,
                'exception' => $cleanupException::class,
                'message' => $cleanupException->getMessage(),
            ]);
        }
    }
}
