<?php

namespace App\Services;

use App\Enums\TenantStatus;
use App\Models\DiningTable;
use App\Models\Outlet;
use App\Models\TableQrToken;
use App\Models\Tenant;
use App\Support\PublicTableAccess;
use Carbon\CarbonImmutable;

final class PublicTableAccessService
{
    public function __construct(private readonly SubscriptionEntitlementService $entitlements) {}

    public function resolve(string $plainToken): ?PublicTableAccess
    {
        if (strlen($plainToken) !== 64 || ! ctype_xdigit($plainToken)) {
            return null;
        }

        $token = TableQrToken::withoutGlobalScopes()
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();

        if ($token === null || $token->revoked_at !== null || ($token->expires_at !== null && CarbonImmutable::parse((string) $token->expires_at)->isPast())) {
            return null;
        }

        $tenant = Tenant::query()
            ->whereKey($token->tenant_id)
            ->where('status', TenantStatus::Active)
            ->first();

        if ($tenant === null) {
            return null;
        }

        if (! $this->entitlements->canAcceptOrders($tenant)) {
            return null;
        }

        $outlet = Outlet::withoutGlobalScopes()
            ->whereKey($token->outlet_id)
            ->where('tenant_id', $token->tenant_id)
            ->where('is_active', true)
            ->where('accepts_orders', true)
            ->first();

        if ($outlet === null) {
            return null;
        }

        $table = DiningTable::withoutGlobalScopes()
            ->whereKey($token->table_id)
            ->where('tenant_id', $token->tenant_id)
            ->where('outlet_id', $token->outlet_id)
            ->where('is_active', true)
            ->first();

        if ($table === null) {
            return null;
        }

        $token->update(['last_used_at' => now()]);

        return new PublicTableAccess($plainToken, $token, $tenant, $outlet, $table);
    }
}
