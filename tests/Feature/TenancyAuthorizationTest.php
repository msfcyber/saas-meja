<?php

use App\Models\Category;
use App\Models\Outlet;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

/**
 * @param  list<string>  $permissions
 * @return array{tenant: Tenant, outlet: Outlet}
 */
function createTenantMembership(User $user, array $permissions = []): array
{
    $tenant = Tenant::factory()->create();
    $outlet = Outlet::factory()->for($tenant)->create();
    $tenant->users()->attach($user, [
        'status' => 'active',
        'is_owner' => false,
        'joined_at' => now(),
    ]);

    if ($permissions !== []) {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();
        $registrar->setPermissionsTeamId($tenant->id);

        $role = Role::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'member-'.$tenant->id,
            'guard_name' => 'web',
        ]);

        foreach ($permissions as $permissionName) {
            $permission = Permission::query()->firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
            $role->givePermissionTo($permission);
        }

        $user->assignRole($role);
    }

    return ['tenant' => $tenant, 'outlet' => $outlet];
}

test('middleware resolves the preferred tenant and outlet into inertia props', function () {
    $user = User::factory()->create();
    createTenantMembership($user, ['order.view']);
    $preferred = createTenantMembership($user, ['order.view']);

    $response = $this->actingAs($user)
        ->withSession([
            'active_tenant_id' => $preferred['tenant']->id,
            'active_outlet_id' => $preferred['outlet']->id,
        ])
        ->get(route('dashboard'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('tenancy.tenant.id', $preferred['tenant']->id)
        ->where('tenancy.outlet.id', $preferred['outlet']->id)
        ->has('tenancy.tenants', 2)
        ->where('tenancy.permissions', fn ($permissions) => $permissions->contains('order.view'))
    );
});

test('tenant and outlet scopes hide records outside the active context', function () {
    $user = User::factory()->create();
    $active = createTenantMembership($user);
    $other = createTenantMembership(User::factory()->create());
    $activeCategory = Category::factory()->for($active['outlet'])->create();
    $otherCategory = Category::factory()->for($other['outlet'])->create();
    $activeProduct = Product::factory()->for($activeCategory)->create();
    Product::factory()->for($otherCategory)->create();

    Route::middleware(['web', 'auth'])->get('/_test/tenant-products', fn () => Product::query()->pluck('id'));

    $response = $this->actingAs($user)
        ->withSession([
            'active_tenant_id' => $active['tenant']->id,
            'active_outlet_id' => $active['outlet']->id,
        ])
        ->getJson('/_test/tenant-products');

    $response->assertOk()->assertExactJson([$activeProduct->id]);
});

test('invalid tenant session falls back to an active membership', function () {
    $user = User::factory()->create();
    $membership = createTenantMembership($user, ['order.view']);
    $foreignTenant = Tenant::factory()->create();

    $response = $this->actingAs($user)
        ->withSession(['active_tenant_id' => $foreignTenant->id])
        ->get(route('dashboard'));

    $response->assertOk()
        ->assertSessionHas('active_tenant_id', $membership['tenant']->id)
        ->assertSessionHas('active_outlet_id', $membership['outlet']->id);
});

test('route permissions restrict operational areas by role', function () {
    $user = User::factory()->create();
    createTenantMembership($user, ['order.view']);

    $this->actingAs($user)->get(route('orders'))->assertOk();
    $this->actingAs($user)->get(route('products'))->assertForbidden();
    $this->actingAs($user)->get(route('tables'))->assertForbidden();
});

test('product policy rejects a model owned by another tenant', function () {
    $user = User::factory()->create();
    $active = createTenantMembership($user, ['menu.manage']);
    $other = createTenantMembership(User::factory()->create());
    $activeProduct = Product::factory()->for(Category::factory()->for($active['outlet']))->create();
    $otherProduct = Product::factory()->for(Category::factory()->for($other['outlet']))->create();

    $context = app(TenantContext::class);
    $context->setTenant($active['tenant']);
    $context->setOutlet($active['outlet']);
    $context->markResolved();
    app(PermissionRegistrar::class)->setPermissionsTeamId($active['tenant']->id);
    $user->unsetRelation('roles')->unsetRelation('permissions');

    expect(Gate::forUser($user)->allows('update', $activeProduct))->toBeTrue()
        ->and(Gate::forUser($user)->allows('update', $otherProduct))->toBeFalse();

    $context->clear();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

test('users can switch only to tenants where they have an active membership', function () {
    $user = User::factory()->create();
    $first = createTenantMembership($user);
    $second = createTenantMembership($user);
    $foreignTenant = Tenant::factory()->create();

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $first['tenant']->id])
        ->post(route('context.tenant.switch', $second['tenant']))
        ->assertRedirect()
        ->assertSessionHas('active_tenant_id', $second['tenant']->id)
        ->assertSessionMissing('active_outlet_id');

    $this->actingAs($user)
        ->post(route('context.tenant.switch', $foreignTenant))
        ->assertForbidden();
});

test('outlet switching is limited to the active tenant', function () {
    $user = User::factory()->create();
    $active = createTenantMembership($user);
    $secondOutlet = Outlet::factory()->for($active['tenant'])->create();
    $foreign = createTenantMembership(User::factory()->create());

    $this->actingAs($user)
        ->withSession([
            'active_tenant_id' => $active['tenant']->id,
            'active_outlet_id' => $active['outlet']->id,
        ])
        ->post(route('context.outlet.switch', $secondOutlet))
        ->assertRedirect()
        ->assertSessionHas('active_outlet_id', $secondOutlet->id);

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $active['tenant']->id])
        ->post(route('context.outlet.switch', $foreign['outlet']))
        ->assertNotFound();
});
