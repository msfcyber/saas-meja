<?php

namespace App\Services;

use App\Models\SaasInvoice;
use App\Models\Subscription;
use Illuminate\Support\Facades\Http;

final class MidtransSaasPaymentGateway implements SaasPaymentGateway
{
    /**
     * @return array{provider: string, redirect_url: string, expires_at: string|null}
     */
    public function createCheckout(SaasInvoice $invoice, Subscription $subscription, string $finishUrl): array
    {
        $metadata = is_array($invoice->metadata) ? $invoice->metadata : [];
        $midtransMetadata = is_array($metadata['midtrans'] ?? null) ? $metadata['midtrans'] : [];
        $existingUrl = $midtransMetadata['redirect_url'] ?? null;

        if (is_string($existingUrl) && $existingUrl !== '') {
            return $this->result($invoice, $existingUrl);
        }

        $serverKey = $this->serverKey();
        $providerReference = (string) $invoice->provider_reference;

        if ($providerReference === '') {
            throw new PaymentGatewayException('Referensi invoice subscription tidak tersedia.');
        }

        if ($invoice->currency !== 'IDR') {
            throw new PaymentGatewayException('Midtrans Snap saat ini hanya mendukung subscription IDR.');
        }

        $subscription->loadMissing(['plan', 'tenant']);
        $plan = $subscription->plan;

        if ($plan === null) {
            throw new PaymentGatewayException('Plan subscription tidak tersedia.');
        }

        $payload = [
            'transaction_details' => [
                'order_id' => $providerReference,
                'gross_amount' => (int) $invoice->amount,
            ],
            'item_details' => [[
                'id' => 'subscription-plan-'.$plan->getKey(),
                'price' => (int) $invoice->amount,
                'quantity' => 1,
                'name' => mb_strimwidth((string) $plan->name, 0, 50, ''),
            ]],
            'callbacks' => ['finish' => $finishUrl],
            'customer_details' => [
                'first_name' => mb_strimwidth((string) $subscription->tenant->name, 0, 50, ''),
            ],
        ];

        try {
            $response = Http::acceptJson()
                ->withBasicAuth($serverKey, '')
                ->retry([250, 750])
                ->timeout(10)
                ->post((string) config('payments.midtrans.snap_url'), $payload)
                ->throw()
                ->json();
        } catch (\Throwable $exception) {
            report($exception);

            throw new PaymentGatewayException('Sesi pembayaran subscription belum dapat dibuat.');
        }

        $token = is_array($response) ? ($response['token'] ?? null) : null;
        $redirectUrl = is_array($response) ? ($response['redirect_url'] ?? null) : null;

        if (! is_string($token) || ! is_string($redirectUrl) || $token === '' || $redirectUrl === '') {
            throw new PaymentGatewayException('Respons Midtrans tidak menyediakan sesi subscription.');
        }

        $metadata['midtrans'] = [
            'snap_token' => $token,
            'redirect_url' => $redirectUrl,
        ];
        $invoice->update(['metadata' => $metadata]);

        return $this->result($invoice, $redirectUrl);
    }

    /** @return array{provider: string, redirect_url: string, expires_at: string|null} */
    private function result(SaasInvoice $invoice, string $redirectUrl): array
    {
        return [
            'provider' => 'midtrans',
            'redirect_url' => $redirectUrl,
            'expires_at' => $invoice->due_at?->toIso8601String(),
        ];
    }

    private function serverKey(): string
    {
        $serverKey = config('payments.midtrans.server_key');

        if (! is_string($serverKey) || trim($serverKey) === '') {
            throw new PaymentGatewayException('Midtrans belum dikonfigurasi.');
        }

        return $serverKey;
    }
}
