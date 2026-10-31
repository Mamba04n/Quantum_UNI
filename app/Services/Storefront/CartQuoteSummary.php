<?php

namespace App\Services\Storefront;

use App\Domain\Finanzas\ValueObjects\DecimalMoney as Money;
use App\Services\ExchangeRateService;
use Illuminate\Support\Collection;

class CartQuoteSummary
{
    public function __construct(private ExchangeRateService $rates) {}

    public function build(Collection $items): array
    {
        $threshold = Money::amount((string) config('services.storefront.free_shipping_threshold_usd', '250.00'));
        $currency = config('services.storefront.catalog_currency', 'NIO');
        $rate = null;
        try {
            $rate = Money::exchangeRate((string) $this->rates->rateToNio('USD')['rate']);
            if ((float) $rate <= 0) {
                $rate = null;
            }
        } catch (\Throwable) {
            // A quote remains available when a current approved rate is missing.
        }
        $total = '0.00';
        $lines = [];
        $complete = $items->isNotEmpty();
        foreach ($items as $item) {
            $price = Money::amount((string) ($item->sales_price ?? '0'));
            $known = Money::compare($price, '0.00') > 0;
            $complete = $complete && $known;
            $line = '0.00';
            for ($unit = 0; $unit < (int) $item->cart_quantity; $unit++) {
                $line = Money::add($line, $price);
            }
            $total = Money::add($total, $line);
            $lines[$item->id] = [
                'unitUsd' => $known ? ($currency === 'USD' ? $price : ($rate ? Money::convertFromBase($price, $rate) : null)) : null,
                'totalUsd' => $known ? ($currency === 'USD' ? $line : ($rate ? Money::convertFromBase($line, $rate) : null)) : null,
            ];
        }
        $usd = $complete ? ($currency === 'USD' ? $total : ($rate ? Money::convertFromBase($total, $rate) : null)) : null;
        $nio = $complete ? ($currency === 'NIO' ? $total : ($rate ? Money::convertToBase($total, $rate) : null)) : null;
        $thresholdNio = $rate ? Money::convertToBase($threshold, $rate) : null;
        $reached = $usd !== null && ($currency === 'NIO'
            ? Money::compare($total, $thresholdNio) >= 0
            : Money::compare($usd, $threshold) >= 0);
        $remaining = $usd !== null ? ($reached ? '0.00' : Money::subtract($threshold, $usd)) : null;
        $remainingNio = $remaining !== null && $rate ? ($currency === 'NIO'
            ? ($reached ? '0.00' : Money::subtract($thresholdNio, $total))
            : Money::convertToBase($remaining, $rate)) : null;

        return [
            'usd' => $usd, 'nio' => $nio, 'lines' => $lines, 'rate' => $rate,
            'thresholdUsd' => $threshold, 'thresholdNio' => $thresholdNio,
            'remainingUsd' => $remaining, 'remainingNio' => $remainingNio,
            'progress' => $usd !== null ? min(100, (float) $usd / max(0.01, (float) $threshold) * 100) : 0,
            'reached' => $reached, 'complete' => $complete,
        ];
    }
}
