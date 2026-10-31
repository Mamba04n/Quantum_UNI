<?php

namespace Tests\Feature;

use App\Domain\Finanzas\Documents\Services\FinanceDocumentService as DomainFinanceDocumentService;
use App\Domain\Finanzas\Services\FinanceService as DomainFinanceService;
use App\Domain\Finanzas\Services\HistoricalPaymentFingerprintBackfill;
use App\Domain\Finanzas\Services\PaymentRegistrationService as DomainPaymentRegistrationService;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Entity;
use App\Models\Payment;
use App\Services\FinanceService;
use App\Services\PaymentRegistrationService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class FinancePaymentIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_and_total_payments_update_the_locked_balance_exactly(): void
    {
        $account = $this->receivable('100.00');

        $first = $this->service()->applyPayment('cxc', $account->id, $this->payment([
            'amount' => '40.25',
            'reference' => 'BANK-CXC-001',
            'idempotency_key' => 'payment-attempt-001',
        ]));

        $this->assertSame('40.25', $first->amount);
        $this->assertSame('40.25', $account->fresh()->paid_amount);
        $this->assertSame('parcial', $account->fresh()->status);

        $this->service()->applyPayment('cxc', $account->id, $this->payment([
            'amount' => '59.75',
            'reference' => 'BANK-CXC-002',
            'idempotency_key' => 'payment-attempt-002',
        ]));

        $account = $account->fresh();
        $this->assertSame('100.00', $account->paid_amount);
        $this->assertSame('pagado', $account->status);
        $this->assertNotNull($account->collected_at);
    }

    public function test_an_overpayment_is_rejected_without_changing_the_account(): void
    {
        $account = $this->payable('50.00');

        $this->assertValidationError('amount', fn () => $this->service()->applyPayment(
            'cxp',
            $account->id,
            $this->payment(['amount' => '50.01'])
        ));

        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('0.00', $account->fresh()->paid_amount);
        $this->assertSame('pendiente', $account->fresh()->status);
    }

    public function test_the_same_idempotent_retry_returns_the_original_payment_once(): void
    {
        $account = $this->payable('80.00');
        $payload = $this->payment([
            'amount' => '25.00',
            'reference' => 'TRANSFER-777',
            'idempotency_key' => 'stable-request-key',
        ]);

        $first = $this->service()->applyPayment('cxp', $account->id, $payload);
        $retry = $this->service()->applyPayment('cxp', $account->id, $payload);

        $this->assertTrue($first->is($retry));
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame('25.00', $account->fresh()->paid_amount);
    }

    public function test_an_idempotency_key_cannot_be_reused_with_different_data(): void
    {
        $account = $this->receivable('80.00');
        $this->service()->applyPayment('cxc', $account->id, $this->payment([
            'reference' => 'DEPOSIT-1',
            'idempotency_key' => 'reused-key',
        ]));

        $this->assertValidationError('idempotency_key', fn () => $this->service()->applyPayment(
            'cxc',
            $account->id,
            $this->payment([
                'amount' => '11.00',
                'reference' => 'DEPOSIT-2',
                'idempotency_key' => 'reused-key',
            ])
        ));

        $this->assertDatabaseCount('payments', 1);
    }

    public function test_idempotency_fingerprint_rejects_changes_to_date_evidence_user_or_notes(): void
    {
        $account = $this->receivable('100.00');
        $original = $this->payment([
            'reference' => 'FULL-FINGERPRINT',
            'idempotency_key' => 'full-fingerprint-key',
            'date' => '2026-08-01',
            'evidence_path' => 'payments/original.pdf',
            'registered_by' => null,
            'notes' => 'Pago confirmado',
        ]);
        $this->service()->applyPayment('cxc', $account->id, $original);

        foreach ([
            ['date' => '2026-08-02'],
            ['evidence_path' => 'payments/other.pdf'],
            ['registered_by' => 999],
            ['notes' => 'Nota distinta'],
        ] as $change) {
            $this->assertValidationError('idempotency_key', fn () => $this->service()->applyPayment(
                'cxc',
                $account->id,
                array_replace($original, $change)
            ));
        }
    }

    public function test_uploaded_evidence_is_deleted_after_failure_or_idempotent_replay(): void
    {
        Storage::fake('private');
        $account = $this->payable('20.00');
        $payload = $this->payment([
            'reference' => 'FILE-CLEANUP',
            'idempotency_key' => 'file-cleanup-key',
        ]);
        unset($payload['evidence_path']);

        $first = app(PaymentRegistrationService::class)->register(
            'cxp',
            $account->id,
            UploadedFile::fake()->createWithContent('first.pdf', 'same-proof'),
            'Proveedor',
            $payload,
        );
        $retry = app(PaymentRegistrationService::class)->register(
            'cxp',
            $account->id,
            UploadedFile::fake()->createWithContent('retry.pdf', 'same-proof'),
            'Proveedor',
            $payload,
        );

        $this->assertTrue($first->is($retry));
        $this->assertCount(1, Storage::disk('private')->allFiles('finance/payments'));
        Storage::disk('private')->assertExists($first->payment_evidence_path);

        try {
            app(PaymentRegistrationService::class)->register(
                'cxp',
                $account->id,
                UploadedFile::fake()->createWithContent('overpay.pdf', 'other-proof'),
                'Proveedor',
                $this->payment(['amount' => '99.00', 'reference' => 'OVERPAY-FILE']),
            );
            $this->fail('Se esperaba que el sobrepago fuera rechazado.');
        } catch (ValidationException) {
            $this->assertCount(1, Storage::disk('private')->allFiles('finance/payments'));
        }
    }

    public function test_historical_reference_backfill_reports_collisions_and_fills_safe_rows(): void
    {
        Storage::fake('private');
        $firstAccount = $this->payable('100.00');
        $secondAccount = $this->payable('100.00');
        $safeAccount = $this->receivable('100.00');

        $duplicateOne = $this->historicalPayment($firstAccount, 'supplier_payment', 'LEGACY-DUP');
        $duplicateTwo = $this->historicalPayment($secondAccount, 'supplier_payment', ' legacy-dup ');
        $safe = $this->historicalPayment($safeAccount, 'customer_payment', 'LEGACY-SAFE');
        Storage::disk('private')->put($safe->payment_evidence_path, 'legacy-safe-proof');

        app(HistoricalPaymentFingerprintBackfill::class)->run();

        $this->assertNull($duplicateOne->fresh()->reference_fingerprint);
        $this->assertNull($duplicateTwo->fresh()->reference_fingerprint);
        $safe = $safe->fresh();
        $this->assertNotNull($safe->reference_fingerprint);
        $this->assertSame(hash('sha256', 'legacy-safe-proof'), $safe->evidence_sha256);
        $this->assertNotNull($safe->request_fingerprint);
        $this->assertDatabaseHas('finance_payment_migration_conflicts', [
            'conflict_type' => 'duplicate_reference',
        ]);

        $thirdAccount = $this->payable('100.00');
        $this->assertValidationError('reference', fn () => $this->service()->applyPayment(
            'cxp',
            $thirdAccount->id,
            $this->payment(['reference' => 'LEGACY-DUP'])
        ));
    }

    public function test_historical_receipt_reference_is_checked_when_payment_reference_is_empty(): void
    {
        $legacyAccount = $this->payable('100.00');
        $legacyPayment = $this->historicalPayment($legacyAccount, 'supplier_payment', 'LEGACY-RECEIPT');
        $legacyPayment->forceFill([
            'payment_reference' => '',
            'reference_fingerprint' => null,
        ])->save();

        $newAccount = $this->payable('100.00');
        $this->assertValidationError('reference', fn () => $this->service()->applyPayment(
            'cxp',
            $newAccount->id,
            $this->payment(['reference' => 'legacy-receipt'])
        ));
    }

    public function test_failed_orphan_cleanup_is_reported_without_masking_the_payment_result(): void
    {
        $path = 'finance/payments/orphan.pdf';
        $documentService = Mockery::mock(DomainFinanceDocumentService::class);
        $documentService->shouldReceive('store')->once()->andReturn($path);

        $payment = new Payment;
        $payment->payment_evidence_path = 'finance/payments/original.pdf';
        $financeService = Mockery::mock(DomainFinanceService::class);
        $financeService->shouldReceive('applyPayment')->once()->andReturn($payment);

        $disk = Mockery::mock();
        $disk->shouldReceive('delete')->once()->with($path)->andReturnFalse();
        $disk->shouldReceive('exists')->once()->with($path)->andReturnTrue();
        Storage::shouldReceive('disk')->once()->with('private')->andReturn($disk);
        Log::shouldReceive('warning')
            ->once()
            ->with('No fue posible eliminar una evidencia financiera huerfana.', Mockery::on(
                fn (array $context): bool => $context['path'] === $path
                    && $context['reason'] === 'idempotent_replay'
            ));

        $result = (new DomainPaymentRegistrationService($financeService, $documentService))->register(
            'cxp',
            10,
            UploadedFile::fake()->createWithContent('proof.pdf', 'proof'),
            'Proveedor',
            $this->payment(),
        );

        $this->assertSame($payment, $result);
    }

    public function test_an_external_reference_cannot_be_registered_twice_for_the_same_direction(): void
    {
        $firstAccount = $this->payable('100.00');
        $secondAccount = $this->payable('100.00');

        $this->service()->applyPayment('cxp', $firstAccount->id, $this->payment([
            'reference' => ' Wire-ABC-9 ',
            'idempotency_key' => 'wire-attempt-1',
        ]));

        $this->assertValidationError('reference', fn () => $this->service()->applyPayment(
            'cxp',
            $secondAccount->id,
            $this->payment([
                'reference' => 'wire-abc-9',
                'idempotency_key' => 'wire-attempt-2',
            ])
        ));
    }

    public function test_usd_conversion_uses_decimal_half_up_rounding_without_float_arithmetic(): void
    {
        $account = $this->receivable('500.00');

        $payment = $this->service()->applyPayment('cxc', $account->id, $this->payment([
            'amount' => '10.01',
            'currency' => 'USD',
            'exchange_rate' => '36.123456',
            'reference' => 'USD-EXACT-1',
        ]));

        $this->assertSame('10.01', $payment->amount_original);
        $this->assertSame('36.123456', $payment->exchange_rate);
        $this->assertSame('361.60', $payment->amount);
        $this->assertSame('361.60', $account->fresh()->paid_amount);
    }

    public function test_invalid_account_type_currency_and_exchange_rate_are_rejected(): void
    {
        $account = $this->receivable('100.00');

        $this->assertValidationError('type', fn () => $this->service()->applyPayment('other', $account->id, $this->payment()));
        $this->assertValidationError('currency', fn () => $this->service()->applyPayment(
            'cxc',
            $account->id,
            $this->payment(['currency' => 'EUR'])
        ));
        $this->assertValidationError('exchange_rate', fn () => $this->service()->applyPayment(
            'cxc',
            $account->id,
            $this->payment(['currency' => 'USD', 'exchange_rate' => '0'])
        ));
        $this->assertValidationError('exchange_rate', fn () => $this->service()->applyPayment(
            'cxc',
            $account->id,
            $this->payment(['currency' => 'USD', 'exchange_rate' => '36.1234567'])
        ));
        $this->assertValidationError('amount', fn () => $this->service()->applyPayment(
            'cxc',
            $account->id,
            $this->payment(['amount' => '10.001'])
        ));
    }

    public function test_account_is_selected_for_update_before_its_balance_is_changed(): void
    {
        $account = $this->payable('100.00');
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });

        $this->service()->applyPayment('cxp', $account->id, $this->payment());

        $this->assertTrue(collect($queries)->contains(
            fn (string $sql): bool => str_contains($sql, 'account_payables') && str_contains($sql, 'for update')
        ), 'La cuenta debe leerse con SELECT ... FOR UPDATE dentro de la transaccion.');
    }

    public function test_payment_codes_use_unique_ulids(): void
    {
        $account = $this->receivable('100.00');
        $first = $this->service()->applyPayment('cxc', $account->id, $this->payment([
            'reference' => 'ULID-1',
            'idempotency_key' => 'ulid-attempt-1',
        ]));
        $second = $this->service()->applyPayment('cxc', $account->id, $this->payment([
            'reference' => 'ULID-2',
            'idempotency_key' => 'ulid-attempt-2',
        ]));

        $this->assertMatchesRegularExpression('/^PAY-[0-9A-HJKMNP-TV-Z]{26}$/', $first->payment_code);
        $this->assertNotSame($first->payment_code, $second->payment_code);
    }

    private function service(): FinanceService
    {
        return app(FinanceService::class);
    }

    private function payable(string $total): AccountPayable
    {
        return AccountPayable::create($this->accountData($total));
    }

    private function receivable(string $total): AccountReceivable
    {
        return AccountReceivable::create($this->accountData($total));
    }

    private function accountData(string $total): array
    {
        $entity = Entity::create([
            'name' => 'Entidad financiera '.uniqid(),
            'type' => 'socio',
        ]);

        return [
            'entity_id' => $entity->id,
            'concept' => 'Prueba de integridad',
            'total_amount' => $total,
            'paid_amount' => '0.00',
            'due_date' => now()->addMonth()->toDateString(),
            'status' => 'pendiente',
        ];
    }

    private function payment(array $overrides = []): array
    {
        return array_replace([
            'amount' => '10.00',
            'currency' => 'NIO',
            'exchange_rate' => '1',
            'date' => now()->toDateString(),
            'method' => 'Transferencia',
            'reference' => 'REF-'.uniqid(),
            'evidence_path' => 'payments/evidence.pdf',
            'idempotency_key' => 'attempt-'.uniqid(),
        ], $overrides);
    }

    private function historicalPayment(AccountPayable|AccountReceivable $account, string $paymentType, string $reference): Payment
    {
        return Payment::create([
            'payment_code' => 'PAY-HIST-'.uniqid(),
            'payment_type' => $paymentType,
            'payable_type' => $account::class,
            'payable_id' => $account->id,
            'accounts_receivable_id' => $account instanceof AccountReceivable ? $account->id : null,
            'accounts_payable_id' => $account instanceof AccountPayable ? $account->id : null,
            'amount' => '10.00',
            'amount_original' => '10.00',
            'currency' => 'NIO',
            'exchange_rate' => '1.000000',
            'payment_date' => '2026-08-01',
            'payment_method' => 'Transferencia',
            'payment_reference' => $reference,
            'receipt_reference' => $reference,
            'payment_evidence_path' => 'missing/'.uniqid().'.pdf',
            'reference_fingerprint' => null,
        ]);
    }

    private function assertValidationError(string $field, callable $callback): void
    {
        try {
            $callback();
            $this->fail("Se esperaba un error de validacion para {$field}.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }
}
