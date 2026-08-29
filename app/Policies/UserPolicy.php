<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Tenancy\TenantContext;

class UserPolicy
{
    public function __construct(private readonly TenantContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->context->tenant() !== null && $user->can('staff.manage');
    }

    public function view(User $user, User $subject): bool
    {
        return $this->viewAny($user) && $this->belongsToCurrentTenant($subject);
    }

    public function update(User $user, User $subject): bool
    {
        return $this->view($user, $subject);
    }

    private function belongsToCurrentTenant(User $user): bool
    {
        $tenantId = $this->context->tenantId();

        return $tenantId !== null && $user->tenants()->whereKey($tenantId)->exists();
    }
}
