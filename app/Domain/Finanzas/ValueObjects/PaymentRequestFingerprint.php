<?php

namespace App\Domain\Finanzas\ValueObjects;

use App\Models\Payment;
use Carbon\CarbonInterface;
use InvalidArgumentException;
use JsonException;

final class PaymentRequestFingerprint
{
    /**
     * @throws JsonException
     */
    public static function fromInput(string $type, int $accountId, array $data): string
    {
        return self::hash(self::normalize([
            'type' => $type,
            'account_id' => $accountId,
            'amount' => $data['amount'] ?? null,
            'currency' => $data['currency'] ?? 'NIO',
            'exchange_rate' => $data['exchange_rate'] ?? 1,
            'method' => $data['method'] ?? null,
            'reference' => $data['reference'] ?? null,
            'date' => $data['date'] ?? now()->toDateString(),
            'evidence_sha256' => $data['evidence_sha256'] ?? self::legacyEvidenceHash($data['evidence_path'] ?? null),
            'registered_by' => $data['registered_by'] ?? auth()->id(),
            'notes' => $data['notes'] ?? null,
        ]));
    }

    /**
     * @throws JsonException
     */
    public static function fromPayment(Payment $payment): string
    {
        $type = $payment->payment_type === 'customer_payment' ? 'cxc' : 'cxp';
        $accountId = $type === 'cxc' ? $payment->accounts_receivable_id : $payment->accounts_payable_id;

        return self::hash(self::normalize([
            'type' => $type,
            'account_id' => $accountId,
            'amount' => $payment->amount_original ?? $payment->amount,
            'currency' => $payment->currency ?? 'NIO',
            'exchange_rate' => $payment->exchange_rate ?? 1,
            'method' => $payment->payment_method,
            'reference' => $payment->payment_reference ?? $payment->receipt_reference,
            'date' => $payment->payment_date,
            'evidence_sha256' => $payment->evidence_sha256 ?? self::legacyEvidenceHash($payment->payment_evidence_path),
            'registered_by' => $payment->registered_by,
            'notes' => $payment->notes,
        ]));
    }

    public static function reference(string $paymentType, string $reference): string
    {
        return hash('sha256', $paymentType.'|'.mb_strtolower(trim($reference)));
    }

    public static function legacyEvidenceHash(mixed $path): ?string
    {
        $path = trim((string) $path);

        return $path === '' ? null : hash('sha256', 'legacy-path:'.$path);
    }

    private static function normalize(array $data): array
    {
        $type = (string) $data['type'];
        if (! in_array($type, ['cxc', 'cxp'], true)) {
            throw new InvalidArgumentException('El tipo de cuenta debe ser CXC o CXP.');
        }

        $currency = strtoupper(trim((string) $data['currency']));
        if (! in_array($currency, ['NIO', 'USD'], true)) {
            throw new InvalidArgumentException('La moneda del pago debe ser NIO o USD.');
        }

        $method = trim((string) $data['method']);
        $reference = trim((string) $data['reference']);
        $evidenceHash = strtolower(trim((string) $data['evidence_sha256']));

        if ($method === '' || $reference === '' || ! preg_match('/^[a-f0-9]{64}$/', $evidenceHash)) {
            throw new InvalidArgumentException('La operacion no contiene todos los datos requeridos.');
        }

        return [
            'type' => $type,
            'account_id' => (int) $data['account_id'],
            'amount' => DecimalMoney::amount($data['amount']),
            'currency' => $currency,
            'exchange_rate' => $currency === 'USD'
                ? DecimalMoney::exchangeRate($data['exchange_rate'])
                : DecimalMoney::exchangeRate(1),
            'method' => mb_strtolower($method),
            'reference' => mb_strtolower($reference),
            'date' => self::date($data['date']),
            'evidence_sha256' => $evidenceHash,
            'registered_by' => $data['registered_by'] === null ? null : (int) $data['registered_by'],
            'notes' => self::notes($data['notes']),
        ];
    }

    /**
     * @throws JsonException
     */
    private static function hash(array $normalized): string
    {
        return hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    private static function date(mixed $value): string
    {
        if ($value instanceof CarbonInterface) {
            return $value->toDateString();
        }

        $value = trim((string) $value);
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches)
            || ! checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])) {
            throw new InvalidArgumentException('La fecha del pago no es valida.');
        }

        return $value;
    }

    private static function notes(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $notes = trim(str_replace(["\r\n", "\r"], "\n", (string) $value));

        return $notes === '' ? null : $notes;
    }
}
