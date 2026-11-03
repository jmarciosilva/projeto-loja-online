<?php

namespace App\Http\Requests\Admin;

use App\Models\Category;
use App\Services\CategoryService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('category')) ?? false;
    }

    public function rules(): array
    {
        /** @var Category $category */
        $category = $this->route('category');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('categories', 'slug')->ignore($category->id)->whereNull('deleted_at'), $this->slugRule($category->id)],
            'description' => ['nullable', 'string'],
            'parent_category_id' => ['nullable', 'integer', function (string $attribute, mixed $value, Closure $fail) use ($category): void {
                if ($value !== null && ((int) $value === $category->id
                    || ! Category::query()->whereKey($value)->whereNull('parent_category_id')->exists()
                    || $category->children()->exists())) {
                    $fail('Selecione uma categoria principal válida. Categorias com subcategorias não podem ser movidas.');
                }
            }],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:1'],
        ];
    }

    private function slugRule(int $ignoreId): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($ignoreId): void {
            if (is_string($value) && trim($value) !== '' && ! app(CategoryService::class)->slugIsAvailable($value, $ignoreId)) {
                $fail('Este slug já está em uso.');
            }
        };
    }
}
