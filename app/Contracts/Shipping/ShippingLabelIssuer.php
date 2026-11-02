<?php

namespace App\Contracts\Shipping;

use App\DTO\Shipping\ShippingLabel;
use App\DTO\Shipping\ShippingShipmentReference;

interface ShippingLabelIssuer extends ShippingProviderAdapter
{
    public function issueLabel(ShippingShipmentReference $shipment): ShippingLabel;
}
