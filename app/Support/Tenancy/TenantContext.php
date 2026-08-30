<?php

namespace App\Support\Tenancy;

use App\Models\Outlet;
use App\Models\Tenant;
use LogicException;

class TenantContext
{
    private ?Tenant $tenant = null;

    private ?Outlet $outlet = null;

    private bool $resolved = false;

    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function tenantId(): ?int
    {
        return $this->tenant?->getKey();
    }

    public function tenantOrFail(): Tenant
    {
        return $this->tenant ?? throw new LogicException('An active tenant context is required.');
    }

    public function outlet(): ?Outlet
    {
        return $this->outlet;
    }

    public function outletOrFail(): Outlet
    {
        return $this->outlet ?? throw new LogicException('An active outlet context is required.');
    }

    public function outletId(): ?int
    {
        return $this->outlet?->getKey();
    }

    public function setTenant(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
        $this->outlet = null;
    }

    public function setOutlet(?Outlet $outlet): void
    {
        if ($outlet !== null && $outlet->tenant_id !== $this->tenantId()) {
            throw new LogicException('Outlet context must belong to the active tenant.');
        }

        $this->outlet = $outlet;
    }

    public function markResolved(): void
    {
        $this->resolved = true;
    }

    public function isResolved(): bool
    {
        return $this->resolved;
    }

    public function clear(): void
    {
        $this->tenant = null;
        $this->outlet = null;
        $this->resolved = false;
    }
}
