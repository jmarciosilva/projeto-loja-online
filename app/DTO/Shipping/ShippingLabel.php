<?php

namespace App\DTO\Shipping;

final readonly class ShippingLabel
{
    public function __construct(
        public string $contentType,
        public string $content,
    ) {}
}
