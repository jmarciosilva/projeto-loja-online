<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductStock extends Model
{
    /** Alterações de saldo devem passar por StockService. */
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'integer',
            'quantity_reserved' => 'integer',
            'minimum_quantity' => 'integer',
            'stock_control_enabled' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function availableQuantity(): int
    {
        return $this->stock_control_enabled ? $this->quantity_on_hand - $this->quantity_reserved : 0;
    }

    public function publicAvailability(): string
    {
        if (! $this->stock_control_enabled) {
            return 'unreported';
        }

        return $this->availableQuantity() > 0 ? 'available' : 'sold_out';
    }
}
