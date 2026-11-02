<?php

namespace App\Contracts\Shipping;

use App\DTO\Shipping\ShippingQuote;
use App\DTO\Shipping\ShippingQuoteRequest;

interface ShippingQuoteProvider extends ShippingProviderAdapter
{
    public function quote(ShippingQuoteRequest $request): ShippingQuote;
}
