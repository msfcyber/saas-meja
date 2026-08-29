<?php

namespace App\Policies;

use App\Models\Outlet;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

class OutletPolicy
{
    public function __construct(private readonly TenantContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->context->tenant() !== null;
    }

    public function view(User $user, Outlet $outlet): bool
    {
        return $this->context->tenantId() === $outlet->tenant_id;
    }

    public function create(User $user): bool
    {
        return $this->context->tenant() !== null && $user->can('outlet.manage');
    }

    public function update(User $user, Outlet $outlet): bool
    {
        return $this->view($user, $outlet) && $user->can('outlet.manage');
    }

    public function delete(User $user, Outlet $outlet): bool
    {
        return $this->update($user, $outlet);
    }
}
