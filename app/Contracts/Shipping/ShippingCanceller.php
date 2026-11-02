<?php

namespace App\Contracts\Shipping;

use App\DTO\Shipping\ShippingShipmentReference;

interface ShippingCanceller extends ShippingProviderAdapter
{
    public function cancel(ShippingShipmentReference $shipment, string $reason): void;
}
