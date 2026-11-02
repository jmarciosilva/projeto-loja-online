<?php

namespace App\Contracts\Shipping;

use App\DTO\Shipping\ShippingShipmentReference;
use App\DTO\Shipping\ShippingShipmentRequest;

interface ShippingShipmentPurchaser extends ShippingProviderAdapter
{
    public function purchase(ShippingShipmentRequest $request, string $idempotencyKey): ShippingShipmentReference;
}
