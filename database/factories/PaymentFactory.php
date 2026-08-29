<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'method' => 'qris',
            'status' => PaymentStatus::Pending,
            'amount' => 28000,
            'currency' => 'IDR',
            'provider' => null,
            'provider_reference' => null,
            'expires_at' => now()->addMinutes(15),
            'paid_at' => null,
            'metadata' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Payment $payment) {
            $order = Order::query()->findOrFail($payment->order_id);
            $payment->tenant_id = $order->tenant_id;
            $payment->outlet_id = $order->outlet_id;
        });
    }
}
