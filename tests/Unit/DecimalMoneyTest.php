<?php

namespace Tests\Unit;

use App\Domain\Finanzas\ValueObjects\DecimalMoney;
use PHPUnit\Framework\TestCase;

class DecimalMoneyTest extends TestCase
{
    public function test_it_converts_from_nio_using_the_direct_foreign_currency_rate(): void
    {
        $this->assertSame('3685.00', DecimalMoney::convertFromBase('136345.00', '37.000000'));
        $this->assertSame('19.11', DecimalMoney::convertFromBase('707.00', '37.000000'));
    }
}
