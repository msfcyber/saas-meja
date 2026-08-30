<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Tenant;
use Throwable;

final class SubscriptionCheckoutService
{
    public function __construct(
        private readonly SubscriptionEntitlementService $entitlements,
        private readonly SaasInvoiceService $invoices,
        private readonly SaasPaymentGatewayManager $gateways,
        private readonly TelemetryService $telemetry,
    ) {}

    /**
     * @return array{invoice_id: int, provider: string, redirect_url: string, expires_at: string|null}
     */
    public function start(Tenant $tenant, string $finishUrl): array
    {
        $startedAt = hrtime(true);

        try {
            $subscription = $this->entitlements->current($tenant);

            if ($subscription === null || $subscription->status === SubscriptionStatus::Cancelled) {
                throw new PaymentGatewayException('Subscription tenant belum tersedia.');
            }

            $invoice = $this->invoices->pendingFor($subscription);
            $provider = (string) $invoice->provider;
            $checkout = $this->gateways->for($provider)
                ->createCheckout($invoice, $subscription, $finishUrl);

            $this->telemetry->recordDuration('subscription.checkout.completed', $startedAt, [
                'provider' => $provider,
            ]);

            return [
                'invoice_id' => (int) $invoice->getKey(),
                ...$checkout,
            ];
        } catch (Throwable $exception) {
            $this->telemetry->recordDuration('subscription.checkout.failed', $startedAt, [
                'exception' => $exception::class,
            ], 'warning');

            throw $exception;
        }
    }
}
