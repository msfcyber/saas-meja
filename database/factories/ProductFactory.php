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
        $name = rtrim(fake()->unique()->sentence(3), '.');

        return [
            'category_id' => Category::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'description' => fake()->sentence(12),
            'image_path' => null,
            'base_price' => fake()->numberBetween(18, 95) * 1000,
            'is_active' => true,
            'is_available' => true,
            'is_featured' => false,
            'position' => fake()->numberBetween(0, 50),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Product $product) {
            $category = Category::query()->findOrFail($product->category_id);
            $product->tenant_id = $category->tenant_id;
            $product->outlet_id = $category->outlet_id;
        });
    }

    public function unavailable(): static
    {
        return $this->state(fn () => ['is_available' => false]);
    }

    public function featured(): static
    {
        return $this->state(fn () => ['is_featured' => true]);
    }
}
