<?php

namespace App\Services;

use App\Models\SaasInvoice;
use App\Models\Subscription;

interface SaasPaymentGateway
{
    /**
     * @return array{provider: string, redirect_url: string, expires_at: string|null}
     */
    public function createCheckout(SaasInvoice $invoice, Subscription $subscription, string $finishUrl): array;
}
