<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Tenant;

final class SubscriptionCheckoutService
{
    public function __construct(
        private readonly SubscriptionEntitlementService $entitlements,
        private readonly SaasInvoiceService $invoices,
        private readonly SaasPaymentGatewayManager $gateways,
    ) {}

    /**
     * @return array{invoice_id: int, provider: string, redirect_url: string, expires_at: string|null}
     */
    public function start(Tenant $tenant, string $finishUrl): array
    {
        $subscription = $this->entitlements->current($tenant);

        if ($subscription === null || $subscription->status === SubscriptionStatus::Cancelled) {
            throw new PaymentGatewayException('Subscription tenant belum tersedia.');
        }

        $invoice = $this->invoices->pendingFor($subscription);
        $checkout = $this->gateways->for((string) $invoice->provider)
            ->createCheckout($invoice, $subscription, $finishUrl);

        return [
            'invoice_id' => (int) $invoice->getKey(),
            ...$checkout,
        ];
    }
}
