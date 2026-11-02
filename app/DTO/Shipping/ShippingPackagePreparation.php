<?php

namespace App\DTO\Shipping;

use InvalidArgumentException;

final readonly class ShippingPackagePreparation
{
    /** @param list<ShippingPackage> $packages */
    public function __construct(
        public bool $available,
        public array $packages = [],
        public int $declaredValueCents = 0,
        public ?string $reasonCode = null,
    ) {
        if ($available && ($packages === [] || $reasonCode !== null || $declaredValueCents < 0)) {
            throw new InvalidArgumentException('Uma preparação disponível precisa de pacotes e valor declarado válido.');
        }

        if (! $available && ($packages !== [] || $reasonCode === null)) {
            throw new InvalidArgumentException('Uma preparação indisponível precisa de um motivo e não pode conter pacotes.');
        }

        if (array_filter($packages, static fn (mixed $package): bool => ! $package instanceof ShippingPackage) !== []) {
            throw new InvalidArgumentException('A preparação contém um pacote inválido.');
        }
    }

    /** @param list<ShippingPackage> $packages */
    public static function available(array $packages, int $declaredValueCents): self
    {
        return new self(true, $packages, $declaredValueCents);
    }

    public static function unavailable(string $reasonCode): self
    {
        return new self(false, [], 0, $reasonCode);
    }
}
