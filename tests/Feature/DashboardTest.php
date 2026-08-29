<?php

use App\Models\Outlet;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users without an active tenant are directed to onboarding', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('onboarding.create'));
});

test('tenant members with order permission can visit the dashboard', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    Outlet::factory()->for($tenant)->create();
    $tenant->users()->attach($user, ['status' => 'active', 'joined_at' => now()]);

    $permission = Permission::query()->create(['name' => 'order.view', 'guard_name' => 'web']);
    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
    $role = Role::query()->create(['tenant_id' => $tenant->id, 'name' => 'staff', 'guard_name' => 'web']);
    $role->givePermissionTo($permission);
    $user->assignRole($role);

    $response = $this->actingAs($user)->get(route('dashboard'));
    $response->assertOk();
});
