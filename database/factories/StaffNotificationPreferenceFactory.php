<?php

namespace Database\Factories;

use App\Models\Outlet;
use App\Models\StaffNotificationPreference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StaffNotificationPreference> */
class StaffNotificationPreferenceFactory extends Factory
{
    protected $model = StaffNotificationPreference::class;

    public function definition(): array
    {
        return [
            'outlet_id' => Outlet::factory(),
            'user_id' => User::factory(),
            'visual_enabled' => true,
            'sound_enabled' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (StaffNotificationPreference $preference) {
            $outlet = Outlet::query()->findOrFail($preference->outlet_id);
            $preference->tenant_id = $outlet->tenant_id;
        });
    }
}
