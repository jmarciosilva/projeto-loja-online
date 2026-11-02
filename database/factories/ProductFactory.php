<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'category_id' => Category::factory(),
            'main_media_id' => null,
            'name' => $name,
            'slug' => Str::slug($name),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####')),
            'description' => fake()->optional()->paragraph(),
            'price' => '59.90',
            'promotional_price' => null,
            'is_active' => false,
            'is_featured' => false,
            'sort_order' => 1,
        ];
    }
}
