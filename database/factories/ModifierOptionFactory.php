<?php

namespace Database\Factories;

use App\Models\Modifier;
use App\Models\ModifierOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ModifierOption> */
class ModifierOptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'modifier_id' => Modifier::factory(),
            'name' => fake()->unique()->word(),
            'price_delta' => fake()->randomElement([0, 3000, 5000, 8000]),
            'is_active' => true,
            'position' => fake()->numberBetween(0, 10),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (ModifierOption $option) {
            $modifier = Modifier::query()->findOrFail($option->modifier_id);
            $option->tenant_id = $modifier->tenant_id;
            $option->outlet_id = $modifier->outlet_id;
        });
    }
}
