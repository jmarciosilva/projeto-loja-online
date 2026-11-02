<?php

namespace App\DTO\Shipping;

/** Endereços e dados fiscais serão tipados/refinados junto ao domínio de pedidos. */
final readonly class ShippingShipmentRequest
{
    /** @param array<string, string> $sender @param array<string, string> $recipient @param non-empty-list<ShippingPackage> $packages */
    public function __construct(
        public string $platformOrderReference,
        public array $sender,
        public array $recipient,
        public array $packages,
        public ShippingServiceOption $selectedService,
        public int $declaredValueCents,
    ) {}
}
