<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\OrderStatusUpdated;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Outlet;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StaffNotificationPreference;
use App\Models\Tenant;
use App\Models\User;
use App\Services\OrderStatusService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

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
            ->where('realtime.channel', 'outlet.'.$workspace['outlet']->id.'.orders')
            ->where('notifications.visual_enabled', true)
            ->where('notifications.sound_enabled', true)
            ->has('orders', 1)
            ->where('orders.0.id', $visibleOrder->id));
});

test('staff order board filters orders by table and outlet-local date range', function () {
    $workspace = createOrderBoardWorkspace();
    $secondTable = DiningTable::factory()->for($workspace['outlet'])->create([
        'name' => 'Meja Selatan',
    ]);
    $localNow = CarbonImmutable::now($workspace['outlet']->timezone);
    $today = $localNow->toDateString();

    $targetOrder = Order::factory()->for($workspace['table'], 'table')->create([
        'tenant_id' => $workspace['tenant']->id,
        'outlet_id' => $workspace['outlet']->id,
        'status' => OrderStatus::Paid,
        'created_at' => $localNow->setTime(10, 0)->setTimezone('UTC'),
    ]);
    Order::factory()->for($secondTable, 'table')->create([
        'tenant_id' => $workspace['tenant']->id,
        'outlet_id' => $workspace['outlet']->id,
        'status' => OrderStatus::Paid,
        'created_at' => $localNow->subDay()->setTime(10, 0)->setTimezone('UTC'),
    ]);

    $this->actingAs($workspace['user'])
        ->get(route('orders', [
            'table_id' => $workspace['table']->id,
            'from' => $today,
            'to' => $today,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('orders', 1)
            ->where('orders.0.id', $targetOrder->id)
            ->where('filters.table_id', $workspace['table']->id)
            ->where('filters.from', $today)
            ->where('filters.to', $today)
            ->has('tables', 2));
});

test('staff with payment access can print a paid order receipt from the active outlet', function () {
    $workspace = createOrderBoardWorkspace();
    app(PermissionRegistrar::class)->setPermissionsTeamId($workspace['tenant']->id);
    $paymentPermission = Permission::query()->firstOrCreate([
        'name' => 'payment.view',
        'guard_name' => 'web',
    ]);
    $workspace['user']->givePermissionTo($paymentPermission);
    $order = Order::factory()->for($workspace['table'], 'table')->create([
        'tenant_id' => $workspace['tenant']->id,
        'outlet_id' => $workspace['outlet']->id,
        'status' => OrderStatus::Paid,
    ]);
    Payment::factory()->for($order)->create([
        'status' => PaymentStatus::Paid,
        'paid_at' => now(),
    ]);

    $this->actingAs($workspace['user'])
        ->get(route('orders.receipt', $order))
        ->assertOk()
        ->assertSee('#'.$order->order_number);
});

test('staff notification preferences are stored for the active outlet only', function () {
    $workspace = createOrderBoardWorkspace();
    $otherOutlet = Outlet::factory()->for($workspace['tenant'])->create();
    StaffNotificationPreference::factory()->create([
        'tenant_id' => $workspace['tenant']->id,
        'outlet_id' => $otherOutlet->id,
        'user_id' => $workspace['user']->id,
        'visual_enabled' => true,
        'sound_enabled' => false,
    ]);

    $this->actingAs($workspace['user'])
        ->put(route('orders.notifications.update'), [
            'visual_enabled' => false,
            'sound_enabled' => true,
        ])
        ->assertRedirect(route('orders'));

    expect(StaffNotificationPreference::withoutGlobalScopes()
        ->where('tenant_id', $workspace['tenant']->id)
        ->where('outlet_id', $workspace['outlet']->id)
        ->where('user_id', $workspace['user']->id)
        ->firstOrFail())
        ->visual_enabled->toBeFalse()
        ->sound_enabled->toBeTrue();

    expect(StaffNotificationPreference::withoutGlobalScopes()
        ->where('outlet_id', $otherOutlet->id)
        ->where('user_id', $workspace['user']->id)
        ->firstOrFail())
        ->visual_enabled->toBeTrue()
        ->sound_enabled->toBeFalse();

    $this->actingAs($workspace['user'])
        ->get(route('orders'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications.visual_enabled', false)
            ->where('notifications.sound_enabled', true));
});

test('staff notification preferences require boolean values', function () {
    $workspace = createOrderBoardWorkspace();

    $this->actingAs($workspace['user'])
        ->put(route('orders.notifications.update'), [
            'visual_enabled' => 'yes',
            'sound_enabled' => true,
        ])
        ->assertSessionHasErrors('visual_enabled');
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

test('staff order status changes dispatch the realtime event', function () {
    $workspace = createOrderBoardWorkspace();
    $order = Order::factory()->for($workspace['table'], 'table')->create([
        'tenant_id' => $workspace['tenant']->id,
        'outlet_id' => $workspace['outlet']->id,
        'status' => OrderStatus::Paid,
    ]);
    Event::fake([OrderStatusUpdated::class]);

    $this->actingAs($workspace['user'])
        ->patch(route('orders.status.update', $order), ['status' => OrderStatus::Accepted->value])
        ->assertRedirect(route('orders'));

    Event::assertDispatched(OrderStatusUpdated::class, fn (OrderStatusUpdated $event): bool => $event->orderId === $order->id
            && $event->outletId === $workspace['outlet']->id);
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

test('only a verified payment can move an awaiting order into paid', function () {
    $workspace = createOrderBoardWorkspace();
    $order = Order::factory()->for($workspace['table'], 'table')->create([
        'tenant_id' => $workspace['tenant']->id,
        'outlet_id' => $workspace['outlet']->id,
        'status' => OrderStatus::AwaitingPayment,
    ]);

    expect(fn () => app(OrderStatusService::class)->transition($order, OrderStatus::Paid, 'user'))
        ->toThrow(ConflictHttpException::class);
    expect($order->fresh()->status)->toBe(OrderStatus::AwaitingPayment);
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
