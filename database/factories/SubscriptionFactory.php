<?php

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Subscription> */
class SubscriptionFactory extends Factory
{
    public function definition(): array
    {
        $startedAt = now();

        return [
            'tenant_id' => Tenant::factory(),
            'plan_id' => Plan::factory(),
            'status' => SubscriptionStatus::Trialing,
            'provider' => null,
            'provider_reference' => null,
            'started_at' => $startedAt,
            'trial_starts_at' => $startedAt,
            'trial_ends_at' => $startedAt->copy()->addDays(14),
            'current_period_starts_at' => null,
            'current_period_ends_at' => null,
            'cancelled_at' => null,
            'suspended_at' => null,
            'metadata' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::Active,
            'trial_starts_at' => null,
            'trial_ends_at' => null,
            'current_period_starts_at' => now(),
            'current_period_ends_at' => now()->addMonth(),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => SubscriptionStatus::Suspended]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::Expired,
            'trial_ends_at' => now()->subSecond(),
            'current_period_ends_at' => now()->subSecond(),
        ]);
    }
}
