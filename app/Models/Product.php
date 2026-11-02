<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    /** @use HasFactory<\\Database\\Factories\\ProductFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id', 'main_media_id', 'name', 'slug', 'sku', 'description',
        'cost_price', 'target_margin', 'price', 'promotional_price',
        'promotion_enabled', 'promotion_starts_at', 'promotion_ends_at',
        'is_active', 'is_featured', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'target_margin' => 'decimal:2',
            'promotional_price' => 'decimal:2',
            'promotion_enabled' => 'boolean',
            'promotion_starts_at' => 'immutable_datetime',
            'promotion_ends_at' => 'immutable_datetime',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function mainMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'main_media_id');
    }

    public function images(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'product_images')
            ->withPivot('sort_order')->withTimestamps()->orderByPivot('sort_order')->orderBy('media.id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->active()->whereHas('category', fn (Builder $category) => $category->active());
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function promotionIsActive(?CarbonInterface $at = null): bool
    {
        if (! $this->promotion_enabled || $this->promotional_price === null) {
            return false;
        }

        $at ??= now(config('app.timezone'));

        return ($this->promotion_starts_at === null || $at >= $this->promotion_starts_at)
            && ($this->promotion_ends_at === null || $at < $this->promotion_ends_at);
    }

    public function effectivePrice(?CarbonInterface $at = null): string
    {
        return $this->promotionIsActive($at) ? $this->promotional_price : $this->price;
    }
}
