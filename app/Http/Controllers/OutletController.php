<?php

namespace App\Http\Controllers;

use App\Http\Requests\Outlets\StoreOutletRequest;
use App\Http\Requests\Outlets\UpdateOutletRequest;
use App\Models\Outlet;
use App\Models\TaxSetting;
use App\Models\Tenant;
use App\Services\AuditLogService;
use App\Services\SubscriptionEntitlementService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OutletController extends Controller
{
    /** @var list<string> */
    private const TIMEZONES = ['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura'];

    public function index(
        TenantContext $context,
        SubscriptionEntitlementService $entitlements,
    ): Response {
        $this->authorize('viewAny', Outlet::class);
        $tenant = $context->tenantOrFail();
        $usage = $entitlements->usage($tenant)['outlets'];
        $subscription = $entitlements->current($tenant);
        $limit = $subscription?->plan?->limit(SubscriptionEntitlementService::LIMIT_OUTLETS);
        $canAdd = $entitlements->canAdd($tenant, SubscriptionEntitlementService::LIMIT_OUTLETS);
        $productCounts = DB::table('products')
            ->where('tenant_id', $tenant->getKey())
            ->select('outlet_id', DB::raw('count(*) as aggregate'))
            ->groupBy('outlet_id')
            ->pluck('aggregate', 'outlet_id');
        $tableCounts = DB::table('tables')
            ->where('tenant_id', $tenant->getKey())
            ->select('outlet_id', DB::raw('count(*) as aggregate'))
            ->groupBy('outlet_id')
            ->pluck('aggregate', 'outlet_id');

        return Inertia::render('outlets', [
            'outlets' => Outlet::query()
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get()
                ->map(fn (Outlet $outlet): array => [
                    'id' => $outlet->id,
                    'name' => $outlet->name,
                    'code' => $outlet->code,
                    'slug' => $outlet->slug,
                    'address' => $outlet->address,
                    'phone' => $outlet->phone,
                    'timezone' => $outlet->timezone,
                    'currency' => $outlet->currency,
                    'is_active' => $outlet->is_active,
                    'accepts_orders' => $outlet->accepts_orders,
                    'products_count' => (int) $productCounts->get($outlet->id, 0),
                    'tables_count' => (int) $tableCounts->get($outlet->id, 0),
                ])->values(),
            'timezones' => collect(self::TIMEZONES)->map(fn (string $timezone): array => [
                'value' => $timezone,
                'label' => match ($timezone) {
                    'Asia/Jakarta' => 'WIB - Jakarta',
                    'Asia/Makassar' => 'WITA - Makassar',
                    default => 'WIT - Jayapura',
                },
            ])->values(),
            'usage' => [
                'current' => $usage,
                'limit' => $limit,
            ],
            'can_add' => $canAdd,
            'limit_message' => $canAdd
                ? null
                : $entitlements->limitMessage($tenant, SubscriptionEntitlementService::LIMIT_OUTLETS),
        ]);
    }

    public function store(
        StoreOutletRequest $request,
        TenantContext $context,
        SubscriptionEntitlementService $entitlements,
        AuditLogService $audits,
    ): RedirectResponse {
        $this->authorize('create', Outlet::class);
        $tenant = $context->tenantOrFail();

        if ($request->boolean('is_active')) {
            $this->ensureCanAdd($tenant, $entitlements);
        }

        $attributes = $request->validated();
        $outlet = DB::transaction(function () use ($attributes, $tenant, $audits): Outlet {
            $outlet = Outlet::query()->create([
                ...$attributes,
                'tenant_id' => $tenant->getKey(),
                'slug' => $this->uniqueSlug($attributes['name']),
            ]);

            TaxSetting::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->getKey(),
                'outlet_id' => $outlet->getKey(),
                'is_enabled' => false,
                'name' => null,
                'rate_basis_points' => 0,
                'is_inclusive' => false,
            ]);

            $audits->record('outlet.created', [
                'tenant_id' => (int) $tenant->getKey(),
                'outlet_id' => (int) $outlet->getKey(),
                'auditable_type' => Outlet::class,
                'auditable_id' => (int) $outlet->getKey(),
                'new_values' => $this->auditValues($outlet),
            ]);

            return $outlet;
        }, attempts: 3);

        return to_route('outlets')->with('success', "Outlet {$outlet->name} berhasil ditambahkan.");
    }

    public function update(
        UpdateOutletRequest $request,
        Outlet $outlet,
        TenantContext $context,
        SubscriptionEntitlementService $entitlements,
        AuditLogService $audits,
    ): RedirectResponse {
        $this->authorize('update', $outlet);
        $tenant = $context->tenantOrFail();
        $attributes = $request->validated();

        if (($attributes['is_active'] ?? $outlet->is_active) && ! $outlet->is_active) {
            $this->ensureCanAdd($tenant, $entitlements);
        }

        if ($outlet->is_active && array_key_exists('is_active', $attributes) && ! $attributes['is_active']
            && Outlet::query()->where('is_active', true)->count() <= 1) {
            throw ValidationException::withMessages([
                'is_active' => 'Minimal satu outlet aktif harus dipertahankan.',
            ]);
        }

        $oldValues = $this->auditValues($outlet);
        $updateAttributes = $attributes;

        if (array_key_exists('name', $attributes)) {
            $updateAttributes['slug'] = $this->uniqueSlug((string) $attributes['name'], $outlet);
        }

        $outlet->update($updateAttributes);
        $outlet->refresh();

        $audits->record('outlet.updated', [
            'tenant_id' => (int) $tenant->getKey(),
            'outlet_id' => (int) $outlet->getKey(),
            'auditable_type' => Outlet::class,
            'auditable_id' => (int) $outlet->getKey(),
            'old_values' => $oldValues,
            'new_values' => $this->auditValues($outlet),
        ]);

        return to_route('outlets')->with('success', "Outlet {$outlet->name} berhasil diperbarui.");
    }

    private function ensureCanAdd(
        Tenant $tenant,
        SubscriptionEntitlementService $entitlements,
    ): void {
        if (! $entitlements->canAdd($tenant, SubscriptionEntitlementService::LIMIT_OUTLETS)) {
            throw ValidationException::withMessages([
                'subscription' => $entitlements->limitMessage($tenant, SubscriptionEntitlementService::LIMIT_OUTLETS),
            ]);
        }
    }

    private function uniqueSlug(string $name, ?Outlet $ignore = null): string
    {
        $base = Str::slug($name) ?: 'outlet';
        $slug = $base;
        $suffix = 2;

        while (Outlet::query()
            ->where('slug', $slug)
            ->when($ignore !== null, fn ($query) => $query->where('id', '!=', $ignore->getKey()))
            ->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /** @return array<string, bool|int|string|null> */
    private function auditValues(Outlet $outlet): array
    {
        return Arr::only($outlet->getAttributes(), [
            'name',
            'slug',
            'code',
            'address',
            'phone',
            'timezone',
            'currency',
            'is_active',
            'accepts_orders',
        ]);
    }
}
