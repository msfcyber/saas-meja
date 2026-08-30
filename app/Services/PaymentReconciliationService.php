<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Payment;

final class PaymentReconciliationService
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly MidtransWebhookService $midtransWebhooks,
    ) {}

    /** @return array<string, mixed> */
    public function reconcile(Payment $payment): array
    {
        if ($payment->status !== PaymentStatus::Pending) {
            return ['processed' => false, 'duplicate' => false];
        }

        $provider = (string) $payment->provider;
        $status = $this->gateways->for($provider)->getStatus($payment);

        return match ($provider) {
            'midtrans' => $this->midtransWebhooks->handleStatus($status),
            default => throw new PaymentGatewayException('Provider payment belum didukung.'),
        };
    }
}
