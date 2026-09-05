<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Models\DiningTable;
use App\Models\Outlet;
use App\Models\Subscription;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class SubscriptionEntitlementService
{
    public const LIMIT_OUTLETS = 'outlets';

    public const LIMIT_ACTIVE_TABLES = 'active_tables';

    public const LIMIT_STAFF = 'staff';

    public const FEATURE_MENU = 'menu';

    public const FEATURE_QR_ORDERING = 'qr_ordering';

    public const FEATURE_ONLINE_PAYMENT = 'online_payment';

    /** @return list<string> */
    private function limitKeys(): array
    {
        return [self::LIMIT_OUTLETS, self::LIMIT_ACTIVE_TABLES, self::LIMIT_STAFF];
    }

    public function current(Tenant|int $tenant): ?Subscription
    {
        $tenantId = $tenant instanceof Tenant ? (int) $tenant->getKey() : $tenant;

        return Subscription::withoutGlobalScopes()
            ->with('plan')
            ->where('tenant_id', $tenantId)
            ->latest('id')
            ->first();
    }

    public function canAcceptOrders(Tenant $tenant): bool
    {
        if ($tenant->status !== TenantStatus::Active) {
            return false;
        }

        $subscription = $this->current($tenant);

        if ($subscription === null || ! $subscription->status->allowsOrders()) {
            return false;
        }

        $now = CarbonImmutable::now();

        if ($subscription->status === SubscriptionStatus::Trialing
            && ($subscription->trial_ends_at === null || $subscription->trial_ends_at->lessThanOrEqualTo($now))) {
            return false;
        }

        return $subscription->current_period_ends_at === null
            || $subscription->current_period_ends_at->greaterThan($now);
    }

    public function hasFeature(Tenant|int $tenant, string $feature): bool
    {
        return $this->current($tenant)?->plan?->hasFeature($feature) ?? false;
    }

    /** @return array{outlets: int, active_tables: int, staff: int} */
    public function usage(Tenant|int $tenant): array
    {
        $tenantId = $tenant instanceof Tenant ? (int) $tenant->getKey() : $tenant;

        return [
            self::LIMIT_OUTLETS => Outlet::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->count(),
            self::LIMIT_ACTIVE_TABLES => DiningTable::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->count(),
            self::LIMIT_STAFF => (int) DB::table('tenant_user')
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->count(),
        ];
    }

    public function canAdd(Tenant $tenant, string $limitKey, int $additional = 1): bool
    {
        if (! in_array($limitKey, $this->limitKeys(), true) || $additional < 1) {
            return false;
        }

        if (! $this->canAcceptOrders($tenant)) {
            return false;
        }

        $subscription = $this->current($tenant);
        $limit = $subscription?->plan?->limit($limitKey);

        if ($subscription === null || $subscription->plan === null) {
            return false;
        }

        if ($limit === null || $limit < 0) {
            return true;
        }

        return ($this->usage($tenant)[$limitKey] ?? 0) + $additional <= $limit;
    }

    public function limitMessage(Tenant $tenant, string $limitKey): string
    {
        $label = match ($limitKey) {
            self::LIMIT_OUTLETS => 'outlet',
            self::LIMIT_ACTIVE_TABLES => 'meja aktif',
            self::LIMIT_STAFF => 'staf',
            default => 'resource',
        };

        if (! $this->canAcceptOrders($tenant)) {
            return 'Subscription tenant belum aktif atau sudah berakhir.';
        }

        $subscription = $this->current($tenant);
        $limit = $subscription?->plan?->limit($limitKey);

        if ($subscription === null || $subscription->plan === null) {
            return 'Workspace belum memiliki subscription yang dapat digunakan.';
        }

        if ($limit === null || $limit < 0) {
            return 'Resource tidak dapat ditambahkan pada subscription ini.';
        }

        return "Batas {$label} pada plan {$subscription->plan->name} adalah {$limit}.";
    }

    /** @return array<string, mixed> */
    public function summary(Tenant $tenant): array
    {
        $subscription = $this->current($tenant);
        $plan = $subscription?->plan;

        return [
            'status' => $subscription?->status->value,
            'trial_ends_at' => $subscription?->trial_ends_at?->toIso8601String(),
            'current_period_ends_at' => $subscription?->current_period_ends_at?->toIso8601String(),
            'plan' => $plan === null ? null : [
                'code' => $plan->code,
                'name' => $plan->name,
                'price' => $plan->price,
                'currency' => $plan->currency,
                'billing_interval' => $plan->billing_interval,
                'limits' => $plan->limits,
                'features' => $plan->features,
            ],
            'usage' => $this->usage($tenant),
            'can_accept_orders' => $this->canAcceptOrders($tenant),
        ];
    }
}
