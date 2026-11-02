<?php

namespace App\Models;

use App\Enums\ShippingProviderCode;
use Illuminate\Database\Eloquent\Model;

class ShippingSettings extends Model
{
    public const SINGLETON_KEY = 'default';

    /** @var list<string> */
    protected $fillable = ['key', 'active_provider'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['active_provider' => ShippingProviderCode::class];
    }
}
