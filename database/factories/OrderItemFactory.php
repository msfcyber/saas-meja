<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrderItem> */
class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_id' => null,
            'variant_id' => null,
            'product_name_snapshot' => fake()->words(2, true),
            'product_description_snapshot' => fake()->sentence(),
            'variant_name_snapshot' => null,
            'base_price_snapshot' => 28000,
            'variant_price_delta_snapshot' => 0,
            'modifier_amount_snapshot' => 0,
            'unit_price' => 28000,
            'quantity' => 1,
            'line_total' => 28000,
            'note' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (OrderItem $item) {
            $order = Order::query()->findOrFail($item->order_id);
            $item->tenant_id = $order->tenant_id;
            $item->outlet_id = $order->outlet_id;
        });
    }
}
