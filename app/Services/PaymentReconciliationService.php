<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Throwable;

final class PaymentReconciliationService
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly MidtransWebhookService $midtransWebhooks,
        private readonly TelemetryService $telemetry,
    ) {}

    /** @return array<string, mixed> */
    public function reconcile(Payment $payment): array
    {
        if ($payment->status !== PaymentStatus::Pending) {
            return ['processed' => false, 'duplicate' => false];
        }

        $provider = (string) $payment->provider;
        $startedAt = hrtime(true);

        try {
            $status = $this->gateways->for($provider)->getStatus($payment);

            $result = match ($provider) {
                'midtrans' => $this->midtransWebhooks->handleStatus($status),
                default => throw new PaymentGatewayException('Provider payment belum didukung.'),
            };
        } catch (Throwable $exception) {
            $this->telemetry->recordDuration('payment.reconciliation.failed', $startedAt, [
                'provider' => $provider,
                'exception' => $exception::class,
            ], 'warning');

            throw $exception;
        }

        $this->telemetry->recordDuration('payment.reconciliation.completed', $startedAt, [
            'provider' => $provider,
            'processed' => (bool) $result['processed'],
            'duplicate' => (bool) $result['duplicate'],
        ]);

        return $result;
    }
}
