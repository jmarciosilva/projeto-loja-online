<?php

namespace App\DTO\Shipping;

/** Dados persistidos de uma linha física, sem tratar medidas do produto como embalagem. */
final readonly class ShippingPackageCandidate
{
    public function __construct(
        public int $productId,
        public int $quantity,
        public int $unitDeclaredValueCents,
        public ShippingPackage $unitPackage,
    ) {}
}
