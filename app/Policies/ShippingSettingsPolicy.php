<?php

namespace App\Policies;

use App\Models\User;

class ShippingSettingsPolicy
{
    public function manage(User $user): bool
    {
        return $user->canManageShippingIntegrations();
    }
}
