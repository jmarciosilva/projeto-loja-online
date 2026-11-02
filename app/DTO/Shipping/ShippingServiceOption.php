<?php

namespace App\DTO\Shipping;

use App\Enums\ShippingProviderCode;
use InvalidArgumentException;

final readonly class ShippingServiceOption
{
    public function __construct(
        public ShippingProviderCode $provider,
        public string $serviceCode,
        public string $serviceName,
        public int $priceCents,
        public ?int $minimumDeliveryDays = null,
        public ?int $maximumDeliveryDays = null,
        public ?string $providerQuoteReference = null,
    ) {
        if ($serviceCode === '' || $serviceName === '' || $priceCents < 0) {
            throw new InvalidArgumentException('A opção de serviço deve ter código, nome e preço não negativo.');
        }
    }
}
