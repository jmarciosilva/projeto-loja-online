<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductShippingProfile extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'product_id',
        'requires_shipping',
        'weight_kg',
        'height_cm',
        'width_cm',
        'length_cm',
        'package_weight_kg',
        'package_height_cm',
        'package_width_cm',
        'package_length_cm',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'requires_shipping' => 'boolean',
            'weight_kg' => 'decimal:3',
            'height_cm' => 'decimal:2',
            'width_cm' => 'decimal:2',
            'length_cm' => 'decimal:2',
            'package_weight_kg' => 'decimal:3',
            'package_height_cm' => 'decimal:2',
            'package_width_cm' => 'decimal:2',
            'package_length_cm' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function hasProductMeasurements(): bool
    {
        return $this->hasPositiveMeasurements([
            'weight_kg', 'height_cm', 'width_cm', 'length_cm',
        ]);
    }

    public function hasPackageMeasurements(): bool
    {
        return $this->hasPositiveMeasurements([
            'package_weight_kg', 'package_height_cm', 'package_width_cm', 'package_length_cm',
        ]);
    }

    /** @param list<string> $attributes */
    private function hasPositiveMeasurements(array $attributes): bool
    {
        foreach ($attributes as $attribute) {
            $value = $this->getAttribute($attribute);

            if (! is_string($value) || preg_match('/^\d+(?:\.\d+)?$/', $value) !== 1 || (float) $value <= 0) {
                return false;
            }
        }

        return true;
    }
}
