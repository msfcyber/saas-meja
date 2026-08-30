<?php

use App\Actions\Tenancy\CreateOwnerWorkspace;
use App\Models\AuditLog;
use App\Models\Outlet;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

/** @return array{owner: User, tenant: Tenant, outlet: Outlet, second_outlet: Outlet} */
function outletAssignmentWorkspace(): array
{
    $owner = User::factory()->create();
    $workspace = app(CreateOwnerWorkspace::class)->handle($owner, [
        'business_name' => 'Outlet Assignment Workspace',
        'outlet_name' => 'Outlet Assignment Pusat',
        'address' => null,
        'phone' => null,
        'timezone' => 'Asia/Jakarta',
        'tax_enabled' => false,
        'tax_name' => null,
        'tax_rate' => null,
        'tax_inclusive' => false,
    ]);
    $secondOutlet = Outlet::factory()->for($workspace['tenant'])->create([
        'name' => 'Outlet Assignment Selatan',
    ]);

    return [
        'owner' => $owner,
        'tenant' => $workspace['tenant'],
        'outlet' => $workspace['outlet'],
        'second_outlet' => $secondOutlet,
    ];
}

/** @param list<int> $outletIds */
function assignedStaff(Tenant $tenant, array $outletIds, string $role = 'cashier'): User
{
    $staff = User::factory()->create();
    $tenant->users()->attach($staff, [
        'status' => 'active',
        'is_owner' => false,
        'joined_at' => now(),
    ]);
    $staff->assignedOutletsFor($tenant)->sync($outletIds);

    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($tenant->id);
    $staff->syncRoles([Role::query()->where('tenant_id', $tenant->id)->where('name', $role)->firstOrFail()]);
    $staff->unsetRelation('roles')->unsetRelation('permissions');
    $registrar->setPermissionsTeamId(null);

    return $staff;
}

/** @param array{tenant: Tenant, outlet: Outlet} $workspace */
function outletAssignmentSession(array $workspace, ?int $outletId = null): array
{
    return [
        'active_tenant_id' => $workspace['tenant']->id,
        'active_outlet_id' => $outletId ?? $workspace['outlet']->id,
    ];
}

test('migration backfills every existing tenant membership to every tenant outlet', function () {
    $tenant = Tenant::factory()->create();
    $firstOutlet = Outlet::factory()->for($tenant)->create();
    $secondOutlet = Outlet::factory()->for($tenant)->create();
    $staff = User::factory()->create();
    $owner = User::factory()->create();
    $tenant->users()->attach($staff, ['status' => 'active', 'is_owner' => false, 'joined_at' => now()]);
    $tenant->users()->attach($owner, ['status' => 'active', 'is_owner' => true, 'joined_at' => now()]);

    Schema::drop('tenant_outlet_user');
    $migration = require database_path('migrations/2026_08_30_152000_create_tenant_outlet_user_table.php');
    $migration->up();

    $this->assertDatabaseHas('tenant_outlet_user', [
        'tenant_id' => $tenant->id,
        'outlet_id' => $firstOutlet->id,
        'user_id' => $staff->id,
    ]);
    $this->assertDatabaseHas('tenant_outlet_user', [
        'tenant_id' => $tenant->id,
        'outlet_id' => $secondOutlet->id,
        'user_id' => $owner->id,
    ]);
    $this->assertDatabaseHas('tenant_outlet_user', [
        'tenant_id' => $tenant->id,
        'outlet_id' => $secondOutlet->id,
        'user_id' => $staff->id,
    ]);
});

test('direct memberships default to all current tenant outlets', function () {
    $tenant = Tenant::factory()->create();
    $firstOutlet = Outlet::factory()->for($tenant)->create();
    $secondOutlet = Outlet::factory()->for($tenant)->create();
    $staff = User::factory()->create();

    $tenant->users()->attach($staff, ['status' => 'active', 'is_owner' => false, 'joined_at' => now()]);

    expect($staff->assignedOutletsFor($tenant)->pluck('outlets.id')->all())
        ->toEqualCanonicalizing([$firstOutlet->id, $secondOutlet->id]);
});

test('staff creation replaces the default assignment and records outlet audit values', function () {
    $workspace = outletAssignmentWorkspace();

    $this->actingAs($workspace['owner'])
        ->withSession(outletAssignmentSession($workspace))
        ->post(route('staff.store'), [
            'name' => 'Assigned Cashier',
            'email' => 'assigned.cashier@example.com',
            'role' => 'cashier',
            'outlet_ids' => [$workspace['second_outlet']->id],
        ])
        ->assertRedirect(route('staff'));

    $staff = User::query()->where('email', 'assigned.cashier@example.com')->firstOrFail();
    expect($staff->assignedOutletsFor($workspace['tenant'])->pluck('outlets.id')->all())
        ->toBe([$workspace['second_outlet']->id]);

    $audit = AuditLog::withoutGlobalScopes()->where('event', 'staff.added')->firstOrFail();
    expect($audit->new_values)->toMatchArray([
        'outlet_ids' => [$workspace['second_outlet']->id],
        'outlet_names' => [$workspace['second_outlet']->name],
    ]);
});

test('staff updates synchronize assignments and retain old and new outlet audit values', function () {
    $workspace = outletAssignmentWorkspace();
    $staff = assignedStaff($workspace['tenant'], [$workspace['outlet']->id]);

    $this->actingAs($workspace['owner'])
        ->withSession(outletAssignmentSession($workspace))
        ->patch(route('staff.update', $staff), [
            'role' => 'admin',
            'status' => 'active',
            'outlet_ids' => [$workspace['second_outlet']->id],
        ])
        ->assertRedirect(route('staff'));

    expect($staff->fresh()->assignedOutletsFor($workspace['tenant'])->pluck('outlets.id')->all())
        ->toBe([$workspace['second_outlet']->id]);

    $audit = AuditLog::withoutGlobalScopes()->where('event', 'staff.updated')->firstOrFail();
    expect($audit->old_values)->toMatchArray([
        'outlet_ids' => [$workspace['outlet']->id],
        'outlet_names' => [$workspace['outlet']->name],
    ])->and($audit->new_values)->toMatchArray([
        'outlet_ids' => [$workspace['second_outlet']->id],
        'outlet_names' => [$workspace['second_outlet']->name],
    ]);
});

test('staff outlet validation requires active outlets in the active tenant', function () {
    $workspace = outletAssignmentWorkspace();
    $foreignOutlet = Outlet::factory()->create();
    $inactiveOutlet = Outlet::factory()->for($workspace['tenant'])->create(['is_active' => false]);

    $this->actingAs($workspace['owner'])
        ->withSession(outletAssignmentSession($workspace))
        ->from(route('staff'))
        ->post(route('staff.store'), [
            'name' => 'Invalid Assignment',
            'email' => 'invalid.assignment@example.com',
            'role' => 'cashier',
            'outlet_ids' => [$foreignOutlet->id],
        ])
        ->assertRedirect(route('staff'))
        ->assertSessionHasErrors('outlet_ids.0');

    $staff = assignedStaff($workspace['tenant'], [$workspace['outlet']->id]);
    $this->actingAs($workspace['owner'])
        ->withSession(outletAssignmentSession($workspace))
        ->from(route('staff'))
        ->patch(route('staff.update', $staff), [
            'role' => 'cashier',
            'status' => 'active',
            'outlet_ids' => [$inactiveOutlet->id],
        ])
        ->assertRedirect(route('staff'))
        ->assertSessionHasErrors('outlet_ids.0');
});

test('resolver falls back to the first assigned active outlet and denies staff with none', function () {
    $workspace = outletAssignmentWorkspace();
    $staff = assignedStaff($workspace['tenant'], [$workspace['second_outlet']->id]);

    Route::middleware(['web', 'auth', 'tenant.required'])->get('/_test/assigned-outlet-context', function (TenantContext $context): array {
        return ['outlet_id' => $context->outletId()];
    });

    $this->actingAs($staff)
        ->withSession(outletAssignmentSession($workspace, $workspace['outlet']->id))
        ->getJson('/_test/assigned-outlet-context')
        ->assertOk()
        ->assertExactJson(['outlet_id' => $workspace['second_outlet']->id])
        ->assertSessionHas('active_outlet_id', $workspace['second_outlet']->id);

    $staff->assignedOutletsFor($workspace['tenant'])->detach();

    $this->actingAs($staff)
        ->withSession(outletAssignmentSession($workspace))
        ->get('/dashboard')
        ->assertForbidden();
});

test('staff cannot switch to or manage unassigned outlets while owners retain all outlet access', function () {
    $workspace = outletAssignmentWorkspace();
    $staff = assignedStaff($workspace['tenant'], [$workspace['outlet']->id], 'admin');

    $this->actingAs($staff)
        ->withSession(outletAssignmentSession($workspace))
        ->post(route('context.outlet.switch', $workspace['second_outlet']))
        ->assertForbidden();

    $this->actingAs($staff)
        ->withSession(outletAssignmentSession($workspace))
        ->patch(route('outlets.update', $workspace['second_outlet']), ['name' => 'Forged Update'])
        ->assertForbidden();

    $this->actingAs($staff)
        ->withSession(outletAssignmentSession($workspace))
        ->get(route('outlets'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('outlets', 1)
            ->where('outlets.0.id', $workspace['outlet']->id));

    $this->actingAs($staff)
        ->withSession(outletAssignmentSession($workspace))
        ->get(route('reports.sales', ['outlet' => $workspace['second_outlet']->id]))
        ->assertInvalid('outlet');

    $this->actingAs($workspace['owner'])
        ->withSession(outletAssignmentSession($workspace))
        ->post(route('context.outlet.switch', $workspace['second_outlet']))
        ->assertRedirect()
        ->assertSessionHas('active_outlet_id', $workspace['second_outlet']->id);
});
