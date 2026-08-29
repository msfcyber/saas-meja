<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrderStatusHistory> */
class OrderStatusHistoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'from_status' => null,
            'to_status' => OrderStatus::AwaitingPayment,
            'actor_type' => 'system',
            'actor_id' => null,
            'note' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (OrderStatusHistory $history) {
            $order = Order::query()->findOrFail($history->order_id);
            $history->tenant_id = $order->tenant_id;
            $history->outlet_id = $order->outlet_id;
        });
    }
}
