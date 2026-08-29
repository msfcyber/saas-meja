<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Outlet;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Category> */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['Makanan Utama', 'Camilan', 'Minuman', 'Pencuci Mulut']).' '.fake()->randomNumber(3);

        return [
            'outlet_id' => Outlet::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'position' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Category $category) {
            $outlet = Outlet::query()->findOrFail($category->outlet_id);
            $category->tenant_id = $outlet->tenant_id;
        });
    }
}
