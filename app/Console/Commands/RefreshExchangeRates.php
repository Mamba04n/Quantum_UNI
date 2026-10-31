<?php

namespace App\Console\Commands;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Services\ExchangeRateService;
use Illuminate\Console\Command;
use Throwable;

class RefreshExchangeRates extends Command
{
    protected $signature = 'exchange-rates:refresh {currency?}';

    protected $description = 'Actualiza las tasas automáticas habilitadas contra NIO';

    public function handle(ExchangeRateService $service): int
    {
        $requested = strtoupper((string) $this->argument('currency'));
        $currencies = Currency::query()
            ->where('is_active', true)
            ->where('automatic_updates_enabled', true)
            ->where('is_fixed', false)
            ->where('code', '!=', 'NIO')
            ->when($requested !== '', fn ($query) => $query->where('code', $requested))
            ->get();

        if ($currencies->isEmpty()) {
            $this->warn('No hay monedas automáticas pendientes de actualizar.');

            return self::SUCCESS;
        }

        $failed = false;
        foreach ($currencies as $currency) {
            try {
                $lastAutomaticUpdate = ExchangeRate::query()
                    ->where('base_currency', $currency->code)
                    ->where('quote_currency', 'NIO')
                    ->whereNotNull('fetched_at')
                    ->latest('fetched_at')
                    ->value('fetched_at');
                if (! $requested && $lastAutomaticUpdate && now()->lt(\Carbon\CarbonImmutable::parse($lastAutomaticUpdate)->addHours($currency->automatic_update_interval_hours))) {
                    $this->line("{$currency->code}->NIO: todavía vigente.");
                    continue;
                }
                $rate = $service->refreshAutomaticRate($currency->code);
                $this->info("{$currency->code}->NIO: {$rate->rate}");
            } catch (Throwable $exception) {
                report($exception);
                $this->error("{$currency->code}->NIO: {$exception->getMessage()}");
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
