<?php

namespace App\DTO\Shipping;

use InvalidArgumentException;

/** Medidas normalizadas para milímetros e massa para gramas. */
final readonly class ShippingPackage
{
    public function __construct(
        public int $weightGrams,
        public int $heightMillimeters,
        public int $widthMillimeters,
        public int $lengthMillimeters,
    ) {
        if (min($weightGrams, $heightMillimeters, $widthMillimeters, $lengthMillimeters) < 1) {
            throw new InvalidArgumentException('Peso e dimensões do pacote devem ser positivos.');
        }
    }
}
