<?php

namespace App\DTO\Shipping;

use App\Enums\ShippingProviderCode;

final readonly class ShippingShipmentReference
{
    public function __construct(
        public ShippingProviderCode $provider,
        public string $externalId,
    ) {}
}
