<?php

namespace App\Actions\Tenancy;

use App\Enums\TenantStatus;
use App\Models\Outlet;
use App\Models\TaxSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateOwnerWorkspace
{
    public function __construct(private readonly ProvisionTenantRoles $roles) {}

    /**
     * @param  array{business_name: string, outlet_name: string, address: string|null, phone: string|null, timezone: string, tax_enabled: bool, tax_name: string|null, tax_rate: float|null, tax_inclusive: bool}  $attributes
     * @return array{tenant: Tenant, outlet: Outlet}
     */
    public function handle(User $owner, array $attributes): array
    {
        return DB::transaction(function () use ($owner, $attributes) {
            $tenant = Tenant::query()->create([
                'name' => $attributes['business_name'],
                'slug' => $this->uniqueTenantSlug($attributes['business_name']),
                'status' => TenantStatus::Active,
                'timezone' => $attributes['timezone'],
            ]);

            $tenant->users()->attach($owner, [
                'status' => 'active',
                'is_owner' => true,
                'joined_at' => now(),
            ]);

            $this->roles->handle($tenant, $owner);

            $outlet = $tenant->outlets()->create([
                'name' => $attributes['outlet_name'],
                'slug' => Str::slug($attributes['outlet_name']) ?: 'outlet-utama',
                'code' => 'OUT-001',
                'address' => $attributes['address'],
                'phone' => $attributes['phone'],
                'timezone' => $attributes['timezone'],
                'currency' => 'IDR',
                'is_active' => true,
                'accepts_orders' => true,
            ]);

            TaxSetting::query()->create([
                'tenant_id' => $tenant->getKey(),
                'outlet_id' => $outlet->getKey(),
                'is_enabled' => $attributes['tax_enabled'],
                'name' => $attributes['tax_enabled'] ? $attributes['tax_name'] : null,
                'rate_basis_points' => $attributes['tax_enabled']
                    ? (int) round($attributes['tax_rate'] * 100)
                    : 0,
                'is_inclusive' => $attributes['tax_enabled'] && $attributes['tax_inclusive'],
            ]);

            return ['tenant' => $tenant, 'outlet' => $outlet];
        }, attempts: 3);
    }

    private function uniqueTenantSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'bisnis';
        $slug = $base;
        $suffix = 2;

        while (Tenant::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
