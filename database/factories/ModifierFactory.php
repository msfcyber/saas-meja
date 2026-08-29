<?php

namespace Database\Factories;

use App\Enums\ModifierSelectionType;
use App\Models\Modifier;
use App\Models\Outlet;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Modifier> */
class ModifierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'outlet_id' => Outlet::factory(),
            'name' => fake()->unique()->randomElement(['Level Pedas', 'Pilihan Topping', 'Pilihan Es', 'Tambahan']).' '.fake()->randomNumber(3),
            'selection_type' => ModifierSelectionType::Single,
            'minimum_selections' => 0,
            'maximum_selections' => 1,
            'is_required' => false,
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Modifier $modifier) {
            $outlet = Outlet::query()->findOrFail($modifier->outlet_id);
            $modifier->tenant_id = $outlet->tenant_id;
        });
    }

    public function required(): static
    {
        return $this->state(fn () => ['minimum_selections' => 1, 'is_required' => true]);
    }
}
