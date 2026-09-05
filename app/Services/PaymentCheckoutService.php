<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Tenant;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

final class PaymentCheckoutService
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly TelemetryService $telemetry,
        private readonly SubscriptionEntitlementService $entitlements,
    ) {}

    /**
     * @return array{provider: string, redirect_url: string, expires_at: string|null}
     */
    public function start(Payment $payment, Order $order, string $finishUrl): array
    {
        $tenant = Tenant::query()->find($order->tenant_id);

        if ($tenant === null
            || ! $this->entitlements->hasFeature($tenant, SubscriptionEntitlementService::FEATURE_ONLINE_PAYMENT)) {
            throw new ConflictHttpException('Pembayaran online belum tersedia pada plan outlet ini.');
        }

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
