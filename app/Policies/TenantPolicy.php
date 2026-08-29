<?php

namespace App\Policies;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

class TenantPolicy
{
    public function __construct(private readonly TenantContext $context) {}

    public function view(User $user, Tenant $tenant): bool
    {
        return $this->hasActiveMembership($user, $tenant);
    }

    public function switch(User $user, Tenant $tenant): bool
    {
        return $tenant->status === TenantStatus::Active
            && $this->hasActiveMembership($user, $tenant);
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $this->context->tenantId() === $tenant->getKey()
            && $user->can('outlet.manage');
    }

    private function hasActiveMembership(User $user, Tenant $tenant): bool
    {
        return $user->tenants()
            ->whereKey($tenant->getKey())
            ->wherePivot('status', 'active')
            ->exists();
    }
}
