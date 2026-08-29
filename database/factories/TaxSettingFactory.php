<?php

namespace Database\Factories;

use App\Models\Outlet;
use App\Models\TaxSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TaxSetting> */
class TaxSettingFactory extends Factory
{
    protected $model = TaxSetting::class;

    public function definition(): array
    {
        return [
            'outlet_id' => Outlet::factory(),
            'is_enabled' => false,
            'name' => null,
            'rate_basis_points' => 0,
            'is_inclusive' => false,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (TaxSetting $taxSetting) {
            $outlet = Outlet::query()->findOrFail($taxSetting->outlet_id);
            $taxSetting->tenant_id = $outlet->tenant_id;
        });
    }

    public function enabled(): static
    {
        return $this->state(fn () => [
            'is_enabled' => true,
            'name' => 'Pajak Restoran',
            'rate_basis_points' => 1000,
        ]);
    }
}
