<?php

namespace App\Contracts\Shipping;

use App\Enums\ShippingCapability;
use App\Enums\ShippingProviderCode;

/** Metadados do adaptador; não implica que haja conexão ou implementação ativa. */
interface ShippingProviderAdapter
{
    public function provider(): ShippingProviderCode;

    /** @return list<ShippingCapability> */
    public function capabilities(): array;
}
