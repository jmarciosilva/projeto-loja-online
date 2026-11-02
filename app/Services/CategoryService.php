<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CategoryService
{
    public function publicCategories(): Collection
    {
        if (! Schema::hasTable('categories')) {
            return new Collection;
        }

        return Category::query()->publiclyVisible()->ordered()->get();
    }

    public function findPublicBySlug(string $slug): ?Category
    {
        if (! Schema::hasTable('categories')) {
            return null;
        }

        return Category::query()->publiclyVisible()->where('slug', $slug)->first();
    }

    public function adminOptions(): Collection
    {
        return Category::query()->ordered()->get();
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Category::query()
            ->withCount('products')
            ->ordered()
            ->paginate($perPage);
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Category
    {
        $name = $this->requiredString($attributes, 'name');
        $slug = $this->resolveSlug($attributes['slug'] ?? null, $name);

        return Category::query()->create([
            'name' => $name,
            'slug' => $slug,
            'description' => $this->nullableString($attributes['description'] ?? null),
            'is_active' => $this->boolean($attributes['is_active'] ?? false),
            'sort_order' => isset($attributes['sort_order']) ? (int) $attributes['sort_order'] : $this->nextOrder(),
        ]);
    }

    /** @param array<string, mixed> $attributes */
    public function update(Category $category, array $attributes): Category
    {
        $payload = [];
        if (array_key_exists('name', $attributes)) {
            $payload['name'] = $this->requiredString($attributes, 'name');
        }
        if (array_key_exists('slug', $attributes) && filled($attributes['slug'])) {
            $payload['slug'] = $this->resolveSlug($attributes['slug'], $payload['name'] ?? $category->name, $category->id);
        }
        foreach (['description', 'is_active', 'sort_order'] as $field) {
            if (array_key_exists($field, $attributes)) {
                $payload[$field] = match ($field) {
                    'description' => $this->nullableString($attributes[$field]),
                    'is_active' => $this->boolean($attributes[$field]),
                    default => (int) $attributes[$field],
                };
            }
        }
        $category->fill($payload)->save();

        return $category;
    }

    public function delete(Category $category): void
    {
        $category->delete();
    }

    public function slugIsAvailable(string $slug, ?int $ignoreId = null): bool
    {
        return ! Category::withTrashed()->where('slug', $slug)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }

    private function resolveSlug(mixed $slug, string $name, ?int $ignoreId = null): string
    {
        $explicit = is_string($slug) && trim($slug) !== '';
        $candidate = $explicit ? trim($slug) : Str::slug($name);
        if ($candidate === '' || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $candidate) !== 1) {
            throw new InvalidArgumentException('O slug da categoria é inválido.');
        }
        if (strlen($candidate) > 255) {
            throw new InvalidArgumentException('O slug da categoria excede 255 caracteres.');
        }
        if (! $explicit) {
            $base = $candidate;
            $suffix = 2;
            while (! $this->slugIsAvailable($candidate, $ignoreId)) {
                $candidate = $base.'-'.$suffix++;
            }
        } elseif (! $this->slugIsAvailable($candidate, $ignoreId)) {
            throw new InvalidArgumentException('Este slug já está em uso.');
        }

        return $candidate;
    }

    private function nextOrder(): int
    {
        return ((int) Category::query()->max('sort_order')) + 1;
    }

    private function requiredString(array $attributes, string $field): string
    {
        $value = is_string($attributes[$field] ?? null) ? trim($attributes[$field]) : '';
        if ($value === '') {
            throw new InvalidArgumentException("O campo {$field} é obrigatório.");
        }

        return $value;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function boolean(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'on'], true);
    }
}
