<?php

use App\Enums\OrderStatus;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
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

test('dashboard summarizes paid orders for the active outlet', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $outlet = Outlet::factory()->for($tenant)->create();
    $table = DiningTable::factory()->for($outlet)->create();
    $tenant->users()->attach($user, ['status' => 'active', 'joined_at' => now()]);

    $permission = Permission::query()->create(['name' => 'order.view', 'guard_name' => 'web']);
    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
    $role = Role::query()->create(['tenant_id' => $tenant->id, 'name' => 'dashboard-staff', 'guard_name' => 'web']);
    $role->givePermissionTo($permission);
    $user->assignRole($role);

    Order::factory()->for($table, 'table')->create([
        'tenant_id' => $tenant->id,
        'outlet_id' => $outlet->id,
        'status' => OrderStatus::Paid,
        'paid_at' => now(),
        'grand_total' => 42000,
    ]);
    Order::factory()->for($table, 'table')->create([
        'tenant_id' => $tenant->id,
        'outlet_id' => $outlet->id,
        'status' => OrderStatus::Preparing,
        'paid_at' => now(),
        'grand_total' => 18000,
    ]);

    $otherOutlet = Outlet::factory()->for($tenant)->create();
    $otherTable = DiningTable::factory()->for($otherOutlet)->create();
    Order::factory()->for($otherTable, 'table')->create([
        'tenant_id' => $tenant->id,
        'outlet_id' => $otherOutlet->id,
        'status' => OrderStatus::Paid,
        'paid_at' => now(),
        'grand_total' => 99000,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('outlet.currency', 'IDR')
            ->where('orderSummary.orders_today', 2)
            ->where('orderSummary.gross_sales_today', 60000)
            ->where('orderSummary.active_orders', 2));
});
