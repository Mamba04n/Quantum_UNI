<?php

namespace App\Domain\Finanzas\ExchangeRates\Enums;

enum ExchangeRateSourceType: string
{
    case Official = 'official';
    case Api = 'api';
    case ManualApproved = 'manual_approved';
    case Stored = 'stored';
    case ConfiguredFallback = 'configured_fallback';
    case SameCurrency = 'same_currency';
    case Multiple = 'multiple';
    case Legacy = 'legacy';

    public function label(): string
    {
        return match ($this) {
            self::Official => 'Tasa oficial obtenida por API',
            self::Api => 'Tasa obtenida por API',
            self::ManualApproved => 'Tasa manual aprobada por Finanzas',
            self::Stored => 'Tasa almacenada previamente',
            self::ConfiguredFallback => 'Tasa fallback configurada',
            self::SameCurrency => 'Sin conversion de moneda',
            self::Multiple => 'Multiples tasas por moneda',
            self::Legacy => 'Tasa historica sin clasificar',
        };
    }
}
