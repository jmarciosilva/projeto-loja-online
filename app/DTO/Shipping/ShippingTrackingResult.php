<?php

namespace App\DTO\Shipping;

/** @param list<array{occurred_at: string|null, status: string, description: string}> $events */
final readonly class ShippingTrackingResult
{
    public function __construct(
        public ?string $trackingCode,
        public ?string $trackingUrl,
        public array $events,
    ) {}
}
