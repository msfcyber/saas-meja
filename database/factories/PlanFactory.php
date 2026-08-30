<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Plan> */
class PlanFactory extends Factory
{
    public function definition(): array
    {
        $code = fake()->unique()->slug(2);

        return [
            'code' => $code,
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'price' => 299000,
            'currency' => 'IDR',
            'billing_interval' => 'monthly',
            'limits' => [
                'outlets' => 3,
                'active_tables' => 100,
                'staff' => 10,
            ],
            'features' => ['menu', 'qr_ordering'],
            'is_active' => true,
            'position' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
