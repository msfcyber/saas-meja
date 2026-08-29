<?php

namespace App\Http\Middleware;

use App\Models\Outlet;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantContext
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly PermissionRegistrar $permissions,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->context->clear();
        $this->permissions->setPermissionsTeamId(null);

        try {
            $user = $request->user();

            if ($user === null) {
                $this->context->markResolved();

                return $next($request);
            }

            $tenant = $this->resolveTenant($request);
            $this->context->setTenant($tenant);
            $this->context->markResolved();

            if ($tenant === null) {
                return $next($request);
            }

            $this->permissions->setPermissionsTeamId($tenant->getKey());
            $user->unsetRelation('roles')->unsetRelation('permissions');

            $outlet = $this->resolveOutlet($request);
            $this->context->setOutlet($outlet);

            $request->attributes->set('tenant', $tenant);
            $request->attributes->set('outlet', $outlet);
            $request->session()->put('active_tenant_id', $tenant->getKey());

            if ($outlet !== null) {
                $request->session()->put('active_outlet_id', $outlet->getKey());
            } else {
                $request->session()->forget('active_outlet_id');
            }

            return $next($request);
        } finally {
            $this->context->clear();
            $this->permissions->setPermissionsTeamId(null);
        }
    }

    private function resolveTenant(Request $request): ?Tenant
    {
        $query = $request->user()->tenants()
            ->wherePivot('status', 'active')
            ->where('tenants.status', 'active');
        $preferredId = $request->session()->get('active_tenant_id');

        if ($preferredId !== null) {
            $preferred = (clone $query)->whereKey($preferredId)->first();

            if ($preferred !== null) {
                return $preferred;
            }
        }

        return $query->orderBy('tenants.id')->first();
    }

    private function resolveOutlet(Request $request): ?Outlet
    {
        $query = Outlet::query()->where('is_active', true);
        $preferredId = $request->session()->get('active_outlet_id');

        if ($preferredId !== null) {
            $preferred = (clone $query)->whereKey($preferredId)->first();

            if ($preferred !== null) {
                return $preferred;
            }
        }

        return $query->orderBy('id')->first();
    }
}
