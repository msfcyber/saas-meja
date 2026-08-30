<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;

final class PaymentCheckoutService
{
    public function __construct(private readonly PaymentGatewayManager $gateways) {}

    /**
     * @return array{provider: string, redirect_url: string, expires_at: string|null}
     */
    public function start(Payment $payment, Order $order, string $finishUrl): array
    {
        return $this->gateways->for((string) $payment->provider)
            ->createCheckout($payment, $order, $finishUrl);
    }
}
