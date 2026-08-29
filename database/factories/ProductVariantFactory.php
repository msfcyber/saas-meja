<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductVariant> */
class ProductVariantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'name' => fake()->unique()->randomElement(['Regular', 'Large', 'Hot', 'Iced']).' '.fake()->randomNumber(3),
            'price_delta' => fake()->randomElement([0, 3000, 5000, 8000]),
            'is_default' => false,
            'is_active' => true,
            'position' => fake()->numberBetween(0, 10),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (ProductVariant $variant) {
            $product = Product::query()->findOrFail($variant->product_id);
            $variant->tenant_id = $product->tenant_id;
            $variant->outlet_id = $product->outlet_id;
        });
    }

    public function asDefault(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }
}
