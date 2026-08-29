<?php

namespace App\Http\Middleware;

use App\Models\Outlet;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $context = app(TenantContext::class);

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'tenancy' => fn () => [
                'tenant' => $context->tenant()?->only(['id', 'name', 'slug']),
                'outlet' => $context->outlet()?->only(['id', 'name', 'code']),
                'tenants' => $request->user()?->tenants()
                    ->wherePivot('status', 'active')
                    ->orderBy('name')
                    ->get(['tenants.id', 'tenants.name', 'tenants.slug'])
                    ->toArray() ?? [],
                'outlets' => $context->tenant() === null
                    ? []
                    : Outlet::query()
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->get(['id', 'name', 'code'])
                        ->toArray(),
                'roles' => $request->user()?->getRoleNames()->values()->all() ?? [],
                'permissions' => $request->user()?->getAllPermissions()->pluck('name')->values()->all() ?? [],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
