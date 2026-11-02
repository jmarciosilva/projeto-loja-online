<?php

namespace App\Http\Requests\Admin;

use App\Models\Category;
use App\Models\Media;
use App\Models\Product;
use App\Services\MediaService;
use App\Services\ProductService;
use Carbon\Carbon;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('sku'))) {
            $this->merge(['sku' => strtoupper(trim($this->input('sku')))]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->can('create', Product::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'main_media_id' => ['nullable', 'integer', $this->validMedia()],
            'gallery_media_ids' => ['sometimes', 'array', 'max:6'],
            'gallery_media_ids.*' => ['integer', 'distinct', $this->validMedia()],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $this->slugRule()],
            'sku' => ['nullable', 'string', 'max:255', 'unique:products,sku'],
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

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $normal = $this->request->get('price');
            $promo = $this->request->get('promotional_price');
            if (is_string($normal) && is_string($promo) && $promo !== ''
                && $this->validMoney($normal) && $this->validMoney($promo)
                && $this->cents($promo) >= $this->cents($normal)) {
                $validator->errors()->add('promotional_price', 'O preço promocional deve ser menor que o preço normal.');
            }
            $categoryId = $this->input('category_id');
            $active = in_array($this->input('is_active'), [true, 1, '1', 'on'], true);
            if ($active && is_numeric($categoryId)
                && ! Category::query()->whereKey($categoryId)->where('is_active', true)->exists()) {
                $validator->errors()->add('category_id', 'Ative a categoria antes de publicar o produto.');
            }

            $cost = $this->input('cost_price');
            $margin = $this->input('target_margin');
            if (filled($margin) && ! filled($cost)) {
                $validator->errors()->add('cost_price', 'Informe o preço de custo para usar uma margem desejada.');
            }
            if (is_string($margin) && $this->validMoney($margin) && $this->cents($margin) >= 10000) {
                $validator->errors()->add('target_margin', 'A margem precisa ser menor que 100%.');
            }
            if ($this->boolean('promotion_enabled') && ! filled($this->input('promotional_price'))) {
                $validator->errors()->add('promotional_price', 'Informe o preço promocional para habilitar a promoção.');
            }

            $start = $this->input('promotion_starts_at');
            $end = $this->input('promotion_ends_at');
            if (is_string($start) && is_string($end)
                && $this->validDateTime($start) && $this->validDateTime($end)
                && Carbon::createFromFormat('!Y-m-d\TH:i', $end, config('app.timezone'))
                    ->lessThanOrEqualTo(Carbon::createFromFormat('!Y-m-d\TH:i', $start, config('app.timezone')))) {
                $validator->errors()->add('promotion_ends_at', 'O término da promoção deve ser posterior ao início.');
            }

            $product = $this->route('product');
            if ($product instanceof Product && filled($this->input('sku')) && $this->input('sku') !== $product->sku) {
                $validator->errors()->add('sku', 'O SKU não pode ser alterado depois da criação.');
            }
        });
    }

    protected function slugRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && trim($value) !== '' && ! app(ProductService::class)->slugIsAvailable($value)) {
                $fail('Este slug já está em uso.');
            }
        };
    }

    protected function validMedia(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }
            $media = Media::query()->find($value);
            if ($media === null || $media->disk !== MediaService::DISK
                || ! in_array($media->mime_type, app(MediaService::class)->supportedMimeTypes(), true)
                || ! Storage::disk($media->disk)->exists($media->path)) {
                $fail('Selecione uma imagem acessível da biblioteca de mídia.');
            }
        };
    }

    public function attributes(): array
    {
        return [
            'category_id' => 'categoria', 'main_media_id' => 'imagem principal', 'name' => 'nome',
            'slug' => 'endereço', 'sku' => 'SKU', 'description' => 'descrição',
            'cost_price' => 'preço de custo', 'target_margin' => 'margem desejada',
            'price' => 'preço', 'promotional_price' => 'preço promocional',
            'promotion_starts_at' => 'início da promoção', 'promotion_ends_at' => 'término da promoção',
        ];
    }

    private function validMoney(string $value): bool
    {
        return preg_match('/^\d{1,10}(?:[,.]\d{1,2})?$/', $value) === 1;
    }

    private function cents(string $value): int
    {
        [$whole, $fraction] = array_pad(preg_split('/[,.]/', $value, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private function validDateTime(string $value): bool
    {
        $date = Carbon::createFromFormat('!Y-m-d\TH:i', $value, config('app.timezone'));

        return $date !== false && $date->format('Y-m-d\TH:i') === $value;
    }
}
