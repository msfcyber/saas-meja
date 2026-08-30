<?php

use App\Actions\Tenancy\CreateOwnerWorkspace;
use App\Models\Outlet;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{owner: User, tenant: Tenant, outlet: Outlet, subscription: Subscription}
 */
function createResourceManagementWorkspace(): array
{
    $owner = User::factory()->create();
    $workspace = app(CreateOwnerWorkspace::class)->handle($owner, [
        'business_name' => 'Resource Management',
        'outlet_name' => 'Resource Management Pusat',
        'address' => null,
        'phone' => null,
        'timezone' => 'Asia/Jakarta',
        'tax_enabled' => false,
        'tax_name' => null,
        'tax_rate' => null,
        'tax_inclusive' => false,
    ]);
    $subscription = Subscription::withoutGlobalScopes()
        ->where('tenant_id', $workspace['tenant']->id)
        ->firstOrFail();

    return [
        'owner' => $owner,
        'tenant' => $workspace['tenant'],
        'outlet' => $workspace['outlet'],
        'subscription' => $subscription,
    ];
}

function resourceManagementSession(array $workspace): array
{
    return [
        'active_tenant_id' => $workspace['tenant']->id,
        'active_outlet_id' => $workspace['outlet']->id,
    ];
}

test('owner can view and create an outlet within the tenant scope', function () {
    $workspace = createResourceManagementWorkspace();

    $this->actingAs($workspace['owner'])
        ->withSession(resourceManagementSession($workspace))
        ->get(route('outlets'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('outlets')
            ->has('outlets', 1)
            ->where('outlets.0.id', $workspace['outlet']->id)
            ->where('usage.current', 1)
            ->where('usage.limit', 3)
            ->where('can_add', true),
        );

    $this->actingAs($workspace['owner'])
        ->withSession(resourceManagementSession($workspace))
        ->post(route('outlets.store'), [
            'name' => 'Resource Management Selatan',
            'code' => 'out-002',
            'address' => 'Jl. Selatan No. 2',
            'phone' => '081234567890',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'idr',
            'is_active' => true,
            'accepts_orders' => true,
        ])
        ->assertRedirect(route('outlets'));

    $outlet = Outlet::query()->where('code', 'OUT-002')->firstOrFail();

    expect($outlet->tenant_id)->toBe($workspace['tenant']->id)
        ->and($outlet->slug)->toBe('resource-management-selatan');
    $this->assertDatabaseHas('tax_settings', [
        'tenant_id' => $workspace['tenant']->id,
        'outlet_id' => $outlet->id,
        'is_enabled' => false,
    ]);
    $this->assertDatabaseHas('audit_logs', [
        'event' => 'outlet.created',
        'tenant_id' => $workspace['tenant']->id,
        'outlet_id' => $outlet->id,
    ]);
});

test('outlet creation and reactivation respect the active outlet limit', function () {
    $workspace = createResourceManagementWorkspace();
    $limitedPlan = Plan::factory()->create([
        'limits' => [
            'outlets' => 1,
            'active_tables' => 100,
            'staff' => 10,
        ],
    ]);
    $workspace['subscription']->update(['plan_id' => $limitedPlan->id]);

    $response = $this->actingAs($workspace['owner'])
        ->withSession(resourceManagementSession($workspace))
        ->from(route('outlets'))
        ->post(route('outlets.store'), [
            'name' => 'Outlet Terbatas',
            'code' => 'OUT-002',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'is_active' => true,
            'accepts_orders' => true,
        ]);

    $response->assertRedirect(route('outlets'))->assertSessionHasErrors('subscription');
    expect(Outlet::query()->count())->toBe(1);

    $inactiveOutlet = Outlet::factory()->for($workspace['tenant'])->create([
        'is_active' => false,
    ]);

    $this->actingAs($workspace['owner'])
        ->withSession(resourceManagementSession($workspace))
        ->from(route('outlets'))
        ->patch(route('outlets.update', $inactiveOutlet), ['is_active' => true])
        ->assertRedirect(route('outlets'))
        ->assertSessionHasErrors('subscription');

    expect($inactiveOutlet->fresh()->is_active)->toBeFalse();
});

test('an owner cannot deactivate the last active outlet', function () {
    $workspace = createResourceManagementWorkspace();

    $this->actingAs($workspace['owner'])
        ->withSession(resourceManagementSession($workspace))
        ->from(route('outlets'))
        ->patch(route('outlets.update', $workspace['outlet']), ['is_active' => false])
        ->assertRedirect(route('outlets'))
        ->assertSessionHasErrors('is_active');

    expect($workspace['outlet']->fresh()->is_active)->toBeTrue();
});

test('outlet updates cannot cross the active tenant boundary', function () {
    $workspace = createResourceManagementWorkspace();
    $foreignTenant = Tenant::factory()->create();
    $foreignOutlet = Outlet::factory()->for($foreignTenant)->create();

    $this->actingAs($workspace['owner'])
        ->withSession(resourceManagementSession($workspace))
        ->patch(route('outlets.update', $foreignOutlet), ['name' => 'Tidak boleh'])
        ->assertNotFound();

    expect($foreignOutlet->fresh()->name)->not->toBe('Tidak boleh');
});

test('owner can add update and remove a registered staff member', function () {
    $workspace = createResourceManagementWorkspace();
    $staff = User::factory()->create([
        'name' => 'Kasir Sore',
        'email' => 'kasir.sore@example.com',
    ]);
    $session = resourceManagementSession($workspace);

    $this->actingAs($workspace['owner'])
        ->withSession($session)
        ->post(route('staff.store'), [
            'email' => $staff->email,
            'role' => 'cashier',
        ])
        ->assertRedirect(route('staff'));

    $cashierRole = Role::query()
        ->where('tenant_id', $workspace['tenant']->id)
        ->where('name', 'cashier')
        ->firstOrFail();

    $this->assertDatabaseHas('tenant_user', [
        'tenant_id' => $workspace['tenant']->id,
        'user_id' => $staff->id,
        'status' => 'active',
        'is_owner' => false,
    ]);
    $this->assertDatabaseHas('model_has_roles', [
        'tenant_id' => $workspace['tenant']->id,
        'role_id' => $cashierRole->id,
        'model_id' => $staff->id,
        'model_type' => User::class,
    ]);

    $this->actingAs($workspace['owner'])
        ->withSession($session)
        ->get(route('staff'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('staff')
            ->has('staff', 2)
            ->where('usage.current', 2)
            ->where('staff', fn (Collection $members): bool => $members->contains(
                fn (mixed $member): bool => data_get($member, 'email') === $staff->email
                    && data_get($member, 'role') === 'cashier',
            )),
        );

    $this->actingAs($workspace['owner'])
        ->withSession($session)
        ->patch(route('staff.update', $staff), [
            'role' => 'admin',
            'status' => 'inactive',
        ])
        ->assertRedirect(route('staff'));

    $adminRole = Role::query()
        ->where('tenant_id', $workspace['tenant']->id)
        ->where('name', 'admin')
        ->firstOrFail();

    $this->assertDatabaseHas('tenant_user', [
        'tenant_id' => $workspace['tenant']->id,
        'user_id' => $staff->id,
        'status' => 'inactive',
    ]);
    $this->assertDatabaseHas('model_has_roles', [
        'tenant_id' => $workspace['tenant']->id,
        'role_id' => $adminRole->id,
        'model_id' => $staff->id,
        'model_type' => User::class,
    ]);

    $this->actingAs($workspace['owner'])
        ->withSession($session)
        ->delete(route('staff.destroy', $staff))
        ->assertRedirect(route('staff'));

    $this->assertDatabaseMissing('tenant_user', [
        'tenant_id' => $workspace['tenant']->id,
        'user_id' => $staff->id,
    ]);
    $this->assertDatabaseMissing('model_has_roles', [
        'tenant_id' => $workspace['tenant']->id,
        'model_id' => $staff->id,
        'model_type' => User::class,
    ]);
    $this->assertDatabaseHas('audit_logs', [
        'event' => 'staff.removed',
        'tenant_id' => $workspace['tenant']->id,
        'auditable_id' => $staff->id,
    ]);
});

test('staff creation and reactivation respect the active staff limit', function () {
    $workspace = createResourceManagementWorkspace();
    $limitedPlan = Plan::factory()->create([
        'limits' => [
            'outlets' => 3,
            'active_tables' => 100,
            'staff' => 1,
        ],
    ]);
    $workspace['subscription']->update(['plan_id' => $limitedPlan->id]);
    $staff = User::factory()->create(['email' => 'limited.staff@example.com']);
    $session = resourceManagementSession($workspace);

    $this->actingAs($workspace['owner'])
        ->withSession($session)
        ->from(route('staff'))
        ->post(route('staff.store'), [
            'email' => $staff->email,
            'role' => 'cashier',
        ])
        ->assertRedirect(route('staff'))
        ->assertSessionHasErrors('subscription');

    expect($workspace['tenant']->users()->whereKey($staff->id)->exists())->toBeFalse();
});

test('staff management protects owners and foreign memberships', function () {
    $workspace = createResourceManagementWorkspace();
    $foreignTenant = Tenant::factory()->create();
    $foreignStaff = User::factory()->create();
    $foreignTenant->users()->attach($foreignStaff, [
        'status' => 'active',
        'is_owner' => false,
        'joined_at' => now(),
    ]);
    $session = resourceManagementSession($workspace);

    $this->actingAs($workspace['owner'])
        ->withSession($session)
        ->patch(route('staff.update', $workspace['owner']), [
            'role' => 'admin',
            'status' => 'inactive',
        ])
        ->assertForbidden();

    $this->actingAs($workspace['owner'])
        ->withSession($session)
        ->delete(route('staff.destroy', $workspace['owner']))
        ->assertForbidden();

    $this->actingAs($workspace['owner'])
        ->withSession($session)
        ->patch(route('staff.update', $foreignStaff), [
            'role' => 'cashier',
            'status' => 'inactive',
        ])
        ->assertForbidden();
});

test('staff management requires the staff permission', function () {
    $workspace = createResourceManagementWorkspace();
    $member = User::factory()->create();
    $workspace['tenant']->users()->attach($member, [
        'status' => 'active',
        'is_owner' => false,
        'joined_at' => now(),
    ]);

    $this->actingAs($member)
        ->withSession(resourceManagementSession($workspace))
        ->get(route('staff'))
        ->assertForbidden();
});
