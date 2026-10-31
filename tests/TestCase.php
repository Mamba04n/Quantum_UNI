<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // Fixture financiero explícito y exclusivo de pruebas. Producción no
        // recibe tasas predeterminadas ni fechas que puedan autorrenovarse.
        $today = now()->toDateString();
        config([
            'services.exchange_rate.manual_usd_nio' => '36.500000',
            'services.exchange_rate.manual_usd_nio_date' => $today,
            'services.exchange_rate.manual_eur_nio' => '42.500000',
            'services.exchange_rate.manual_eur_nio_date' => $today,
            'services.exchange_rate.manual_inr_nio' => '0.430000',
            'services.exchange_rate.manual_inr_nio_date' => $today,
        ]);
    }
}
