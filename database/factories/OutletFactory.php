<?php

namespace Database\Factories;

use App\Models\Outlet;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Outlet> */
class OutletFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company().' '.fake()->city();

        return [
            'tenant_id' => Tenant::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'code' => strtoupper(fake()->unique()->bothify('OUT-###??')),
            'address' => fake()->address(),
            'phone' => fake()->phoneNumber(),
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'is_active' => true,
            'accepts_orders' => true,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => ['accepts_orders' => false]);
    }
}
