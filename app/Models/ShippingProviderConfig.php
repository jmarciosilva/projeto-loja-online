<?php

namespace App\Models;

use App\Enums\ShippingConnectionStatus;
use App\Enums\ShippingEnvironment;
use App\Enums\ShippingProviderCode;
use Illuminate\Database\Eloquent\Model;

class ShippingProviderConfig extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'provider',
        'environment',
        'origin_postal_code',
        'enabled_service_codes',
        'credentials',
        'connection_status',
        'last_tested_at',
        'last_error_code',
    ];

    /** @var list<string> */
    protected $hidden = ['credentials'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'provider' => ShippingProviderCode::class,
            'environment' => ShippingEnvironment::class,
            'enabled_service_codes' => 'array',
            'credentials' => 'encrypted:array',
            'connection_status' => ShippingConnectionStatus::class,
            'last_tested_at' => 'immutable_datetime',
        ];
    }
}
