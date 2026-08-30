<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Outlet;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

/** @return array{user: User, tenant: Tenant, outlet: Outlet, table: DiningTable} */
function createSalesReportWorkspace(): array
{
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withTrialSubscription()->create();
    $outlet = Outlet::factory()->for($tenant)->create(['name' => 'Outlet Laporan']);
    $table = DiningTable::factory()->for($outlet)->create();
    $tenant->users()->attach($user, [
        'status' => 'active',
        'is_owner' => true,
        'joined_at' => now(),
    ]);

    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($tenant->id);
    $permission = Permission::query()->firstOrCreate(['name' => 'report.view', 'guard_name' => 'web']);
    $role = Role::factory()->for($tenant)->create(['name' => 'reporter-'.$tenant->id]);
    $role->givePermissionTo($permission);
    $user->assignRole($role);
    $registrar->setPermissionsTeamId(null);

    return compact('user', 'tenant', 'outlet', 'table');
}

test('sales report aggregates verified orders using the tenant local date', function () {
    $workspace = createSalesReportWorkspace();
    $otherOutlet = Outlet::factory()->for($workspace['tenant'])->create(['name' => 'Outlet Cabang']);
    $otherTable = DiningTable::factory()->for($otherOutlet)->create();
    $otherTenant = Tenant::factory()->create();
    $otherTenantOutlet = Outlet::factory()->for($otherTenant)->create();
    $otherTenantTable = DiningTable::factory()->for($otherTenantOutlet)->create();
    $localMorning = CarbonImmutable::create(2026, 8, 29, 17, 30, 0, 'UTC');
    $localEvening = CarbonImmutable::create(2026, 8, 30, 15, 0, 0, 'UTC');
    $localRefund = CarbonImmutable::create(2026, 8, 30, 16, 0, 0, 'UTC');

    $firstOrder = Order::factory()->for($workspace['table'], 'table')->create([
        'tenant_id' => $workspace['tenant']->id,
        'outlet_id' => $workspace['outlet']->id,
        'order_number' => 'SALE-001',
        'status' => OrderStatus::Completed,
        'grand_total' => 100000,
        'paid_at' => $localMorning,
    ]);
    Payment::factory()->for($firstOrder)->create([
        'method' => 'qris',
        'status' => PaymentStatus::Paid,
        'amount' => 100000,
        'paid_at' => $localMorning,
    ]);
    OrderItem::factory()->for($firstOrder)->create([
        'product_name_snapshot' => 'Nasi Goreng',
        'quantity' => 2,
        'unit_price' => 50000,
        'line_total' => 100000,
    ]);
    Payment::factory()->for($firstOrder)->create([
        'method' => 'qris',
        'status' => PaymentStatus::Pending,
        'amount' => 100000,
    ]);

    $secondOrder = Order::factory()->for($otherTable, 'table')->create([
        'tenant_id' => $workspace['tenant']->id,
        'outlet_id' => $otherOutlet->id,
        'order_number' => 'SALE-002',
        'status' => OrderStatus::Accepted,
        'grand_total' => 50000,
        'paid_at' => $localEvening,
    ]);
    Payment::factory()->for($secondOrder)->create([
        'method' => 'cash',
        'status' => PaymentStatus::Paid,
        'amount' => 50000,
        'paid_at' => $localEvening,
    ]);
    OrderItem::factory()->for($secondOrder)->create([
        'product_name_snapshot' => 'Nasi Goreng',
        'quantity' => 1,
        'unit_price' => 30000,
        'line_total' => 30000,
    ]);
    OrderItem::factory()->for($secondOrder)->create([
        'product_name_snapshot' => 'Es Teh',
        'quantity' => 2,
        'unit_price' => 10000,
        'line_total' => 20000,
    ]);

    $refundedOrder = Order::factory()->for($workspace['table'], 'table')->create([
        'tenant_id' => $workspace['tenant']->id,
        'outlet_id' => $workspace['outlet']->id,
        'order_number' => 'SALE-003',
        'status' => OrderStatus::Refunded,
        'grand_total' => 30000,
        'paid_at' => $localRefund,
    ]);
    Payment::factory()->for($refundedOrder)->create([
        'status' => PaymentStatus::Refunded,
        'amount' => 30000,
        'paid_at' => $localRefund,
    ]);

    Order::factory()->for($workspace['table'], 'table')->create([
        'tenant_id' => $workspace['tenant']->id,
        'outlet_id' => $workspace['outlet']->id,
        'order_number' => 'SALE-004',
        'status' => OrderStatus::AwaitingPayment,
        'grand_total' => 90000,
        'paid_at' => $localEvening,
    ]);
    Order::factory()->for($otherTenantTable, 'table')->create([
        'tenant_id' => $otherTenant->id,
        'outlet_id' => $otherTenantOutlet->id,
        'order_number' => 'FOREIGN-001',
        'status' => OrderStatus::Completed,
        'grand_total' => 999000,
        'paid_at' => $localEvening,
    ]);

    $this->actingAs($workspace['user'])
        ->withSession([
            'active_tenant_id' => $workspace['tenant']->id,
            'active_outlet_id' => $workspace['outlet']->id,
        ])
        ->get(route('reports.sales', ['from' => '2026-08-30', 'to' => '2026-08-30']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/sales')
            ->where('filters.from', '2026-08-30')
            ->where('filters.to', '2026-08-30')
            ->where('filters.outlet', null)
            ->where('summary.orders', 2)
            ->where('summary.gross_sales', 150000)
            ->where('summary.average_order', 75000)
            ->where('summary.refunded_orders', 1)
            ->where('summary.refunded_amount', 30000)
            ->has('payment_methods', 2)
            ->where('payment_methods.0.method', 'qris')
            ->where('payment_methods.0.amount', 100000)
            ->where('payment_methods.1.method', 'cash')
            ->where('payment_methods.1.amount', 50000)
            ->has('daily_sales', 1)
            ->where('daily_sales.0.date', '2026-08-30')
            ->where('daily_sales.0.orders', 2)
            ->where('daily_sales.0.amount', 150000)
            ->has('top_products', 2)
            ->where('top_products.0.name', 'Nasi Goreng')
            ->where('top_products.0.quantity', 3)
            ->where('top_products.0.amount', 130000)
            ->has('transactions', 2)
            ->where('transactions.0.order_number', 'SALE-002')
            ->where('transactions.0.payment_method', 'cash')
            ->where('transactions.1.order_number', 'SALE-001')
            ->where('transactions.1.payment_method', 'qris'),
        );
});

test('sales report filters by an outlet inside the active tenant', function () {
    $workspace = createSalesReportWorkspace();
    $otherOutlet = Outlet::factory()->for($workspace['tenant'])->create();
    $otherTable = DiningTable::factory()->for($otherOutlet)->create();
    $paidAt = CarbonImmutable::create(2026, 8, 30, 5, 0, 0, 'UTC');

    $visibleOrder = Order::factory()->for($workspace['table'], 'table')->create([
        'tenant_id' => $workspace['tenant']->id,
        'outlet_id' => $workspace['outlet']->id,
        'order_number' => 'FILTER-001',
        'status' => OrderStatus::Paid,
        'grand_total' => 45000,
        'paid_at' => $paidAt,
    ]);
    Payment::factory()->for($visibleOrder)->create([
        'status' => PaymentStatus::Paid,
        'amount' => 45000,
        'paid_at' => $paidAt,
    ]);
    OrderItem::factory()->for($visibleOrder)->create([
        'product_name_snapshot' => 'Kopi Susu',
        'quantity' => 1,
        'unit_price' => 45000,
        'line_total' => 45000,
    ]);

    $hiddenOrder = Order::factory()->for($otherTable, 'table')->create([
        'tenant_id' => $workspace['tenant']->id,
        'outlet_id' => $otherOutlet->id,
        'order_number' => 'FILTER-002',
        'status' => OrderStatus::Paid,
        'grand_total' => 80000,
        'paid_at' => $paidAt,
    ]);
    Payment::factory()->for($hiddenOrder)->create([
        'status' => PaymentStatus::Paid,
        'amount' => 80000,
        'paid_at' => $paidAt,
    ]);

    $this->actingAs($workspace['user'])
        ->withSession([
            'active_tenant_id' => $workspace['tenant']->id,
            'active_outlet_id' => $workspace['outlet']->id,
        ])
        ->get(route('reports.sales', [
            'from' => '2026-08-30',
            'to' => '2026-08-30',
            'outlet' => $workspace['outlet']->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.outlet', $workspace['outlet']->id)
            ->where('summary.orders', 1)
            ->where('summary.gross_sales', 45000)
            ->has('transactions', 1)
            ->where('transactions.0.order_number', 'FILTER-001')
            ->where('transactions.0.outlet', $workspace['outlet']->name),
        );
});

test('sales report rejects an outlet belonging to another tenant', function () {
    $workspace = createSalesReportWorkspace();
    $foreignTenant = Tenant::factory()->create();
    $foreignOutlet = Outlet::factory()->for($foreignTenant)->create();

    $this->actingAs($workspace['user'])
        ->withSession([
            'active_tenant_id' => $workspace['tenant']->id,
            'active_outlet_id' => $workspace['outlet']->id,
        ])
        ->get(route('reports.sales', ['outlet' => $foreignOutlet->id]))
        ->assertSessionHasErrors('outlet');
});

test('sales report requires the report permission', function () {
    $workspace = createSalesReportWorkspace();
    $user = User::factory()->create();
    $workspace['tenant']->users()->attach($user, ['status' => 'active', 'joined_at' => now()]);

    $this->actingAs($user)
        ->withSession([
            'active_tenant_id' => $workspace['tenant']->id,
            'active_outlet_id' => $workspace['outlet']->id,
        ])
        ->get(route('reports.sales'))
        ->assertForbidden();
});
