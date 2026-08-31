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

    /** @return array{provider: string, refund_key: string, provider_reference: string|null, response: array<string, mixed>} */
    public function refund(Payment $payment, int $amount, string $refundKey, string $reason): array;
}
