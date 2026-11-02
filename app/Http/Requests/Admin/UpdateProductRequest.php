<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use App\Services\ProductService;
use Closure;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends StoreProductRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('product')) ?? false;
    }

    public function rules(): array
    {
        /** @var Product $product */
        $product = $this->route('product');

        return [
            'category_id' => ['required', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'main_media_id' => ['nullable', 'integer', $this->validMedia()],
            'gallery_media_ids' => ['sometimes', 'array', 'max:6'],
            'gallery_media_ids.*' => ['integer', 'distinct', $this->validMedia()],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('products', 'slug')->ignore($product->id)->whereNull('deleted_at'), $this->slugRule($product->id)],
            'sku' => ['nullable', 'string', 'max:255', Rule::unique('products', 'sku')->ignore($product->id)],
            'description' => ['nullable', 'string'],
            'cost_price' => ['nullable', 'string', 'regex:/^\d{1,10}(?:[,.]\d{1,2})?$/'],
            'target_margin' => ['nullable', 'string', 'regex:/^\d{1,2}(?:[,.]\d{1,2})?$/'],
            'price' => ['required', 'string', 'regex:/^\d{1,10}(?:[,.]\d{1,2})?$/'],
            'promotional_price' => ['nullable', 'string', 'regex:/^\d{1,10}(?:[,.]\d{1,2})?$/'],
            'promotion_enabled' => ['sometimes', 'boolean'],
            'promotion_starts_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'promotion_ends_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function slugRule(?int $ignoreId = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($ignoreId): void {
            if (is_string($value) && trim($value) !== '' && ! app(ProductService::class)->slugIsAvailable($value, $ignoreId)) {
                $fail('Este slug já está em uso.');
            }
        };
    }
}
