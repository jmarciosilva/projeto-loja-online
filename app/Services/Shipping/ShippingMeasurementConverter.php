<?php

namespace App\Services\Shipping;

use InvalidArgumentException;

/** Conversões exatas de medidas decimais para inteiros normalizados. */
final class ShippingMeasurementConverter
{
    public static function kilogramsToGrams(string|int $value): int
    {
        $normalized = self::normalize($value, '/^\d{1,4}(?:\.\d{1,3})?$/');
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $grams = ((int) $whole * 1000) + (int) str_pad($fraction, 3, '0');

        if ($grams < 1 || $grams > 1_000_000) {
            throw new InvalidArgumentException('O peso deve estar entre 0,001 kg e 1.000 kg.');
        }

        return $grams;
    }

    public static function centimetersToMillimeters(string|int $value): int
    {
        $normalized = self::normalize($value, '/^\d{1,3}(?:\.\d{1,2})?$/');
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $tenthsOfMillimeter = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
        $millimeters = intdiv($tenthsOfMillimeter + 9, 10);

        if ($millimeters < 1 || $millimeters > 3000) {
            throw new InvalidArgumentException('A dimensão deve estar entre 0,01 cm e 300 cm.');
        }

        return $millimeters;
    }

    public static function decimalToCents(string $value): int
    {
        $normalized = self::normalize($value, '/^\d{1,10}(?:\.\d{1,2})?$/');
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private static function normalize(string|int $value, string $pattern): string
    {
        $normalized = str_replace(',', '.', trim((string) $value));
        if (preg_match($pattern, $normalized) !== 1) {
            throw new InvalidArgumentException('A medida informada não segue o formato decimal permitido.');
        }

        return $normalized;
    }
}
