<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Throwable;

final class PaymentCheckoutService
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly TelemetryService $telemetry,
    ) {}

    /**
     * @return array{provider: string, redirect_url: string, expires_at: string|null}
     */
    public function start(Payment $payment, Order $order, string $finishUrl): array
    {
        $provider = (string) $payment->provider;
        $startedAt = hrtime(true);

        try {
            $checkout = $this->gateways->for($provider)
                ->createCheckout($payment, $order, $finishUrl);
        } catch (Throwable $exception) {
            $this->telemetry->recordDuration('payment.checkout.failed', $startedAt, [
                'provider' => $provider,
                'exception' => $exception::class,
            ], 'warning');

            throw $exception;
        }

        $this->telemetry->recordDuration('payment.checkout.completed', $startedAt, [
            'provider' => $provider,
        ]);

        return $checkout;
    }
}
