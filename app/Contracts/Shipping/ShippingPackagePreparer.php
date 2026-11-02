<?php

namespace App\Contracts\Shipping;

use App\DTO\Shipping\ShippingPackageCandidate;
use App\DTO\Shipping\ShippingPackagePreparation;

/** Política interna; não representa uma operação de provedor. */
interface ShippingPackagePreparer
{
    /** @param list<ShippingPackageCandidate> $candidates */
    public function prepare(array $candidates): ShippingPackagePreparation;
}
