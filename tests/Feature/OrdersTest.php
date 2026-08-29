<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Outlet;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

/** @return array{user: User, tenant: Tenant, outlet: Outlet, table: DiningTable} */
function createOrderBoardWorkspace(bool $canUpdate = true): array
{
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $outlet = Outlet::factory()->for($tenant)->create();
    $table = DiningTable::factory()->for($outlet)->create();
    $tenant->users()->attach($user, ['status' => 'active', 'joined_at' => now()]);

    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
    $viewPermission = Permission::query()->firstOrCreate(['name' => 'order.view', 'guard_name' => 'web']);
    $role = Role::factory()->for($tenant)->create(['name' => 'order-staff']);
    $role->givePermissionTo($viewPermission);

    if ($canUpdate) {
        $updatePermission = Permission::query()->firstOrCreate(['name' => 'order.update_status', 'guard_name' => 'web']);
        $role->givePermissionTo($updatePermission);
    }

    $user->assignRole($role);

    return compact('user', 'tenant', 'outlet', 'table');
}

test('staff order board only exposes orders from the active outlet', function () {
    $workspace = createOrderBoardWorkspace();
    $visibleOrder = Order::factory()->for($workspace['table'], 'table')->create([
        'tenant_id' => $workspace['tenant']->id,
        'outlet_id' => $workspace['outlet']->id,
        'status' => OrderStatus::Paid,
    ]);
    Payment::factory()->for($visibleOrder)->create(['status' => PaymentStatus::Paid, 'paid_at' => now()]);

    $otherOutlet = Outlet::factory()->for($workspace['tenant'])->create();
    $otherTable = DiningTable::factory()->for($otherOutlet)->create();
    $hiddenOrder = Order::factory()->for($otherTable, 'table')->create([
        'tenant_id' => $workspace['tenant']->id,
        'outlet_id' => $otherOutlet->id,
        'status' => OrderStatus::Paid,
    ]);

    $this->actingAs($workspace['user'])
        ->get(route('orders'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('orders')
            ->where('counts.active', 1)
            ->has('orders', 1)
            ->where('orders.0.id', $visibleOrder->id));
});

test('staff can advance an order and the status change is recorded with the actor', function () {
    $workspace = createOrderBoardWorkspace();
    $order = Order::factory()->for($workspace['table'], 'table')->create([
        'tenant_id' => $workspace['tenant']->id,
        'outlet_id' => $workspace['outlet']->id,
        'status' => OrderStatus::Paid,
    ]);

    $this->actingAs($workspace['user'])
        ->patch(route('orders.status.update', $order), ['status' => OrderStatus::Accepted->value])
        ->assertRedirect(route('orders'));

    $history = OrderStatusHistory::withoutGlobalScopes()
        ->where('order_id', $order->id)
        ->where('to_status', OrderStatus::Accepted->value)
        ->firstOrFail();

    expect($order->fresh()->status)->toBe(OrderStatus::Accepted)
        ->and($history->actor_type)->toBe('user')
        ->and($history->actor_id)->toBe($workspace['user']->id);
});

test('staff cannot bypass the order state machine', function () {
    $workspace = createOrderBoardWorkspace();
    $order = Order::factory()->for($workspace['table'], 'table')->create([
        'tenant_id' => $workspace['tenant']->id,
        'outlet_id' => $workspace['outlet']->id,
        'status' => OrderStatus::Paid,
    ]);

    $this->actingAs($workspace['user'])
        ->patch(route('orders.status.update', $order), ['status' => OrderStatus::Preparing->value])
        ->assertConflict();

    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
});

test('staff without status permission can view orders but cannot update them', function () {
    $workspace = createOrderBoardWorkspace(canUpdate: false);
    $order = Order::factory()->for($workspace['table'], 'table')->create([
        'tenant_id' => $workspace['tenant']->id,
        'outlet_id' => $workspace['outlet']->id,
        'status' => OrderStatus::Paid,
    ]);

    $this->actingAs($workspace['user'])->get(route('orders'))->assertOk();
    $this->actingAs($workspace['user'])
        ->patch(route('orders.status.update', $order), ['status' => OrderStatus::Accepted->value])
        ->assertForbidden();
});
