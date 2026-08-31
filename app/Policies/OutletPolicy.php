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
        $tenant = $this->context->tenant();

        if ($tenant === null || $tenant->getKey() !== $outlet->tenant_id) {
            return false;
        }

        if ($tenant->membership->is_owner === true) {
            return true;
        }

        return $outlet->is_active
            && $user->assignedOutletsFor($tenant)->whereKey($outlet->getKey())->exists();
    }

    public function create(User $user): bool
    {
        return $this->context->tenant() !== null && $user->can('outlet.manage');
    }

    public function update(User $user, Outlet $outlet): bool
    {
        return $this->view($user, $outlet) && $user->can('outlet.manage');
    }

    public function manageTax(User $user, Outlet $outlet): bool
    {
        return $this->view($user, $outlet) && $user->can('tax.manage');
    }

    public function delete(User $user, Outlet $outlet): bool
    {
        return $this->update($user, $outlet);
    }
}
