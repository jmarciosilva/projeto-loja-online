<?php

namespace App\Services\Shipping;

use App\Contracts\Shipping\ShippingPackagePreparer;
use App\DTO\Shipping\ShippingPackageCandidate;
use App\DTO\Shipping\ShippingPackagePreparation;

/** Política MVP: um produto físico, uma unidade, um pacote já medido. */
class SingleUnitShippingPackagePreparer implements ShippingPackagePreparer
{
    /** @param list<ShippingPackageCandidate> $candidates */
    public function prepare(array $candidates): ShippingPackagePreparation
    {
        if (count($candidates) !== 1) {
            return ShippingPackagePreparation::unavailable('multiple_physical_products_not_supported');
        }

        $candidate = $candidates[0];
        if ($candidate->quantity !== 1) {
            return ShippingPackagePreparation::unavailable('multiple_units_not_supported');
        }

        return ShippingPackagePreparation::available(
            [$candidate->unitPackage],
            $candidate->unitDeclaredValueCents,
        );
    }
}
