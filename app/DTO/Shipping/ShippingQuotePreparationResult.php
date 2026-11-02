<?php

namespace App\DTO\Shipping;

use App\Enums\ShippingQuotePreparationStatus;
use InvalidArgumentException;

final readonly class ShippingQuotePreparationResult
{
    public function __construct(
        public ShippingQuotePreparationStatus $status,
        public ?ShippingQuoteRequest $request = null,
        public ?string $reasonCode = null,
    ) {
        if ($status === ShippingQuotePreparationStatus::Ready && ($request === null || $reasonCode !== null)) {
            throw new InvalidArgumentException('Uma preparação pronta precisa conter a solicitação normalizada.');
        }

        if ($status === ShippingQuotePreparationStatus::NotRequired && ($request !== null || $reasonCode !== null)) {
            throw new InvalidArgumentException('Uma preparação sem envio não pode conter cotação ou erro.');
        }

        if ($status === ShippingQuotePreparationStatus::Unavailable && ($request !== null || $reasonCode === null)) {
            throw new InvalidArgumentException('Uma preparação indisponível precisa informar o motivo.');
        }
    }

    public static function ready(ShippingQuoteRequest $request): self
    {
        return new self(ShippingQuotePreparationStatus::Ready, $request);
    }

    public static function notRequired(): self
    {
        return new self(ShippingQuotePreparationStatus::NotRequired);
    }

    public static function unavailable(string $reasonCode): self
    {
        return new self(ShippingQuotePreparationStatus::Unavailable, null, $reasonCode);
    }
}
