<?php

namespace Database\Factories;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Services\SubscriptionService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Tenant> */
class TenantFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->randomNumber(5, true),
            'status' => TenantStatus::Active,
            'timezone' => 'Asia/Jakarta',
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => TenantStatus::Suspended]);
    }

    public function withTrialSubscription(): static
    {
        return $this->afterCreating(function (Tenant $tenant): void {
            app(SubscriptionService::class)->provisionTrial($tenant);
        });
    }
}
