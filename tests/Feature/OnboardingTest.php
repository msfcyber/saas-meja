<?php

use App\Actions\Tenancy\ProvisionTenantRoles;
use App\Enums\TenantStatus;
use App\Models\Outlet;
use App\Models\TaxSetting;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

test('users without a tenant are directed to onboarding from operational routes', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertRedirect(route('onboarding.create'));
});

test('platform admins without a tenant are directed to the platform dashboard', function () {
    $admin = User::factory()->platformAdmin()->create();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertRedirect(route('platform.dashboard'));
});

test('platform admins cannot enter owner onboarding', function () {
    $admin = User::factory()->platformAdmin()->create();

    $this->actingAs($admin)
        ->get(route('onboarding.create'))
        ->assertRedirect(route('platform.dashboard'));
});

test('an authenticated user without a workspace can view onboarding', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('onboarding.create'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('onboarding/create')
        ->has('timezones', 3),
    );
});

test('onboarding provisions an owner workspace, outlet, roles, and tax settings', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('onboarding.store'), [
        'business_name' => 'Warung Pagi',
        'outlet_name' => 'Warung Pagi Tebet',
        'address' => 'Jl. Tebet Raya No. 1',
        'phone' => '+62 812 3456 7890',
        'timezone' => 'Asia/Jakarta',
        'tax_enabled' => true,
        'tax_name' => 'PB1',
        'tax_rate' => '10.5',
        'tax_inclusive' => false,
    ]);

    $tenant = Tenant::query()->where('slug', 'warung-pagi')->firstOrFail();
    $outlet = Outlet::query()->where('tenant_id', $tenant->id)->firstOrFail();
    $taxSetting = TaxSetting::query()->where('outlet_id', $outlet->id)->firstOrFail();

    $response->assertRedirect(route('dashboard'));
    $this->assertDatabaseHas('tenant_user', [
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'status' => 'active',
        'is_owner' => true,
    ]);
    expect($outlet->name)->toBe('Warung Pagi Tebet')
        ->and($outlet->code)->toBe('OUT-001')
        ->and($outlet->timezone)->toBe('Asia/Jakarta')
        ->and($taxSetting->is_enabled)->toBeTrue()
        ->and($taxSetting->name)->toBe('PB1')
        ->and($taxSetting->rate_basis_points)->toBe(1050)
        ->and($taxSetting->is_inclusive)->toBeFalse()
        ->and($this->app[PermissionRegistrar::class]->getPermissionsTeamId())->toBeNull();

    $this->app[PermissionRegistrar::class]->setPermissionsTeamId($tenant->id);
    $user->unsetRelation('roles')->unsetRelation('permissions');

    expect($user->hasRole('owner'))->toBeTrue()
        ->and($user->getAllPermissions()->pluck('name')->sort()->values()->all())
        ->toBe(collect(ProvisionTenantRoles::PERMISSIONS)->sort()->values()->all());
});

test('onboarding stores an inactive tax setting when tax is skipped', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('onboarding.store'), [
        'business_name' => 'Satu Sendok',
        'outlet_name' => 'Satu Sendok Pusat',
        'timezone' => 'Asia/Makassar',
        'tax_enabled' => false,
        'tax_inclusive' => false,
    ]);

    $tenant = Tenant::query()->where('slug', 'satu-sendok')->firstOrFail();
    $outlet = Outlet::query()->where('tenant_id', $tenant->id)->firstOrFail();

    $response->assertRedirect(route('dashboard'));
    expect(TaxSetting::query()->where('outlet_id', $outlet->id)->firstOrFail())
        ->is_enabled->toBeFalse()
        ->name->toBeNull()
        ->rate_basis_points->toBe(0)
        ->is_inclusive->toBeFalse();
});

test('onboarding validates tax details and does not provision a partial workspace', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->from(route('onboarding.create'))->post(route('onboarding.store'), [
        'business_name' => 'Bubur Kota',
        'outlet_name' => 'Bubur Kota Pusat',
        'timezone' => 'Asia/Jakarta',
        'tax_enabled' => true,
        'tax_name' => '',
        'tax_rate' => 0,
        'tax_inclusive' => false,
    ]);

    $response->assertRedirect(route('onboarding.create'))->assertSessionHasErrors(['tax_name', 'tax_rate']);
    expect(Tenant::query()->count())->toBe(0)
        ->and(Outlet::query()->count())->toBe(0)
        ->and(TaxSetting::query()->count())->toBe(0);
});

test('users cannot provision another workspace through onboarding', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $tenant->users()->attach($user, ['status' => 'active', 'is_owner' => true, 'joined_at' => now()]);

    $response = $this->actingAs($user)->post(route('onboarding.store'), [
        'business_name' => 'Bisnis Kedua',
        'outlet_name' => 'Outlet Kedua',
        'timezone' => 'Asia/Jakarta',
        'tax_enabled' => false,
        'tax_inclusive' => false,
    ]);

    $response->assertForbidden();
    expect(Tenant::query()->count())->toBe(1);
});

test('users with an inactive workspace cannot loop back into onboarding', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['status' => TenantStatus::Active]);
    $tenant->users()->attach($user, [
        'status' => 'inactive',
        'is_owner' => false,
        'joined_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('onboarding.create'))
        ->assertForbidden();
});
