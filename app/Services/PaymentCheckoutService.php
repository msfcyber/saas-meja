<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;

final class PaymentCheckoutService
{
    public function __construct(private readonly MidtransPaymentGateway $midtrans) {}

    /**
     * @return array{provider: string, redirect_url: string, expires_at: string|null}
     */
    public function start(Payment $payment, Order $order, string $finishUrl): array
    {
        return match ($payment->provider) {
            'midtrans' => $this->midtrans->createCheckout($payment, $order, $finishUrl),
            default => throw new PaymentGatewayException('Provider payment belum didukung.'),
        };
    }
}
