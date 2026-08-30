<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;

final class SubscriptionService
{
    public function __construct(private readonly AuditLogService $audits) {}

    public function provisionTrial(Tenant $tenant): Subscription
    {
        $existing = Subscription::withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->latest('id')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $startedAt = now();

        $subscription = Subscription::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'plan_id' => $this->defaultPlan()->getKey(),
            'status' => SubscriptionStatus::Trialing,
            'started_at' => $startedAt,
            'trial_starts_at' => $startedAt,
            'trial_ends_at' => $startedAt->addDays((int) config('subscriptions.trial_days', 14)),
            'metadata' => ['source' => 'trial'],
        ]);

        $this->audits->record('subscription.created', [
            'tenant_id' => (int) $subscription->tenant_id,
            'actor_type' => 'system',
            'auditable_type' => Subscription::class,
            'auditable_id' => (int) $subscription->getKey(),
            'new_values' => [
                'status' => $subscription->status->value,
                'plan_id' => (int) $subscription->plan_id,
                'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
            ],
        ]);

        return $subscription;
    }

    public function defaultPlan(): Plan
    {
        $defaults = config('subscriptions.default_plan', []);
        $defaults = is_array($defaults) ? $defaults : [];
        $code = (string) ($defaults['code'] ?? 'tumbuh');

        return Plan::query()->firstOrCreate(
            ['code' => $code],
            [
                'name' => (string) ($defaults['name'] ?? 'Tumbuh'),
                'description' => (string) ($defaults['description'] ?? 'Plan awal untuk bisnis F&B yang sedang bertumbuh.'),
                'price' => (int) ($defaults['price'] ?? 299000),
                'currency' => (string) ($defaults['currency'] ?? 'IDR'),
                'billing_interval' => (string) ($defaults['billing_interval'] ?? 'monthly'),
                'limits' => is_array($defaults['limits'] ?? null) ? $defaults['limits'] : [],
                'features' => is_array($defaults['features'] ?? null) ? $defaults['features'] : [],
                'is_active' => true,
                'position' => 0,
            ],
        );
    }
}
