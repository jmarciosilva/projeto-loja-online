<?php

namespace App\DTO\Shipping;

use App\Enums\ShippingProviderCode;

final readonly class ShippingQuote
{
    /** @param list<ShippingServiceOption> $options */
    public function __construct(
        public ShippingProviderCode $provider,
        public array $options,
        public string $internalReference,
    ) {}
}
