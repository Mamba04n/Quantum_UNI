<?php

namespace App\Domain\Finanzas\Services;

use App\Domain\Finanzas\ValueObjects\DecimalMoney;
use App\Domain\Finanzas\ValueObjects\PaymentRequestFingerprint;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\DocumentSequenceService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class FinanceService
{
    public function createPayable(array $data): AccountPayable
    {
        return DB::transaction(function () use ($data) {
            if (empty($data['entity_id']) && empty($data['supplier_id'])) {
                throw ValidationException::withMessages(['entity_id' => 'Debes seleccionar proveedor o acreedor.']);
            }
            if ((float) ($data['total_amount'] ?? $data['amount'] ?? 0) <= 0) {
                throw ValidationException::withMessages(['total_amount' => 'El monto debe ser mayor que cero.']);
            }
            if (empty($data['due_date'])) {
                throw ValidationException::withMessages(['due_date' => 'La fecha de vencimiento es obligatoria.']);
            }
            if (empty($data['support_document_path'])) {
                throw ValidationException::withMessages(['support_document_path' => 'Debes adjuntar soporte de CXP.']);
            }
            if (! empty($data['invoice_id']) && AccountPayable::where('invoice_id', $data['invoice_id'])->exists()) {
                throw ValidationException::withMessages(['invoice_id' => 'Ya existe una CXP para esta factura proveedor.']);
            }

            $payableCode = $data['payable_code'] ?? app(DocumentSequenceService::class)->formattedNext('account_payable', 'CXP-', 6);
            if (AccountPayable::where('payable_code', $payableCode)->exists()) {
                throw ValidationException::withMessages(['payable_code' => 'El codigo interno de CXP ya existe.']);
            }

            $payable = AccountPayable::create([
                'payable_code' => $payableCode,
                'entity_id' => $data['entity_id'] ?? $data['supplier_id'],
                'supplier_id' => $data['supplier_id'] ?? $data['entity_id'],
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'order_status_id' => $data['order_status_id'] ?? null,
                'invoice_id' => $data['invoice_id'] ?? null,
                'invoice_number' => $data['invoice_number'] ?? null,
                'concept' => $data['concept'] ?? $data['description'] ?? 'Cuenta por pagar',
                'classification' => $data['classification'] ?? null,
                'condition' => $data['condition'] ?? null,
                'invoice_date' => $data['invoice_date'] ?? null,
                'total_amount' => $data['total_amount'] ?? $data['amount'],
                'amount_usd' => $data['amount_usd'] ?? null,
                'exchange_rate' => $data['exchange_rate'] ?? null,
                'paid_amount' => 0,
                'currency' => $data['currency'] ?? 'NIO',
                'due_date' => $data['due_date'],
                'support_document_path' => $data['support_document_path'],
                'created_by' => $data['created_by'] ?? auth()->id(),
                'status' => 'pendiente',
            ]);

            ActivityLog::log('finanzas', 'cxp.created', "Cuenta por pagar creada para {$payable->entity?->name}.", ['account_payable_id' => $payable->id]);

            return $payable;
        });
    }

    public function createReceivable(array $data): AccountReceivable
    {
        return DB::transaction(function () use ($data) {
            if (empty($data['entity_id']) && empty($data['customer_id'])) {
                throw ValidationException::withMessages(['entity_id' => 'Debes seleccionar cliente.']);
            }
            if ((float) ($data['total_amount'] ?? $data['amount'] ?? 0) <= 0) {
                throw ValidationException::withMessages(['total_amount' => 'El monto debe ser mayor que cero.']);
            }
            if (empty($data['due_date'])) {
                throw ValidationException::withMessages(['due_date' => 'La fecha de vencimiento es obligatoria.']);
            }
            if (empty($data['support_document_path'])) {
                throw ValidationException::withMessages(['support_document_path' => 'Debes adjuntar factura o documento base de CXC.']);
            }
            if (! empty($data['invoice_id']) && AccountReceivable::where('invoice_id', $data['invoice_id'])->exists()) {
                throw ValidationException::withMessages(['invoice_id' => 'Ya existe una CXC para esta factura cliente.']);
            }

            $receivableCode = $data['receivable_code'] ?? app(DocumentSequenceService::class)->formattedNext('account_receivable', 'CXC-', 6);
            if (AccountReceivable::where('receivable_code', $receivableCode)->exists()) {
                throw ValidationException::withMessages(['receivable_code' => 'El codigo interno de CXC ya existe.']);
            }

            $receivable = AccountReceivable::create([
                'receivable_code' => $receivableCode,
                'entity_id' => $data['entity_id'] ?? $data['customer_id'],
                'customer_id' => $data['customer_id'] ?? $data['entity_id'],
                'sales_order_id' => $data['sales_order_id'] ?? null,
                'client_offer_id' => $data['client_offer_id'] ?? null,
                'invoice_id' => $data['invoice_id'] ?? null,
                'order_tracker_id' => $data['order_tracker_id'] ?? null,
                'order_status_id' => $data['order_status_id'] ?? null,
                'invoice_number' => $data['invoice_number'] ?? null,
                'concept' => $data['concept'] ?? $data['description'] ?? 'Cuenta por cobrar',
                'classification' => $data['classification'] ?? null,
                'condition' => $data['condition'] ?? null,
                'invoice_date' => $data['invoice_date'] ?? null,
                'total_amount' => $data['total_amount'] ?? $data['amount'],
                'amount_usd' => $data['amount_usd'] ?? null,
                'exchange_rate' => $data['exchange_rate'] ?? null,
                'paid_amount' => 0,
                'currency' => $data['currency'] ?? 'NIO',
                'due_date' => $data['due_date'],
                'support_document_path' => $data['support_document_path'],
                'created_by' => $data['created_by'] ?? auth()->id(),
                'status' => 'pendiente',
            ]);

            ActivityLog::log('finanzas', 'cxc.created', "Cuenta por cobrar creada para {$receivable->entity?->name}.", ['account_receivable_id' => $receivable->id]);

            return $receivable;
        });
    }

    public function updatePayable(AccountPayable $payable, array $data, ?int $expectedLockVersion = null, ?string $justification = null): AccountPayable
    {
        return DB::transaction(function () use ($payable, $data, $expectedLockVersion, $justification): AccountPayable {
            $payable = AccountPayable::query()
                ->whereKey($payable->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($expectedLockVersion !== null && (int) $payable->lock_version !== $expectedLockVersion) {
                throw ValidationException::withMessages([
                    'payable_form' => 'Esta CXP fue modificada por otro usuario. Vuelve a abrirla antes de guardar.',
                ]);
            }

            $totalAmount = (float) ($data['total_amount'] ?? 0);
            if ($totalAmount <= 0) {
                throw ValidationException::withMessages(['total_amount' => 'El monto debe ser mayor que cero.']);
            }
            if ($totalAmount < (float) $payable->paid_amount) {
                throw ValidationException::withMessages([
                    'total_amount' => 'El importe total no puede ser menor que lo ya pagado.',
                ]);
            }
            if (empty($data['due_date'])) {
                throw ValidationException::withMessages(['due_date' => 'La fecha de vencimiento es obligatoria.']);
            }

            $supportPath = $data['support_document_path'] ?? $payable->support_document_path;
            if (blank($supportPath)) {
                throw ValidationException::withMessages([
                    'support_document' => 'La CXP debe conservar un documento de soporte.',
                ]);
            }

            $effectiveRate = (float) ($payable->exchange_rate ?? 0);
            if ($effectiveRate <= 0 && (float) $payable->amount_usd > 0 && (float) $payable->total_amount > 0) {
                $effectiveRate = (float) $payable->total_amount / (float) $payable->amount_usd;
            }

            $amountUsd = isset($data['amount_usd']) && (float) $data['amount_usd'] > 0
                ? (float) $data['amount_usd']
                : ($effectiveRate > 0 ? round($totalAmount / $effectiveRate, 2) : null);

            $status = $totalAmount <= (float) $payable->paid_amount
                ? 'pagado'
                : ((float) $payable->paid_amount > 0 ? 'parcial' : 'pendiente');

            $before = $payable->only([
                'entity_id', 'supplier_id', 'invoice_number', 'concept', 'classification', 'condition',
                'invoice_date', 'total_amount', 'amount_usd', 'due_date', 'status',
            ]);

            $updates = [
                'invoice_number' => $data['invoice_number'] ?? null,
                'concept' => $data['concept'] ?? $payable->concept,
                'classification' => $data['classification'] ?? null,
                'condition' => $data['condition'] ?? null,
                'invoice_date' => $data['invoice_date'] ?? null,
                'total_amount' => round($totalAmount, 2),
                'amount_usd' => $amountUsd,
                'exchange_rate' => $effectiveRate > 0 ? $effectiveRate : null,
                'currency' => $data['currency'] ?? $payable->currency ?? 'NIO',
                'due_date' => $data['due_date'],
                'support_document_path' => $supportPath,
                'status' => $status,
                'paid_at' => $status === 'pagado' ? ($payable->paid_at ?: now()) : null,
                'lock_version' => (int) $payable->lock_version + 1,
            ];

            // El acreedor y el origen de una CXP vinculada a una OC pertenecen a la orden;
            // solo las cuentas manuales permiten cambiar de acreedor.
            if (! $payable->purchase_order_id) {
                $entityId = $data['entity_id'] ?? $data['supplier_id'] ?? null;
                if (! $entityId) {
                    throw ValidationException::withMessages(['entity_id' => 'Debes seleccionar proveedor o acreedor.']);
                }
                $updates['entity_id'] = $entityId;
                $updates['supplier_id'] = $entityId;
            }

            $payable->forceFill($updates)->save();

            $logDesc = "Cuenta por pagar {$payable->payable_code} actualizada.";
            if (filled($justification)) {
                $logDesc .= " Motivo: {$justification}";
            }

            ActivityLog::log('finanzas', 'cxp.updated', $logDesc, [
                'account_payable_id' => $payable->id,
                'justification' => $justification,
                'authorized_by' => auth()->id(),
                'before' => $before,
                'after' => $payable->only(array_keys($before)),
            ]);

            return $payable->fresh();
        });
    }

    public function updateReceivable(AccountReceivable $receivable, array $data, ?int $expectedLockVersion = null, ?string $justification = null): AccountReceivable
    {
        return DB::transaction(function () use ($receivable, $data, $expectedLockVersion, $justification): AccountReceivable {
            $receivable = AccountReceivable::query()
                ->whereKey($receivable->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($expectedLockVersion !== null && (int) $receivable->lock_version !== $expectedLockVersion) {
                throw ValidationException::withMessages([
                    'receivable_form' => 'Esta CXC fue modificada por otro usuario. Vuelve a abrirla antes de guardar.',
                ]);
            }

            $totalAmount = (float) ($data['total_amount'] ?? 0);
            if ($totalAmount <= 0) {
                throw ValidationException::withMessages(['total_amount' => 'El monto debe ser mayor que cero.']);
            }
            if ($totalAmount < (float) $receivable->paid_amount) {
                throw ValidationException::withMessages([
                    'total_amount' => 'El importe total no puede ser menor que lo ya abonado.',
                ]);
            }
            if (empty($data['due_date'])) {
                throw ValidationException::withMessages(['due_date' => 'La fecha de vencimiento es obligatoria.']);
            }

            $supportPath = $data['support_document_path'] ?? $receivable->support_document_path;
            if (blank($supportPath)) {
                throw ValidationException::withMessages([
                    'support_document' => 'La CXC debe conservar un documento de soporte.',
                ]);
            }

            $effectiveRate = (float) ($receivable->exchange_rate ?? 0);
            if ($effectiveRate <= 0 && (float) $receivable->amount_usd > 0 && (float) $receivable->total_amount > 0) {
                $effectiveRate = (float) $receivable->total_amount / (float) $receivable->amount_usd;
            }

            $status = $totalAmount <= (float) $receivable->paid_amount
                ? 'pagado'
                : ((float) $receivable->paid_amount > 0 ? 'parcial' : 'pendiente');
            $before = $receivable->only([
                'entity_id', 'invoice_number', 'concept', 'classification', 'condition',
                'invoice_date', 'total_amount', 'due_date', 'status',
            ]);

            $updates = [
                'invoice_number' => $data['invoice_number'] ?? null,
                'concept' => $data['concept'] ?? $receivable->concept,
                'classification' => $data['classification'] ?? null,
                'condition' => $data['condition'] ?? null,
                'invoice_date' => $data['invoice_date'] ?? null,
                'total_amount' => round($totalAmount, 2),
                'amount_usd' => $effectiveRate > 0 ? round($totalAmount / $effectiveRate, 2) : null,
                'exchange_rate' => $effectiveRate > 0 ? $effectiveRate : null,
                'currency' => 'NIO',
                'due_date' => $data['due_date'],
                'support_document_path' => $supportPath,
                'status' => $status,
                'collected_at' => $status === 'pagado' ? ($receivable->collected_at ?: now()) : null,
                'lock_version' => (int) $receivable->lock_version + 1,
            ];

            // El cliente y el origen de una CXC automatica pertenecen a la orden;
            // solo las cuentas manuales permiten cambiar de cliente.
            if (! $receivable->sales_order_id) {
                $entityId = $data['entity_id'] ?? $data['customer_id'] ?? null;
                if (! $entityId) {
                    throw ValidationException::withMessages(['entity_id' => 'Debes seleccionar cliente.']);
                }
                $updates['entity_id'] = $entityId;
                $updates['customer_id'] = $entityId;
            }

            $receivable->forceFill($updates)->save();

            $logDesc = "Cuenta por cobrar {$receivable->receivable_code} actualizada.";
            if (filled($justification)) {
                $logDesc .= " Motivo: {$justification}";
            }

            ActivityLog::log('finanzas', 'cxc.updated', $logDesc, [
                'account_receivable_id' => $receivable->id,
                'justification' => $justification,
                'authorized_by' => auth()->id(),
                'before' => $before,
                'after' => $receivable->only(array_keys($before)),
            ]);

            return $receivable->fresh();
        });
    }

    public function createInvoice(array $data): Invoice
    {
        return DB::transaction(function () use ($data) {
            if (empty($data['invoice_number'])) {
                throw ValidationException::withMessages(['invoice_number' => 'El numero de factura es obligatorio.']);
            }
            if ((float) ($data['total'] ?? $data['amount_nio'] ?? 0) <= 0) {
                throw ValidationException::withMessages(['total' => 'El total de la factura debe ser mayor que cero.']);
            }
            if (empty($data['invoice_file_path'])) {
                throw ValidationException::withMessages(['invoice_file_path' => 'Debes adjuntar PDF o documento de factura.']);
            }

            $invoice = Invoice::create([
                'invoice_number' => $data['invoice_number'],
                'invoice_type' => $data['invoice_type'] ?? 'customer_invoice',
                'company_issuer' => $data['company_issuer'] ?? (!empty($data['sales_order_id']) ? \App\Models\SaleOrder::find($data['sales_order_id'])?->company_issuer : null) ?? 'quantum',
                'entity_id' => $data['entity_id'] ?? $data['customer_id'] ?? $data['supplier_id'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
                'supplier_id' => $data['supplier_id'] ?? null,
                'sales_order_id' => $data['sales_order_id'] ?? null,
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'order_status_id' => $data['order_status_id'] ?? null,
                'date' => $data['date'] ?? $data['issue_date'] ?? now()->toDateString(),
                'cotizacion_ref' => $data['cotizacion_ref'] ?? null,
                'description' => $data['description'] ?? 'Factura '.$data['invoice_number'],
                'subtotal' => $data['subtotal'] ?? $data['amount_nio'] ?? $data['total'],
                'tax_amount' => $data['tax_amount'] ?? 0,
                'discount_amount' => $data['discount_amount'] ?? 0,
                'total' => $data['total'] ?? $data['amount_nio'],
                'amount_nio' => $data['amount_nio'] ?? $data['total'],
                'retention_amount' => $data['retention_amount'] ?? 0,
                'amount_usd' => $data['amount_usd'] ?? null,
                'currency' => $data['currency'] ?? 'NIO',
                'due_date' => $data['due_date'] ?? null,
                'status' => $data['status'] ?? 'Pendiente',
                'classification' => $data['classification'] ?? null,
                'condition' => $data['condition'] ?? 'Contado',
                'invoice_file_path' => $data['invoice_file_path'],
                'generated_by' => $data['generated_by'] ?? null,
                'uploaded_by' => $data['uploaded_by'] ?? auth()->id(),
            ]);

            ActivityLog::log('finanzas', 'invoice.created', "Factura {$invoice->invoice_number} registrada.", ['invoice_id' => $invoice->id]);

            return $invoice;
        });
    }

    public function applyPayment(string $type, int $accountId, array $paymentData): Payment
    {
        if (! in_array($type, ['cxc', 'cxp'], true)) {
            throw ValidationException::withMessages(['type' => 'El tipo de cuenta debe ser CXC o CXP.']);
        }

        $idempotencyKey = $this->normalizeIdempotencyKey($paymentData['idempotency_key'] ?? null);

        try {
            return DB::transaction(function () use ($type, $accountId, $paymentData, $idempotencyKey) {
                if ($idempotencyKey !== null) {
                    $existingPayment = Payment::where('idempotency_key', $idempotencyKey)->first();

                    if ($existingPayment) {
                        return $this->validateIdempotentRetry($existingPayment, $type, $accountId, $paymentData);
                    }
                }

                $account = $type === 'cxc'
                    ? AccountReceivable::query()->lockForUpdate()->findOrFail($accountId)
                    : AccountPayable::query()->lockForUpdate()->findOrFail($accountId);

                if ($idempotencyKey !== null) {
                    $existingPayment = Payment::where('idempotency_key', $idempotencyKey)->first();

                    if ($existingPayment) {
                        return $this->validateIdempotentRetry($existingPayment, $type, $accountId, $paymentData);
                    }
                }

                if (empty($paymentData['evidence_path'])) {
                    throw ValidationException::withMessages(['payment_evidence_path' => 'Debes adjuntar evidencia del pago.']);
                }
                if (empty($paymentData['method'])) {
                    throw ValidationException::withMessages(['payment_method' => 'El metodo de pago es obligatorio.']);
                }

                $reference = trim((string) ($paymentData['reference'] ?? ''));
                if ($reference === '' || mb_strlen($reference) > 120) {
                    throw ValidationException::withMessages(['reference' => 'La referencia de pago es obligatoria y admite hasta 120 caracteres.']);
                }

                $paymentCurrency = strtoupper(trim((string) ($paymentData['currency'] ?? 'NIO')));
                if (! in_array($paymentCurrency, ['NIO', 'USD'], true)) {
                    throw ValidationException::withMessages(['currency' => 'La moneda del pago debe ser C$ o USD.']);
                }

                try {
                    $originalAmount = DecimalMoney::amount($paymentData['amount'] ?? '');
                } catch (InvalidArgumentException $exception) {
                    throw ValidationException::withMessages(['amount' => $exception->getMessage()]);
                }

                try {
                    $exchangeRate = $paymentCurrency === 'USD'
                        ? DecimalMoney::exchangeRate($paymentData['exchange_rate'] ?? '')
                        : DecimalMoney::exchangeRate(1);
                } catch (InvalidArgumentException $exception) {
                    throw ValidationException::withMessages(['exchange_rate' => $exception->getMessage()]);
                }

                try {
                    $amountToPay = $paymentCurrency === 'USD'
                        ? DecimalMoney::convertToBase($originalAmount, $exchangeRate)
                        : $originalAmount;
                    $totalAmount = DecimalMoney::amount($account->total_amount);
                    $paidAmount = DecimalMoney::amount($account->paid_amount);
                    $pendingAmount = DecimalMoney::subtract($totalAmount, $paidAmount);
                } catch (InvalidArgumentException $exception) {
                    throw ValidationException::withMessages(['amount' => $exception->getMessage()]);
                }

                if (DecimalMoney::compare($originalAmount, '0.00') <= 0) {
                    throw ValidationException::withMessages(['amount' => 'El monto debe ser mayor que cero.']);
                }
                if ($paymentCurrency === 'USD' && $exchangeRate === '0.000000') {
                    throw ValidationException::withMessages(['exchange_rate' => 'Ingresa una tasa de cambio valida para el pago en USD.']);
                }
                if (DecimalMoney::compare($amountToPay, '0.00') <= 0) {
                    throw ValidationException::withMessages(['amount' => 'El monto convertido debe ser al menos 0.01 NIO.']);
                }
                if (DecimalMoney::compare($amountToPay, $pendingAmount) > 0) {
                    throw ValidationException::withMessages(['amount' => 'El monto excede el saldo pendiente.']);
                }

                $paymentType = $type === 'cxc' ? 'customer_payment' : 'supplier_payment';
                $referenceFingerprint = PaymentRequestFingerprint::reference($paymentType, $reference);
                $requestFingerprint = $this->requestFingerprint($type, $accountId, $paymentData);

                if (Payment::where('payment_type', $paymentType)
                    ->where(function ($query) use ($referenceFingerprint, $reference): void {
                        $query->where('reference_fingerprint', $referenceFingerprint)
                            ->orWhereRaw(
                                "LOWER(TRIM(COALESCE(NULLIF(TRIM(payment_reference), ''), receipt_reference))) = ?",
                                [mb_strtolower($reference)]
                            );
                    })->exists()) {
                    throw ValidationException::withMessages(['reference' => 'Esta referencia de pago ya fue registrada.']);
                }

                $payment = Payment::create([
                    'payment_code' => 'PAY-'.Str::ulid(),
                    'idempotency_key' => $idempotencyKey,
                    'request_fingerprint' => $requestFingerprint,
                    'payment_type' => $paymentType,
                    'payable_type' => $account::class,
                    'payable_id' => $account->id,
                    'accounts_receivable_id' => $type === 'cxc' ? $account->id : null,
                    'accounts_payable_id' => $type === 'cxp' ? $account->id : null,
                    'invoice_id' => $account->invoice_id ?? null,
                    'amount' => $amountToPay,
                    'amount_original' => $originalAmount,
                    'currency' => $paymentCurrency,
                    'exchange_rate' => $exchangeRate,
                    'payment_date' => $paymentData['date'] ?? Carbon::now(),
                    'payment_method' => $paymentData['method'],
                    'payment_reference' => $reference,
                    'reference_fingerprint' => $referenceFingerprint,
                    'receipt_reference' => $reference,
                    'payment_evidence_path' => $paymentData['evidence_path'],
                    'evidence_sha256' => $paymentData['evidence_sha256'] ?? PaymentRequestFingerprint::legacyEvidenceHash($paymentData['evidence_path']),
                    'registered_by' => $paymentData['registered_by'] ?? auth()->id(),
                    'notes' => $paymentData['notes'] ?? null,
                ]);

                $newPaidAmount = DecimalMoney::add($paidAmount, $amountToPay);
                $account->paid_amount = $newPaidAmount;

                if (DecimalMoney::compare($newPaidAmount, $totalAmount) >= 0) {
                    $account->status = 'pagado';
                    if ($account instanceof AccountPayable) {
                        $account->paid_at = now();
                    }
                    if ($account instanceof AccountReceivable) {
                        $account->collected_at = now();
                    }
                } else {
                    $account->status = 'parcial';
                }

                $account->save();

                ActivityLog::log(
                    'finanzas',
                    $type === 'cxc' ? 'cxc.payment_registered' : 'cxp.payment_registered',
                    "Pago registrado con evidencia {$payment->payment_reference}.",
                    ['payment_id' => $payment->id]
                );

                return $payment;
            }, 3);
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            if ($idempotencyKey !== null && ($existingPayment = Payment::where('idempotency_key', $idempotencyKey)->first())) {
                return $this->validateIdempotentRetry($existingPayment, $type, $accountId, $paymentData);
            }

            throw ValidationException::withMessages(['reference' => 'Esta referencia de pago ya fue registrada.']);
        }
    }

    private function normalizeIdempotencyKey(mixed $key): ?string
    {
        if ($key === null || $key === '') {
            return null;
        }

        if (! is_string($key) || mb_strlen($key) > 100 || ! preg_match('/^[A-Za-z0-9._:-]+$/', $key)) {
            throw ValidationException::withMessages(['idempotency_key' => 'La clave de idempotencia no es valida.']);
        }

        return $key;
    }

    private function validateIdempotentRetry(Payment $payment, string $type, int $accountId, array $paymentData): Payment
    {
        try {
            $storedFingerprint = $payment->request_fingerprint ?: PaymentRequestFingerprint::fromPayment($payment);
            $requestFingerprint = PaymentRequestFingerprint::fromInput($type, $accountId, $paymentData);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['idempotency_key' => $exception->getMessage()]);
        }

        if (! hash_equals($storedFingerprint, $requestFingerprint)) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'La clave de idempotencia ya fue utilizada con datos diferentes.',
            ]);
        }

        return $payment;
    }

    private function requestFingerprint(string $type, int $accountId, array $paymentData): string
    {
        try {
            return PaymentRequestFingerprint::fromInput($type, $accountId, $paymentData);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['payment' => $exception->getMessage()]);
        }
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23000', '23505'], true)
            || (int) ($exception->errorInfo[1] ?? 0) === 1062;
    }
}
