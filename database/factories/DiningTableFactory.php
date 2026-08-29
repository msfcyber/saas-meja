<?php

namespace Database\Factories;

use App\Models\DiningTable;
use App\Models\Outlet;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DiningTable> */
class DiningTableFactory extends Factory
{
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1, 999);

        return [
            'outlet_id' => Outlet::factory(),
            'name' => 'Meja '.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
            'code' => 'TBL-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
            'zone' => fake()->randomElement(['Indoor', 'Teras', 'Lantai 2']),
            'capacity' => fake()->randomElement([2, 4, 6]),
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (DiningTable $table) {
            $outlet = Outlet::query()->findOrFail($table->outlet_id);
            $table->tenant_id = $outlet->tenant_id;
        });
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
