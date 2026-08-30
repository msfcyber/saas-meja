<?php

use App\Enums\OrderStatus;
use App\Events\OrderStatusUpdated;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\Tenant;
use App\Services\OrderStatusService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;

/** @return array{order: Order, tenant: Tenant, outlet: Outlet} */
function createRealtimeOrder(): array
{
    $tenant = Tenant::factory()->create();
    $outlet = Outlet::factory()->for($tenant)->create();
    $table = DiningTable::factory()->for($outlet)->create();
    $order = Order::factory()->for($table, 'table')->create([
        'tenant_id' => $tenant->id,
        'outlet_id' => $outlet->id,
        'status' => OrderStatus::Paid,
    ]);

    return compact('order', 'tenant', 'outlet');
}

test('order status broadcasts after a successful transaction commit', function () {
    $workspace = createRealtimeOrder();
    Event::fake([OrderStatusUpdated::class]);

    DB::transaction(function () use ($workspace): void {
        app(OrderStatusService::class)->transition(
            $workspace['order'],
            OrderStatus::Accepted,
        );
    });

    Event::assertDispatched(OrderStatusUpdated::class, function (OrderStatusUpdated $event) use ($workspace): bool {
        return $event->orderId === $workspace['order']->id
            && $event->tenantId === $workspace['tenant']->id
            && $event->outletId === $workspace['outlet']->id;
    });
});

test('order status does not broadcast when its transaction rolls back', function () {
    $workspace = createRealtimeOrder();
    Event::fake([OrderStatusUpdated::class]);

    try {
        DB::transaction(function () use ($workspace): never {
            app(OrderStatusService::class)->transition(
                $workspace['order'],
                OrderStatus::Accepted,
            );

            throw new RuntimeException('Rollback realtime test.');
        });
    } catch (RuntimeException) {
        // The exception is intentional: the transaction must roll back.
    }

    Event::assertNotDispatched(OrderStatusUpdated::class);
    expect($workspace['order']->fresh()->status)->toBe(OrderStatus::Paid);
});

test('order status event uses isolated outlet and customer channels', function () {
    $workspace = createRealtimeOrder();
    $event = new OrderStatusUpdated(
        $workspace['order']->id,
        $workspace['tenant']->id,
        $workspace['outlet']->id,
        $workspace['order']->access_token_hash,
    );

    $channels = array_map(
        static fn (object $channel): string => (string) $channel,
        $event->broadcastOn(),
    );

    expect($event->broadcastAs())->toBe('order.status.updated')
        ->and($channels)->toBe([
            'private-'.OrderStatusUpdated::outletChannel($workspace['outlet']->id),
            OrderStatusUpdated::customerChannel($workspace['order']->access_token_hash),
        ])
        ->and($event->broadcastWith()['order']['id'])->toBe($workspace['order']->id);
});
