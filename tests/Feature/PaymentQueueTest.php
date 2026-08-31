<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Jobs\ReconcilePaymentJob;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Support\Facades\Queue;

test('production reconciliation command dispatches pending payments to a retryable queue job', function () {
    $tenant = Tenant::factory()->withTrialSubscription()->create();
    $outlet = Outlet::factory()->for($tenant)->create();
    $table = DiningTable::factory()->for($outlet)->create();
    $order = Order::factory()->for($table, 'table')->create([
        'status' => OrderStatus::AwaitingPayment,
    ]);
    $payment = Payment::factory()->for($order)->create([
        'status' => PaymentStatus::Pending,
        'provider' => 'midtrans',
        'provider_reference' => 'meja-payment-'.$order->id,
    ]);
    Queue::fake();
    config(['queue.default' => 'redis']);

    $this->artisan('payments:reconcile --limit=10')
        ->expectsOutputToContain('Reconciliation diantrekan: 1 payment.')
        ->assertSuccessful();

    Queue::assertPushed(ReconcilePaymentJob::class, fn (ReconcilePaymentJob $job): bool => $job->paymentId === $payment->id);
    expect((new ReconcilePaymentJob($payment->id))->tries)->toBe(3)
        ->and((new ReconcilePaymentJob($payment->id))->backoff())->toBe([60, 300, 900]);
});
