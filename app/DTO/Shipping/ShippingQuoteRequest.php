<?php

namespace App\DTO\Shipping;

use InvalidArgumentException;

final readonly class ShippingQuoteRequest
{
    /** @param non-empty-list<ShippingPackage> $packages */
    public function __construct(
        public string $originPostalCode,
        public string $destinationPostalCode,
        public array $packages,
        public int $declaredValueCents,
    ) {
        if (! preg_match('/^\d{8}$/', $originPostalCode) || ! preg_match('/^\d{8}$/', $destinationPostalCode)) {
            throw new InvalidArgumentException('Os CEPs devem conter oito dígitos.');
        }

        if ($packages === [] || array_filter($packages, static fn (mixed $package): bool => ! $package instanceof ShippingPackage) !== []) {
            throw new InvalidArgumentException('A cotação exige ao menos um pacote válido.');
        }

        if ($declaredValueCents < 0) {
            throw new InvalidArgumentException('O valor declarado não pode ser negativo.');
        }
    }
}
