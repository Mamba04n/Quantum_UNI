<?php

namespace App\Domain\Finanzas\PaymentLinks;

use App\Domain\Finanzas\Services\FinanceService;
use App\Domain\Finanzas\ValueObjects\DecimalMoney as Money;
use App\Models\AccountReceivable;
use App\Models\ActivityLog;
use App\Models\Entity;
use App\Models\PaymentLink;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\User;
use App\Services\DocumentSequenceService;
use App\Services\ExchangeRateService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PaymentLinkService
{
    public function __construct(private FinanceService $finance, private PaymentGateway $gateway) {}

    public function create(User $user, array $data): PaymentLink
    {
        abort_unless($user->can('payment_links.create'), 403);
        $data = Validator::make($data, [
            'issuance_key' => 'required|uuid',
            'customer_id' => 'required|integer|exists:entities,id', 'sale_order_id' => 'nullable|integer|exists:sale_orders,id',
            'account_receivable_id' => 'nullable|integer|exists:account_receivables,id',
            'description' => 'required|string|max:255', 'internal_note' => 'nullable|string|max:2000',
            'expires_at' => 'required|date|after:now|before:'.now()->addDays(31)->toDateTimeString(),
            'items' => 'required|array|min:1|max:50', 'items.*.product_id' => 'nullable|integer|exists:products,id',
            'items.*.description' => 'nullable|string|max:255', 'items.*.quantity' => 'required|integer|min:1|max:10000',
            'items.*.unit_price' => ['required', 'regex:/^\d{1,8}(\.\d{1,2})?$/', 'numeric', 'gt:0'],
        ])->validate();
        $fingerprint = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
        if ($existing = PaymentLink::where('issuance_key', $data['issuance_key'])->first()) {
            if ($existing->created_by !== $user->id || $existing->issuance_fingerprint !== $fingerprint) {
                throw ValidationException::withMessages(['link' => 'La solicitud de emisión cambió. Inicia un nuevo cobro.']);
            }

            return $existing;
        }
        $rate = Money::exchangeRate(number_format((float) app(ExchangeRateService::class)->rateToNio('USD')['rate'], 6, '.', ''));
        if ($rate === '0.000000') {
            throw ValidationException::withMessages(['exchange_rate' => 'No hay una tasa USD vigente.']);
        }
        try {
            return DB::transaction(function () use ($user, $data, $rate, $fingerprint) {
                $customer = Entity::whereKey($data['customer_id'])->where('type', 'cliente')->firstOrFail();
                $order = ! empty($data['sale_order_id']) ? SaleOrder::lockForUpdate()->findOrFail($data['sale_order_id']) : null;
                if ($order && ($order->customer_id != $customer->id || in_array($order->status, ['draft', 'borrador', 'cancelado', 'cancelled'], true))) {
                    throw ValidationException::withMessages(['sale_order_id' => 'La orden debe pertenecer al cliente y estar vigente.']);
                }
                $total = '0.00';
                $rows = [];
                $priceOverrides = [];
                foreach ($data['items'] as $line) {
                    $product = ! empty($line['product_id']) ? Product::whereKey($line['product_id'])->where('active', true)->firstOrFail() : null;
                    $price = Money::amount($line['unit_price']);
                    $suggested = $product ? Money::convertFromBase($product->sales_price ?? '0', $rate) : null;
                    $approvedOrderPrice = $product && $order ? $order->items()->where('product_id', $product->id)->get()->contains(fn ($i) => $price === ($order->currency === 'USD' ? $i->unit_price : Money::convertFromBase($i->unit_price, $rate))) : false;
                    if ((! $product || ($price !== $suggested && ! $approvedOrderPrice)) && ! $user->can('payment_links.override_price')) {
                        abort(403);
                    }
                    if (! $product || ($price !== $suggested && ! $approvedOrderPrice)) {
                        $priceOverrides[] = ['product_id' => $product?->id, 'suggested_usd' => $suggested, 'requested_usd' => $price, 'authorized_by' => $user->id];
                    }
                    $description = $product ? ($product->description ?: $product->name) : trim($line['description'] ?? '');
                    if ($description === '') {
                        throw ValidationException::withMessages(['items' => 'Escribe la descripción del concepto.']);
                    }
                    $lineTotal = Money::convertToBase($price, (string) $line['quantity']);
                    $total = Money::add($total, $lineTotal);
                    $rows[] = ['image_path'=>app(PaymentLinkImages::class)->snapshot($product),'product_id' => $product?->id, 'part_number' => $product?->part_number ?: $product?->sku, 'description' => mb_substr($description, 0, 255), 'quantity' => $line['quantity'], 'unit_price' => $price, 'line_total' => $lineTotal];
                }
                if (Money::compare($total, '999999999.99') > 0) {
                    throw ValidationException::withMessages(['items' => 'El total excede el límite permitido.']);
                }
                $code = app(DocumentSequenceService::class)->formattedNext('payment_link', 'PAY-', 5);
                $token = bin2hex(random_bytes(32));
                $receivable = ! empty($data['account_receivable_id']) ? AccountReceivable::lockForUpdate()->findOrFail($data['account_receivable_id']) : ($order ? AccountReceivable::where('sales_order_id', $order->id)->lockForUpdate()->first() : null);
                if ($receivable && ($receivable->entity_id != $customer->id || ($order && $receivable->sales_order_id != $order->id))) {
                    throw ValidationException::withMessages(['account_receivable_id' => 'La CxC no corresponde al cliente y la orden.']);
                }
                if ($receivable && ! $order && $receivable->sales_order_id) {
                    $order = SaleOrder::lockForUpdate()->findOrFail($receivable->sales_order_id);
                }
                if (! $receivable) {
                    $base = $order ? $this->orderBaseTotal($order, $rate) : Money::convertToBase($total, $rate);
                    $support = 'payment-links/'.$token.'.json';
                    Storage::disk('private')->put($support, json_encode(['code' => $code, 'currency' => 'USD', 'items' => $rows, 'total' => $total], JSON_THROW_ON_ERROR));
                    $receivable = $this->finance->createReceivable([
                        'entity_id' => $customer->id, 'sales_order_id' => $order?->id, 'concept' => $data['description'], 'total_amount' => $base,
                        'currency' => 'NIO', 'exchange_rate' => $rate, 'due_date' => $data['expires_at'], 'support_document_path' => $support, 'created_by' => $user->id,
                    ]);
                }
                $baseAmount = Money::convertToBase($total, $rate);
                if (Money::compare($baseAmount, Money::subtract($receivable->total_amount, $receivable->paid_amount)) > 0) {
                    throw ValidationException::withMessages(['items' => 'El cobro supera el saldo disponible de CxC.']);
                }
                $link = PaymentLink::create([
                    'public_token' => $token, 'code' => $code, 'customer_id' => $customer->id, 'sale_order_id' => $order?->id,
                    'issuance_key' => $data['issuance_key'], 'issuance_fingerprint' => $fingerprint,
                    'account_receivable_id' => $receivable->id, 'currency' => 'USD', 'exchange_rate' => $rate, 'total' => $total,
                    'description' => $data['description'], 'internal_note' => $data['internal_note'] ?? null, 'expires_at' => $data['expires_at'], 'created_by' => $user->id,
                ]);
                $link->items()->createMany($rows);
                ActivityLog::log('finanzas', 'payment_link.created', "Cobro {$code} creado.", ['payment_link_id' => $link->id, 'price_overrides' => $priceOverrides, 'created_by' => $user->id]);

                return $link;
            }, 3);
        } catch (QueryException $e) {
            $existing = PaymentLink::where('issuance_key', $data['issuance_key'])->first();
            if (! $existing || $existing->created_by !== $user->id || $existing->issuance_fingerprint !== $fingerprint) {
                throw $e;
            }

            return $existing;
        }
    }

    private function orderBaseTotal(SaleOrder $order, string $rate): string
    {
        if (! in_array($order->currency, ['USD', 'NIO'], true) || (float) $order->total_amount <= 0) {
            throw ValidationException::withMessages(['sale_order_id' => 'La orden no tiene un importe comercial válido.']);
        }

        return $order->currency === 'USD' ? Money::convertToBase($order->total_amount, $rate) : Money::amount($order->total_amount);
    }

    public function cancel(User $user, PaymentLink $link): void
    {
        abort_unless($user->can('payment_links.cancel'), 403);
        Cache::lock('paypal-capture:'.$link->id, 180)->block(3, fn () => DB::transaction(function () use ($link) {
            $link = PaymentLink::lockForUpdate()->findOrFail($link->id);
            if ($link->status !== 'pending') {
                throw ValidationException::withMessages(['link' => 'Este cobro ya no se puede cancelar.']);
            }
            $link->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            ActivityLog::log('finanzas', 'payment_link.cancelled', "Cobro {$link->code} cancelado.", ['payment_link_id' => $link->id]);
        }));
    }

    private function active(PaymentLink $link): void
    {
        if ($link->effectiveStatus() !== 'pending') {
            throw ValidationException::withMessages(['payment' => 'Este enlace ya no está disponible para pagar.']);
        }
    }

    public function checkout(PaymentLink $link): string
    {
        return Cache::lock('paypal-create:'.$link->id, 180)->block(3, function () use ($link) {
            $link->refresh();
            $this->active($link);
            if ($link->provider_order_id) {
                return $link->provider_order_id;
            }
            $order = $this->gateway->createOrder($link);
            if (empty($order['id'])) {
                throw new \RuntimeException('No se pudo iniciar PayPal.');
            }
            $link->update(['provider_order_id' => $order['id'], 'provider_status' => $order['status'] ?? 'CREATED']);

            return $order['id'];
        });
    }

    public function capture(PaymentLink $link, string $orderId): bool
    {
        return Cache::lock('paypal-capture:'.$link->id, 180)->block(3, function () use ($link, $orderId) {
            $link->refresh();
            if ($link->provider_order_id !== $orderId) {
                abort(422);
            }
            if ($link->status === 'paid') {
                return true;
            }
            $this->active($link);
            // Query first reconciles a previous capture whose response was lost.
            $order = $this->gateway->queryOrder($orderId);
            if (($order['status'] ?? '') !== 'COMPLETED') {
                $this->gateway->capture($orderId, 'capture-'.$link->code);
                // Capture responses need not repeat custom_id and other order fields.
                // Confirm against the full authenticated order, never a browser callback.
                $order = $this->gateway->queryOrder($orderId);
            }

            return $this->confirm($link, $order);
        });
    }

    public function confirm(PaymentLink $link, array $order): bool
    {
        return DB::transaction(function () use ($link, $order) {
            $link = PaymentLink::lockForUpdate()->findOrFail($link->id);
            $units = $order['purchase_units'] ?? [];
            $capture = $units[0]['payments']['captures'][0] ?? [];
            $valid = ($order['id'] ?? null) === $link->provider_order_id && ($order['status'] ?? '') === 'COMPLETED'
                && count($units) === 1 && count($units[0]['payments']['captures'] ?? []) === 1
                && ($units[0]['custom_id'] ?? null) === $link->code
                && ($capture['status'] ?? '') === 'COMPLETED' && preg_match('/^[A-Z0-9]{1,64}$/D', $capture['id'] ?? '')
                && ($capture['amount']['currency_code'] ?? '') === $link->currency
                && ($capture['amount']['value'] ?? '') === $link->total;
            if (! $valid) {
                $link->update(['review_reason' => 'provider_confirmation_mismatch']);
                ActivityLog::log('finanzas', 'payment_link.review_required', "Revisar confirmación de {$link->code}.", ['payment_link_id' => $link->id]);

                return false;
            }
            if ($link->payment_id) {
                return $link->provider_capture_id === $capture['id'];
            }
            $evidence = 'payment-links/captures/'.$capture['id'].'.json';
            $proof = json_encode(['order_id' => $order['id'], 'capture_id' => $capture['id'], 'amount' => $link->total, 'currency' => $link->currency, 'status' => 'COMPLETED'], JSON_THROW_ON_ERROR);
            Storage::disk('private')->put($evidence, $proof);
            $link->update(['status' => 'paid', 'paid_at' => $link->paid_at ?? now(), 'provider_capture_id' => $capture['id'], 'provider_status' => 'COMPLETED']);
            try {
                $payment = $this->finance->applyPayment('cxc', $link->account_receivable_id, [
                    'amount' => $link->total, 'currency' => $link->currency, 'exchange_rate' => $link->exchange_rate,
                    'method' => 'PayPal', 'reference' => 'PAYPAL-'.$capture['id'], 'date' => $link->paid_at->toDateString(),
                    'evidence_path' => $evidence, 'evidence_sha256' => hash('sha256', $proof), 'registered_by' => $link->created_by,
                    'idempotency_key' => 'paypal:'.$capture['id'],
                ]);
            } catch (ValidationException $exception) {
                // Funds received must remain visible even if CxC changed concurrently.
                $link->update(['review_reason' => 'finance_application_requires_review']);
                ActivityLog::log('finanzas', 'payment_link.finance_review', "PayPal recibido; revisar aplicación de {$link->code}.", ['payment_link_id' => $link->id]);

                return true;
            }
            $link->update(['status' => 'paid', 'paid_at' => now(), 'provider_capture_id' => $capture['id'], 'provider_status' => 'COMPLETED', 'payment_id' => $payment->id, 'review_reason' => null]);
            ActivityLog::log('finanzas', 'payment_link.paid', "Cobro {$link->code} confirmado por PayPal.", ['payment_link_id' => $link->id, 'payment_id' => $payment->id]);

            return true;
        }, 3);
    }

    public function reconcile(User $user, PaymentLink $link): bool
    {
        abort_unless($user->can('finance.payment.register'), 403);
        if (! $link->provider_order_id) {
            throw ValidationException::withMessages(['payment' => 'Este cobro todavía no tiene una orden PayPal.']);
        }

        return $this->confirm($link, $this->gateway->queryOrder($link->provider_order_id));
    }
}
