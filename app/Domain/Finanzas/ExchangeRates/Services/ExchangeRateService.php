<?php

namespace App\Domain\Finanzas\ExchangeRates\Services;

use App\Domain\Finanzas\ExchangeRates\Enums\ExchangeRateSourceType;
use App\Domain\Finanzas\ValueObjects\DecimalMoney;
use App\Models\ActivityLog;
use App\Models\ExchangeRate;
use App\Models\Currency;
use App\Models\QuotationRequest;
use App\Models\QuotationRequestItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

class ExchangeRateService
{
    public function usdToNio(bool $forceRefresh = false, ?User $staleApprovedBy = null): array
    {
        return $this->resolveRate('USD', 'NIO', $forceRefresh, $staleApprovedBy);
    }

    public function convert(
        mixed $amount,
        ?string $fromCurrency,
        string $toCurrency = 'NIO',
        ?User $staleApprovedBy = null,
        bool $forceRefresh = false,
    ): array {
        $fromCurrency = $this->currency($fromCurrency, 'from_currency');
        $toCurrency = $this->currency($toCurrency, 'to_currency');
        $amount = $this->amount($amount);

        if ($fromCurrency === $toCurrency) {
            $sourceType = ExchangeRateSourceType::SameCurrency;

            return [
                'amount' => $amount,
                'rate' => '1.000000',
                'date' => now()->toDateString(),
                'source' => $sourceType->value,
                'source_type' => $sourceType->value,
                'source_label' => $sourceType->label(),
                'is_stored' => false,
                'is_fallback' => false,
                'is_stale' => false,
                'requires_confirmation' => false,
                'approved_by' => null,
                'exchange_rate_id' => null,
                'warning' => null,
            ];
        }

        if ($fromCurrency === 'NIO') {
            $directRate = $this->resolveRate($toCurrency, 'NIO', $forceRefresh, $staleApprovedBy);
            $inverseRate = $this->rate(number_format(1 / (float) $directRate['rate'], 6, '.', ''));

            return array_merge($directRate, [
                'amount' => DecimalMoney::convertFromBase($amount, $directRate['rate']),
                'rate' => $inverseRate,
                'source' => 'inverse:'.$directRate['source'],
                'source_label' => 'Conversión inversa · '.$directRate['source_label'],
            ]);
        }

        $rate = $this->resolveRate($fromCurrency, $toCurrency, $forceRefresh, $staleApprovedBy);

        return $rate + [
            'amount' => DecimalMoney::convertToBase($amount, $rate['rate']),
        ];
    }

    public function rateToNio(?string $currency, ?User $staleApprovedBy = null, bool $forceRefresh = false): array
    {
        return $this->convert('1.00', $currency, 'NIO', $staleApprovedBy, $forceRefresh);
    }

    public function approveManualRate(
        string $baseCurrency,
        string $quoteCurrency,
        mixed $rate,
        string $rateDate,
        User $approver,
    ): ExchangeRate {
        $baseCurrency = $this->currency($baseCurrency, 'base_currency');
        $quoteCurrency = $this->currency($quoteCurrency, 'quote_currency');
        $this->assertSupportedPair($baseCurrency, $quoteCurrency);
        if ($baseCurrency === $quoteCurrency) {
            throw ValidationException::withMessages([
                'currency_pair' => 'No se registra una tasa manual entre la misma moneda.',
            ]);
        }
        $this->assertCanApprove($approver);
        $rate = $this->rate($rate);
        $rateDate = $this->date($rateDate);
        $isFixed = Currency::query()->where('code', $baseCurrency)->value('is_fixed');

        $exchangeRate = ExchangeRate::updateOrCreate(
            [
                'base_currency' => $baseCurrency,
                'quote_currency' => $quoteCurrency,
                'rate_date' => $rateDate,
            ],
            [
                'rate' => $rate,
                'expires_at' => $isFixed ? null : CarbonImmutable::parse($rateDate)->addDays($this->maxAgeDays())->toDateString(),
                'source' => 'manual-approved',
                'source_type' => ExchangeRateSourceType::ManualApproved,
                'is_fallback' => false,
                'fetched_at' => null,
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'metadata' => ['approved_manually' => true],
            ],
        );

        Cache::forget($this->cacheKey($baseCurrency, $quoteCurrency));
        $this->logApproval($approver, 'exchange_rate.manual_approved', $exchangeRate);

        return $exchangeRate->fresh();
    }

    public function refreshAutomaticRate(string $baseCurrency): ExchangeRate
    {
        $baseCurrency = $this->currency($baseCurrency, 'base_currency');
        if ($baseCurrency === 'NIO') {
            throw ValidationException::withMessages(['base_currency' => 'NIO es la moneda base del sistema y no requiere tasa.']);
        }

        $currency = Currency::query()->where('code', $baseCurrency)->firstOrFail();
        if ($currency->is_fixed) {
            throw ValidationException::withMessages(['exchange_rate' => "La tasa {$baseCurrency}->NIO está configurada como fija."]);
        }

        $rate = $this->fetchFromApi($baseCurrency, 'NIO');
        if ($rate === null) {
            throw ValidationException::withMessages(['exchange_rate' => "No fue posible obtener automáticamente la tasa {$baseCurrency}->NIO."]);
        }

        Cache::forget($this->cacheKey($baseCurrency, 'NIO'));

        return $this->persistRate($baseCurrency, 'NIO', $rate);
    }

    public function syncSupplierCostInNio(
        QuotationRequestItem $item,
        mixed $supplierCost,
        ?string $supplierCurrency,
        ?User $staleApprovedBy = null,
    ): QuotationRequestItem {
        $supplierCurrency = $this->currency($supplierCurrency, 'supplier_currency');
        $supplierCost = $this->amount($supplierCost);
        $converted = $this->convert($supplierCost, $supplierCurrency, 'NIO', $staleApprovedBy);

        $item->forceFill([
            'unit_cost' => $supplierCost,
            'supplier_currency' => $supplierCurrency,
            'supplier_unit_cost_original' => $supplierCost,
            'supplier_unit_cost_nio' => $converted['amount'],
            'exchange_rate' => $converted['rate'],
            ...$this->itemRatePayload($converted),
        ])->save();

        if ($supplierCurrency === 'USD') {
            $item->quotationRequest?->forceFill($this->quotationRatePayload($converted))->save();
        }

        return $item->fresh();
    }

    public function applyToClientOffer(
        QuotationRequest $request,
        bool $forceRefresh = false,
        ?User $staleApprovedBy = null,
    ): QuotationRequest {
        return DB::transaction(function () use ($request, $forceRefresh, $staleApprovedBy): QuotationRequest {
            $lockedRequest = QuotationRequest::query()->lockForUpdate()->findOrFail($request->id);
            $items = QuotationRequestItem::query()
                ->with('supplierBidSelection.responseItem.response.supplier')
                ->where('quotation_request_id', $lockedRequest->id)
                ->lockForUpdate()
                ->get();
            $markup = $this->rate(config('services.exchange_rate.default_markup', '1.35'));
            $ratesByCurrency = [];

            // Primero se validan y convierten todas las partidas. Ningun cambio
            // se guarda si una moneda, tasa o monto posterior resulta invalido.
            $updates = $items->map(function (QuotationRequestItem $item) use (
                &$ratesByCurrency,
                $forceRefresh,
                $staleApprovedBy,
                $markup,
            ): array {
                $selection = $item->supplierBidSelection;
                $supplierCurrency = $this->currency($selection?->currency ?: 'USD', 'supplier_currency');
                $supplierCost = $this->amount($selection?->selected_unit_cost ?? $item->unit_cost ?? '0');
                $converted = $ratesByCurrency[$supplierCurrency] ??= $this->rateToNio(
                    $supplierCurrency,
                    $staleApprovedBy,
                    $forceRefresh,
                );
                $converted['amount'] = DecimalMoney::convertToBase($supplierCost, $converted['rate']);

                $payload = [
                    'supplier_currency' => $supplierCurrency,
                    'supplier_unit_cost_original' => $supplierCost,
                    'exchange_rate' => $converted['rate'],
                    ...$this->itemRatePayload($converted),
                    'supplier_unit_cost_nio' => $converted['amount'],
                    'client_currency' => 'NIO',
                    'unit_cost' => $supplierCost,
                ];

                $existingPrice = (float) ($item->unit_price ?? 0);
                if (DecimalMoney::compare($this->amount($existingPrice), '0.00') <= 0) {
                    $preliqNio = (float) ($selection?->selected_unit_price_nio
                        ?? $selection?->responseItem?->preliquidated_unit_price_nio
                        ?? 0);
                    if ($preliqNio <= 0 && $selection?->responseItem?->response) {
                        try {
                            $calc = app(\App\Services\PreliquidationCalculator::class)->forResponse($selection->responseItem->response);
                            $rCalc = $calc['rows'][$selection->responseItem->id] ?? null;
                            if ($rCalc) {
                                $preliqNio = (float) ($rCalc['assigned_unit_nio'] ?: ($rCalc['price_nio'] ?? 0));
                            }
                        } catch (\Throwable) {
                            // ignore
                        }
                    }
                    if ($preliqNio > 0) {
                        $payload['unit_price'] = $this->amount($preliqNio);
                    } elseif (DecimalMoney::compare($converted['amount'], '0.00') > 0) {
                        $payload['unit_price'] = $this->amount($converted['amount']);
                    }
                    if (isset($payload['unit_price'])) {
                        $payload['line_total'] = DecimalMoney::convertToBase(
                            $payload['unit_price'],
                            $this->rate($item->quantity),
                        );
                    }
                } else {
                    $payload['unit_price'] = $this->amount($existingPrice);
                    $payload['line_total'] = DecimalMoney::convertToBase(
                        $payload['unit_price'],
                        $this->rate($item->quantity),
                    );
                }

                return [$item, $payload];
            });

            foreach ($updates as [$item, $payload]) {
                $item->forceFill($payload)->save();
            }

            $hasApprovedItems = $lockedRequest->items()->where('is_approved', true)->exists();
            $subtotalQuery = $hasApprovedItems ? $lockedRequest->items()->where('is_approved', true) : $lockedRequest->items();
            $subtotal = $this->amount($subtotalQuery->sum('line_total'));
            $taxMultiplier = DecimalMoney::percentageMultiplier($this->amount($lockedRequest->tax_percent ?? '15'));

            $lockedRequest->forceFill([
                ...$this->quotationRateSummary($ratesByCurrency),
                'currency' => 'NIO',
                'subtotal' => $subtotal,
                'total_amount' => DecimalMoney::convertToBase($subtotal, $taxMultiplier),
            ])->save();

            return $lockedRequest->fresh(['items.supplierBidSelection.responseItem.response.supplier']);
        });
    }

    private function resolveRate(
        string $fromCurrency,
        string $toCurrency,
        bool $forceRefresh,
        ?User $staleApprovedBy,
    ): array {
        $this->assertSupportedPair($fromCurrency, $toCurrency);
        $cacheKey = $this->cacheKey($fromCurrency, $toCurrency);

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        if (! $forceRefresh && $staleApprovedBy === null && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $stored = $this->latestStoredRate($fromCurrency, $toCurrency);

        // Una tasa manual aprobada es una decision financiera explicita y tiene
        // prioridad durante su vigencia sobre cualquier actualizacion automatica.
        if ($stored?->source_type === ExchangeRateSourceType::ManualApproved
            && (! $this->isStale($stored) || $staleApprovedBy !== null)) {
            $rate = $this->storedRate($stored, $staleApprovedBy);
            if (! $rate['is_stale']) {
                Cache::put($cacheKey, $rate, now()->endOfDay());
            }

            return $rate;
        }

        if ($forceRefresh || ! $stored) {
            $apiRate = $this->fetchFromApi($fromCurrency, $toCurrency);
            if ($apiRate !== null && ! $this->isDateStale($apiRate['date'])) {
                $apiRate['exchange_rate_id'] = $this->persistRate($fromCurrency, $toCurrency, $apiRate)->id;
                Cache::put($cacheKey, $apiRate, now()->endOfDay());

                return $apiRate;
            }
        }

        if ($stored && (! $this->isStale($stored) || $staleApprovedBy !== null)) {
            $rate = $this->storedRate($stored, $staleApprovedBy);
            if (! $rate['is_stale']) {
                Cache::put($cacheKey, $rate, now()->endOfDay());
            }

            return $rate;
        }

        $configured = $this->configuredFallback($fromCurrency, $toCurrency);
        if ($configured !== null) {
            $configured['exchange_rate_id'] = $this->persistRate($fromCurrency, $toCurrency, $configured)->id;
            Cache::put($cacheKey, $configured, now()->endOfDay());

            return $configured;
        }

        if ($stored) {
            throw ValidationException::withMessages([
                'exchange_rate' => 'La ultima tasa almacenada esta vencida y requiere aprobacion explicita de Finanzas.',
            ]);
        }

        throw ValidationException::withMessages([
            'exchange_rate' => "No existe una tasa disponible para {$fromCurrency}->{$toCurrency}.",
        ]);
    }

    private function fetchFromApi(string $baseCurrency, string $quoteCurrency): ?array
    {
        $url = config('services.exchange_rate.api_url');
        if (blank($url)) {
            return null;
        }

        try {
            $response = Http::timeout((int) config('services.exchange_rate.timeout', 6))->get($url);
            if (! $response->ok()) {
                return null;
            }

            $data = $response->json();
            $apiBaseCurrency = strtoupper(trim((string) (
                data_get($data, config('services.exchange_rate.base_path', 'base'))
                ?? data_get($data, 'base_code')
            )));
            $expectedBase = strtoupper((string) config('services.exchange_rate.expected_base', 'USD'));
            if ($apiBaseCurrency === '' || $apiBaseCurrency !== $expectedBase) {
                return null;
            }
            $quoteRate = data_get($data, 'rates.'.$quoteCurrency)
                ?? data_get($data, 'conversion_rates.'.$quoteCurrency);
            $requestedBaseRate = $baseCurrency === $expectedBase
                ? 1
                : (data_get($data, 'rates.'.$baseCurrency) ?? data_get($data, 'conversion_rates.'.$baseCurrency));
            $rate = $requestedBaseRate ? ((float) $quoteRate / (float) $requestedBaseRate) : null;
            $rate = $this->rate($rate);
            $date = $this->date(data_get($data, config('services.exchange_rate.date_path', 'date')) ?: now()->toDateString());
            $sourceType = config('services.exchange_rate.api_is_official', false)
                ? ExchangeRateSourceType::Official
                : ExchangeRateSourceType::Api;

            return $this->ratePayload(
                $rate,
                $date,
                (string) config('services.exchange_rate.source', 'api'),
                $sourceType,
                false,
                false,
            );
        } catch (Throwable) {
            return null;
        }
    }

    private function latestStoredRate(string $fromCurrency, string $toCurrency): ?ExchangeRate
    {
        return ExchangeRate::where('base_currency', $fromCurrency)
            ->where('quote_currency', $toCurrency)
            ->latest('rate_date')
            ->latest('id')
            ->first();
    }

    private function storedRate(ExchangeRate $stored, ?User $staleApprovedBy): array
    {
        $isStale = $this->isStale($stored);
        if ($isStale && $staleApprovedBy !== null) {
            $this->assertCanApprove($staleApprovedBy);
            $stored->forceFill([
                'stale_approved_by' => $staleApprovedBy->id,
                'stale_approved_at' => now(),
            ])->save();
            $this->logApproval($staleApprovedBy, 'exchange_rate.stale_approved', $stored);
        }

        $originType = $stored->source_type instanceof ExchangeRateSourceType
            ? $stored->source_type
            : ExchangeRateSourceType::Legacy;
        $warning = $isStale
            ? ($staleApprovedBy !== null ? 'Tasa almacenada vencida aprobada explicitamente por Finanzas.' : 'La ultima tasa almacenada esta vencida y requiere verificacion de Finanzas.')
            : 'Usando una tasa almacenada previamente dentro de su vigencia.';

        return $this->ratePayload(
            $stored->rate,
            $stored->rate_date?->toDateString() ?: now()->toDateString(),
            (string) $stored->source,
            $originType,
            true,
            true,
            $isStale,
            $isStale ? $staleApprovedBy?->id : null,
            $warning,
            $stored->id,
        );
    }

    private function configuredFallback(string $fromCurrency, string $toCurrency): ?array
    {
        if ($toCurrency !== 'NIO' || ! in_array($fromCurrency, ['USD', 'EUR', 'INR'], true)) {
            return null;
        }

        try {
            $rate = $this->rate(config('services.exchange_rate.manual_'.strtolower($fromCurrency).'_nio'));
            $date = $this->date(config('services.exchange_rate.manual_'.strtolower($fromCurrency).'_nio_date'));
        } catch (ValidationException) {
            return null;
        }

        if ($this->isDateStale($date)) {
            return null;
        }

        $sourceType = ExchangeRateSourceType::ConfiguredFallback;

        return $this->ratePayload(
            $rate,
            $date,
            'configured-'.strtolower($fromCurrency).'-nio',
            $sourceType,
            true,
            false,
            false,
            null,
            "Usando tasa fallback configurada para {$fromCurrency}->NIO.",
        );
    }

    private function persistRate(string $fromCurrency, string $toCurrency, array $rate): ExchangeRate
    {
        $existing = ExchangeRate::query()
            ->where('base_currency', $fromCurrency)
            ->where('quote_currency', $toCurrency)
            ->whereDate('rate_date', $rate['date'])
            ->first();

        // Una tasa aprobada manualmente es un registro financiero. Una consulta
        // automatica del mismo dia no debe borrar ni su aprobador ni su origen.
        if ($existing?->source_type === ExchangeRateSourceType::ManualApproved
            && $rate['source_type'] !== ExchangeRateSourceType::ManualApproved->value) {
            return $existing;
        }

        return ExchangeRate::updateOrCreate(
            [
                'base_currency' => $fromCurrency,
                'quote_currency' => $toCurrency,
                'rate_date' => $rate['date'],
            ],
            [
                'rate' => $rate['rate'],
                'expires_at' => CarbonImmutable::parse($rate['date'])->addDays($this->maxAgeDays())->toDateString(),
                'source' => $rate['source'],
                'source_type' => $rate['source_type'],
                'is_fallback' => $rate['is_fallback'],
                'fetched_at' => now(),
                'metadata' => [
                    'source_label' => $rate['source_label'],
                    'warning' => $rate['warning'],
                ],
            ],
        );
    }

    private function ratePayload(
        mixed $rate,
        string $date,
        string $source,
        ExchangeRateSourceType $sourceType,
        bool $isFallback,
        bool $isStored,
        bool $isStale = false,
        ?int $approvedBy = null,
        ?string $warning = null,
        ?int $exchangeRateId = null,
    ): array {
        return [
            'rate' => $this->rate($rate),
            'date' => $date,
            'source' => $source,
            'source_type' => $sourceType->value,
            'source_label' => $isStored
                ? ExchangeRateSourceType::Stored->label().' (origen: '.$sourceType->label().')'
                : $sourceType->label(),
            'is_stored' => $isStored,
            'is_fallback' => $isFallback,
            'is_stale' => $isStale,
            'requires_confirmation' => $isStale,
            'approved_by' => $approvedBy,
            'exchange_rate_id' => $exchangeRateId,
            'warning' => $warning,
        ];
    }

    private function quotationRatePayload(array $rate): array
    {
        return [
            'exchange_rate' => $rate['rate'],
            'exchange_rate_date' => $rate['date'],
            'exchange_rate_source' => $rate['source'],
            'exchange_rate_source_type' => $rate['source_type'],
            'exchange_rate_is_fallback' => $rate['is_fallback'],
            'exchange_rate_is_stale' => $rate['is_stale'],
            'exchange_rate_approved_by' => $rate['approved_by'],
            'exchange_rate_warning' => $rate['warning'],
        ];
    }

    private function itemRatePayload(array $rate): array
    {
        return [
            'exchange_rate_id' => $rate['exchange_rate_id'] ?? null,
            'exchange_rate_date' => $rate['date'],
            'exchange_rate_source_type' => $rate['source_type'],
            'exchange_rate_is_fallback' => $rate['is_fallback'],
            'exchange_rate_is_stale' => $rate['is_stale'],
            'exchange_rate_approved_by' => $rate['approved_by'],
        ];
    }

    private function quotationRateSummary(array $ratesByCurrency): array
    {
        if (count($ratesByCurrency) === 1) {
            return $this->quotationRatePayload(reset($ratesByCurrency));
        }

        $sourceType = ExchangeRateSourceType::Multiple;

        return [
            'exchange_rate' => null,
            'exchange_rate_date' => null,
            'exchange_rate_source' => $sourceType->value,
            'exchange_rate_source_type' => $sourceType->value,
            'exchange_rate_is_fallback' => collect($ratesByCurrency)->contains('is_fallback', true),
            'exchange_rate_is_stale' => collect($ratesByCurrency)->contains('is_stale', true),
            'exchange_rate_approved_by' => null,
            'exchange_rate_warning' => count($ratesByCurrency) > 1
                ? 'La oferta utiliza multiples tasas; consulte la trazabilidad de cada partida.'
                : 'La oferta no contiene partidas con tasa de cambio.',
        ];
    }

    private function assertSupportedPair(string $fromCurrency, string $toCurrency): void
    {
        if ($fromCurrency === $toCurrency) {
            return;
        }

        if ($toCurrency !== 'NIO') {
            throw ValidationException::withMessages([
                'currency_pair' => "El par de monedas {$fromCurrency}->{$toCurrency} no esta soportado.",
            ]);
        }
    }

    private function assertCanApprove(User $approver): void
    {
        if (! $approver->hasPermissionTo('finance.exchange_rates.approve')) {
            throw ValidationException::withMessages([
                'approved_by' => 'El usuario no tiene permiso para aprobar tasas de cambio.',
            ]);
        }
    }

    private function currency(?string $currency, string $field): string
    {
        $currency = strtoupper(trim((string) $currency));
        $supported = $currency !== '' && (
            $currency === 'NIO'
            || Currency::query()->where('code', $currency)->where('is_active', true)->exists()
        );
        if (! $supported) {
            throw ValidationException::withMessages([
                $field => 'La moneda indicada no esta soportada.',
            ]);
        }

        return $currency;
    }

    private function amount(mixed $amount): string
    {
        try {
            return DecimalMoney::amount($this->decimalInput($amount, 2));
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['amount' => $exception->getMessage()]);
        }
    }

    private function rate(mixed $rate): string
    {
        try {
            $rate = DecimalMoney::exchangeRate($this->decimalInput($rate, 6));
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['exchange_rate' => $exception->getMessage()]);
        }

        if ($rate === '0.000000') {
            throw ValidationException::withMessages(['exchange_rate' => 'La tasa debe ser mayor que cero.']);
        }

        return $rate;
    }

    private function decimalInput(mixed $value, int $scale): mixed
    {
        if (is_float($value)) {
            return number_format($value, $scale, '.', '');
        }

        return $value;
    }

    private function date(mixed $date): string
    {
        $date = trim((string) $date);
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $matches)
            || ! checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])) {
            throw ValidationException::withMessages(['rate_date' => 'La fecha de la tasa no es valida.']);
        }

        if (CarbonImmutable::parse($date)->isAfter(now()->endOfDay())) {
            throw ValidationException::withMessages(['rate_date' => 'La fecha de la tasa no puede estar en el futuro.']);
        }

        return $date;
    }

    private function isStale(ExchangeRate $rate): bool
    {
        if (Currency::query()->where('code', $rate->base_currency)->where('is_fixed', true)->exists()) {
            return false;
        }

        $expiresAt = $rate->expires_at ?: $rate->rate_date?->copy()->addDays($this->maxAgeDays());

        return $expiresAt === null || now()->startOfDay()->gt($expiresAt->startOfDay());
    }

    private function isDateStale(string $date): bool
    {
        return now()->startOfDay()->gt(CarbonImmutable::parse($date)->addDays($this->maxAgeDays())->startOfDay());
    }

    private function maxAgeDays(): int
    {
        return max(0, (int) config('services.exchange_rate.max_age_days', 3));
    }

    private function cacheKey(string $fromCurrency, string $toCurrency): string
    {
        return "exchange-rate:{$fromCurrency}:{$toCurrency}:".now()->toDateString();
    }

    private function logApproval(User $user, string $action, ExchangeRate $rate): void
    {
        ActivityLog::create([
            'user_id' => $user->id,
            'module' => 'finanzas',
            'action' => $action,
            'description' => "Tasa {$rate->base_currency}->{$rate->quote_currency} aprobada.",
            'metadata' => [
                'exchange_rate_id' => $rate->id,
                'base_currency' => $rate->base_currency,
                'quote_currency' => $rate->quote_currency,
                'rate' => $rate->rate,
                'rate_date' => $rate->rate_date?->toDateString(),
                'source' => $rate->source,
            ],
            'ip_address' => request()->ip(),
        ]);
    }
}
