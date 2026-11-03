<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    /** O histórico só é gravado por StockService e não aceita mass assignment. */
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'quantity_delta' => 'integer',
            'quantity_before' => 'integer',
            'quantity_after' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(ProductStock::class, 'product_stock_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
