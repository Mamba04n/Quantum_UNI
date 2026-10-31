<?php

namespace App\Domain\Finanzas\Api\Support;

final class DecimalString
{
    public static function normalize(mixed $value, int $scale = 2): ?string
    {
        if ($value === null || ! is_numeric($value)) {
            return null;
        }

        [$whole, $fraction] = array_pad(explode('.', (string) $value, 2), 2, '');
        $fraction = substr(str_pad($fraction, $scale, '0'), 0, $scale);

        return ltrim($whole, '+').($scale > 0 ? '.'.$fraction : '');
    }

    public static function subtract(mixed $left, mixed $right, int $scale = 2): string
    {
        $leftScaled = self::toScaledInteger($left, $scale);
        $rightScaled = self::toScaledInteger($right, $scale);

        return self::fromScaledInteger(max(0, $leftScaled - $rightScaled), $scale);
    }

    private static function toScaledInteger(mixed $value, int $scale): int
    {
        $normalized = self::normalize($value, $scale) ?? self::fromScaledInteger(0, $scale);
        $negative = str_starts_with($normalized, '-');
        $digits = str_replace(['-', '.'], '', $normalized);
        $integer = (int) $digits;

        return $negative ? -$integer : $integer;
    }

    private static function fromScaledInteger(int $value, int $scale): string
    {
        $negative = $value < 0 ? '-' : '';
        $digits = str_pad((string) abs($value), $scale + 1, '0', STR_PAD_LEFT);

        if ($scale === 0) {
            return $negative.$digits;
        }

        return $negative.substr($digits, 0, -$scale).'.'.substr($digits, -$scale);
    }
}
