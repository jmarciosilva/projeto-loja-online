<?php

namespace App\Contracts\Shipping;

use App\DTO\Shipping\ShippingShipmentReference;
use App\DTO\Shipping\ShippingTrackingResult;

interface ShippingTracker extends ShippingProviderAdapter
{
    public function track(ShippingShipmentReference $shipment): ShippingTrackingResult;
}
