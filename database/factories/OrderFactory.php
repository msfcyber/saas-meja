<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\DiningTable;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Order> */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'table_id' => DiningTable::factory(),
            'order_sequence' => fake()->unique()->numberBetween(1, 9999),
            'order_number' => 'A-'.fake()->unique()->numerify('####'),
            'customer_name' => fake()->optional()->name(),
            'status' => OrderStatus::AwaitingPayment,
            'subtotal' => 28000,
            'discount_amount' => 0,
            'tax_name_snapshot' => null,
            'tax_rate_snapshot' => 0,
            'tax_inclusive_snapshot' => false,
            'tax_amount' => 0,
            'fee_amount' => 0,
            'grand_total' => 28000,
            'currency' => 'IDR',
            'idempotency_key' => (string) Str::uuid(),
            'idempotency_fingerprint' => hash('sha256', Str::uuid()->toString()),
            'access_token_hash' => hash('sha256', Str::random(64)),
            'access_token_encrypted' => 'factory-token',
            'paid_at' => null,
            'completed_at' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Order $order) {
            $table = DiningTable::query()->findOrFail($order->table_id);
            $order->tenant_id = $table->tenant_id;
            $order->outlet_id = $table->outlet_id;
        });
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);
    }
}
