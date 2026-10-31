<?php

namespace App\Domain\Finanzas\ValueObjects;

use InvalidArgumentException;

final class DecimalMoney
{
    private const AMOUNT_SCALE = 2;

    private const EXCHANGE_RATE_SCALE = 6;

    public static function amount(mixed $value): string
    {
        return self::normalize($value, self::AMOUNT_SCALE);
    }

    public static function exchangeRate(mixed $value): string
    {
        return self::normalize($value, self::EXCHANGE_RATE_SCALE);
    }

    public static function compare(string $left, string $right): int
    {
        return self::minorUnits($left) <=> self::minorUnits($right);
    }

    public static function add(string $left, string $right): string
    {
        return self::fromMinorUnits(self::minorUnits($left) + self::minorUnits($right));
    }

    public static function subtract(string $left, string $right): string
    {
        $result = self::minorUnits($left) - self::minorUnits($right);

        if ($result < 0) {
            throw new InvalidArgumentException('El resultado monetario no puede ser negativo.');
        }

        return self::fromMinorUnits($result);
    }

    public static function convertToBase(string $amount, string $exchangeRate): string
    {
        $amountDigits = str_replace('.', '', self::amount($amount));
        $rateDigits = str_replace('.', '', self::exchangeRate($exchangeRate));
        $product = self::multiplyUnsignedIntegers($amountDigits, $rateDigits);

        return self::roundedProduct($product, self::AMOUNT_SCALE + self::EXCHANGE_RATE_SCALE, self::AMOUNT_SCALE);
    }

    public static function convertFromBase(string $amount, string $exchangeRate): string
    {
        $amount = self::amount($amount);
        $exchangeRate = self::exchangeRate($exchangeRate);

        if ($exchangeRate === '0.000000') {
            throw new InvalidArgumentException('La tasa debe ser mayor que cero.');
        }

        return self::amount(number_format(
            round((float) $amount / (float) $exchangeRate, self::AMOUNT_SCALE),
            self::AMOUNT_SCALE,
            '.',
            '',
        ));
    }

    public static function percentageMultiplier(mixed $percentage): string
    {
        $percentageMinorUnits = self::minorUnits(self::amount($percentage));
        $rateUnits = 1_000_000 + ($percentageMinorUnits * 100);
        $digits = str_pad((string) $rateUnits, self::EXCHANGE_RATE_SCALE + 1, '0', STR_PAD_LEFT);

        return substr($digits, 0, -self::EXCHANGE_RATE_SCALE).'.'.substr($digits, -self::EXCHANGE_RATE_SCALE);
    }

    private static function normalize(mixed $value, int $scale): string
    {
        if (! is_int($value) && ! is_string($value)) {
            throw new InvalidArgumentException('El valor decimal debe recibirse como texto o entero.');
        }

        $value = trim((string) $value);

        if (! preg_match('/^\+?(\d+)(?:\.(\d+))?$/', $value, $matches)) {
            throw new InvalidArgumentException('El valor decimal no tiene un formato valido.');
        }

        $whole = ltrim($matches[1], '0') ?: '0';
        $fraction = $matches[2] ?? '';

        if (strlen($fraction) > $scale && trim(substr($fraction, $scale), '0') !== '') {
            throw new InvalidArgumentException("El valor admite como maximo {$scale} decimales.");
        }

        $fraction = str_pad(substr($fraction, 0, $scale), $scale, '0');

        return $scale === 0 ? $whole : "{$whole}.{$fraction}";
    }

    private static function minorUnits(string $amount): int
    {
        $normalized = self::amount($amount);
        $digits = ltrim(str_replace('.', '', $normalized), '0') ?: '0';

        if (strlen($digits) > strlen((string) PHP_INT_MAX)
            || (strlen($digits) === strlen((string) PHP_INT_MAX) && strcmp($digits, (string) PHP_INT_MAX) > 0)) {
            throw new InvalidArgumentException('El monto excede el limite soportado.');
        }

        return (int) $digits;
    }

    private static function fromMinorUnits(int $minorUnits): string
    {
        $digits = str_pad((string) $minorUnits, self::AMOUNT_SCALE + 1, '0', STR_PAD_LEFT);

        return substr($digits, 0, -self::AMOUNT_SCALE).'.'.substr($digits, -self::AMOUNT_SCALE);
    }

    private static function multiplyUnsignedIntegers(string $left, string $right): string
    {
        $left = ltrim($left, '0') ?: '0';
        $right = ltrim($right, '0') ?: '0';

        if ($left === '0' || $right === '0') {
            return '0';
        }

        $result = array_fill(0, strlen($left) + strlen($right), 0);

        for ($i = strlen($left) - 1; $i >= 0; $i--) {
            for ($j = strlen($right) - 1; $j >= 0; $j--) {
                $position = $i + $j + 1;
                $result[$position] += ((int) $left[$i]) * ((int) $right[$j]);
            }
        }

        for ($i = count($result) - 1; $i > 0; $i--) {
            $result[$i - 1] += intdiv($result[$i], 10);
            $result[$i] %= 10;
        }

        return ltrim(implode('', $result), '0') ?: '0';
    }

    private static function roundedProduct(string $digits, int $sourceScale, int $targetScale): string
    {
        $discardCount = $sourceScale - $targetScale;
        $digits = str_pad($digits, $discardCount + 1, '0', STR_PAD_LEFT);
        $kept = substr($digits, 0, -$discardCount);
        $discarded = substr($digits, -$discardCount);

        if ($discarded !== '' && $discarded[0] >= '5') {
            $kept = self::incrementUnsignedInteger($kept);
        }

        $kept = str_pad($kept, $targetScale + 1, '0', STR_PAD_LEFT);

        return substr($kept, 0, -$targetScale).'.'.substr($kept, -$targetScale);
    }

    private static function incrementUnsignedInteger(string $digits): string
    {
        $carry = 1;

        for ($i = strlen($digits) - 1; $i >= 0 && $carry === 1; $i--) {
            $digit = ((int) $digits[$i]) + $carry;
            $digits[$i] = (string) ($digit % 10);
            $carry = intdiv($digit, 10);
        }

        return $carry === 1 ? '1'.$digits : $digits;
    }
}
