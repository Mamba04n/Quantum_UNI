<?php

namespace Tests\Feature\Storefront;

use App\Services\ExchangeRateService;
use App\Services\Storefront\CartQuoteSummary;
use Tests\TestCase;

class CartQuoteSummaryTest extends TestCase
{
    public function test_shipping_thresholds_and_nio_conversion(): void
    {
        config(['services.storefront.catalog_currency' => 'USD', 'services.storefront.free_shipping_threshold_usd' => '250.00']);
        $rates = \Mockery::mock(ExchangeRateService::class);
        $rates->shouldReceive('rateToNio')->with('USD')->andReturn(['rate' => '36.500000']);
        $service = new CartQuoteSummary($rates);
        foreach ([['100.00', 40, false, '150.00'], ['250.00', 100, true, '0.00'], ['300.00', 100, true, '0.00']] as [$price,$progress,$reached,$remaining]) {
            $summary = $service->build(collect([(object) ['id' => 1, 'sales_price' => $price, 'cart_quantity' => 1]]));
            $this->assertEquals($progress, $summary['progress']);
            $this->assertSame($reached, $summary['reached']);
            $this->assertSame($remaining, $summary['remainingUsd']);
            $this->assertSame('9125.00', $summary['thresholdNio']);
        }
        config(['services.storefront.catalog_currency' => 'NIO']);
        $summary = $service->build(collect([(object) ['id' => 1, 'sales_price' => '1825.00', 'cart_quantity' => 2]]));
        $this->assertSame('100.00', $summary['usd']);
        $this->assertSame('3650.00', $summary['nio']);
        $this->assertSame('5475.00', $summary['remainingNio']);
    }

    public function test_unknown_prices_empty_cart_and_missing_rate_do_not_claim_free_shipping(): void
    {
        config(['services.storefront.catalog_currency' => 'NIO']);
        $rates = \Mockery::mock(ExchangeRateService::class);
        $rates->shouldReceive('rateToNio')->andThrow(new \RuntimeException('No rate'));
        $service = new CartQuoteSummary($rates);
        foreach ([collect(), collect([(object) ['id' => 1, 'sales_price' => '0.00', 'cart_quantity' => 2]]), collect([(object) ['id' => 1, 'sales_price' => '100.00', 'cart_quantity' => 2]])] as $items) {
            $summary = $service->build($items);
            $this->assertNull($summary['usd']);
            $this->assertFalse($summary['reached']);
            $this->assertSame(0, $summary['progress']);
        }
    }
}
