<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;

interface PaymentGateway
{
    /**
     * @return array{provider: string, redirect_url: string, expires_at: string|null}
     */
    public function createCheckout(Payment $payment, Order $order, string $finishUrl): array;

    /** @return array<string, mixed> */
    public function getStatus(Payment $payment): array;
}
