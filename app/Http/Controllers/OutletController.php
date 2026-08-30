<?php

namespace App\Http\Controllers;

use App\Http\Requests\Outlets\StoreOutletRequest;
use App\Http\Requests\Outlets\UpdateOutletRequest;
use App\Http\Requests\Outlets\UpdateOutletTaxSettingsRequest;
use App\Models\Outlet;
use App\Models\TaxSetting;
use App\Models\Tenant;
use App\Services\AuditLogService;
use App\Services\SubscriptionEntitlementService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        Request $request,
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
        $outlets = ($tenant->membership?->is_owner === true
            ? Outlet::query()
            : $request->user()->assignedOutletsFor($tenant)
                ->where('outlets.is_active', true))
            ->orderByDesc('outlets.is_active')
            ->orderBy('outlets.name')
            ->get();
        $taxSettings = TaxSetting::withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->whereIn('outlet_id', $outlets->modelKeys())
            ->get()
            ->keyBy('outlet_id');

        return Inertia::render('outlets', [
            'outlets' => $outlets->map(function (Outlet $outlet) use ($productCounts, $tableCounts, $taxSettings): array {
                /** @var TaxSetting|null $taxSetting */
                $taxSetting = $taxSettings->get($outlet->id);

                return [
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
                    'tax_settings' => $this->taxDisplayValues($taxSetting),
                ];
            })->values(),
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
            'can_manage_tax' => $request->user()?->can('tax.manage') ?? false,
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

    public function updateTaxSettings(
        UpdateOutletTaxSettingsRequest $request,
        Outlet $outlet,
        TenantContext $context,
        AuditLogService $audits,
    ): RedirectResponse {
        $this->authorize('manageTax', $outlet);
        $tenant = $context->tenantOrFail();
        $attributes = $request->validated();
        $enabled = (bool) $attributes['tax_enabled'];
        $values = $enabled
            ? [
                'is_enabled' => true,
                'name' => (string) $attributes['tax_name'],
                'rate_basis_points' => $this->percentToBasisPoints((string) $attributes['tax_rate']),
                'is_inclusive' => (bool) $attributes['tax_inclusive'],
            ]
            : [
                'is_enabled' => false,
                'name' => null,
                'rate_basis_points' => 0,
                'is_inclusive' => false,
            ];

        DB::transaction(function () use ($tenant, $outlet, $values, $audits): void {
            $taxSetting = TaxSetting::withoutGlobalScopes()
                ->where('tenant_id', $tenant->getKey())
                ->where('outlet_id', $outlet->getKey())
                ->lockForUpdate()
                ->first();
            $oldValues = $taxSetting === null
                ? [
                    'is_enabled' => false,
                    'name' => null,
                    'rate_basis_points' => 0,
                    'is_inclusive' => false,
                ]
                : $this->taxAuditValues($taxSetting);

            if ($taxSetting === null) {
                $taxSetting = new TaxSetting([
                    'tenant_id' => $tenant->getKey(),
                    'outlet_id' => $outlet->getKey(),
                ]);
            }

            $taxSetting->fill($values);
            $taxSetting->save();

            $audits->record('tax_setting.updated', [
                'tenant_id' => (int) $tenant->getKey(),
                'outlet_id' => (int) $outlet->getKey(),
                'auditable_type' => TaxSetting::class,
                'auditable_id' => (int) $taxSetting->getKey(),
                'old_values' => $oldValues,
                'new_values' => $this->taxAuditValues($taxSetting),
            ]);
        }, attempts: 3);

        return to_route('outlets')->with('success', "Pengaturan pajak outlet {$outlet->name} berhasil diperbarui.");
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

    /** @return array{tax_enabled: bool, tax_name: string|null, tax_rate: string|null, tax_inclusive: bool} */
    private function taxDisplayValues(?TaxSetting $taxSetting): array
    {
        $enabled = $taxSetting?->is_enabled === true;

        return [
            'tax_enabled' => $enabled,
            'tax_name' => $enabled ? $taxSetting->name : null,
            'tax_rate' => $enabled ? $this->basisPointsToPercent((int) $taxSetting->rate_basis_points) : null,
            'tax_inclusive' => $enabled && $taxSetting->is_inclusive === true,
        ];
    }

    private function percentToBasisPoints(string $rate): int
    {
        [$whole, $fraction] = array_pad(explode('.', $rate, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private function basisPointsToPercent(int $basisPoints): string
    {
        $whole = intdiv($basisPoints, 100);
        $fraction = $basisPoints % 100;

        if ($fraction === 0) {
            return (string) $whole;
        }

        return $whole.'.'.rtrim(str_pad((string) $fraction, 2, '0', STR_PAD_LEFT), '0');
    }

    /** @return array{is_enabled: bool, name: string|null, rate_basis_points: int, is_inclusive: bool} */
    private function taxAuditValues(TaxSetting $taxSetting): array
    {
        return [
            'is_enabled' => (bool) $taxSetting->is_enabled,
            'name' => $taxSetting->name,
            'rate_basis_points' => (int) $taxSetting->rate_basis_points,
            'is_inclusive' => (bool) $taxSetting->is_inclusive,
        ];
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
