<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;

final class MidtransPaymentGateway implements PaymentGateway
{
    /**
     * @return array{provider: string, redirect_url: string, expires_at: string|null}
     */
    public function createCheckout(Payment $payment, Order $order, string $finishUrl): array
    {
        $metadata = $payment->metadata ?? [];
        $midtransMetadata = $metadata['midtrans'] ?? null;
        $existingUrl = is_array($midtransMetadata) ? ($midtransMetadata['redirect_url'] ?? null) : null;

        if (is_string($existingUrl) && $existingUrl !== '') {
            return $this->result($payment, $existingUrl);
        }

        $serverKey = config('payments.midtrans.server_key');

        if (! is_string($serverKey) || trim($serverKey) === '') {
            throw new PaymentGatewayException('Midtrans belum dikonfigurasi.');
        }

        if ($payment->provider_reference === null || $payment->provider_reference === '') {
            throw new PaymentGatewayException('Referensi payment Midtrans tidak tersedia.');
        }

        if ($payment->currency !== 'IDR') {
            throw new PaymentGatewayException('Midtrans Snap saat ini hanya mendukung payment IDR.');
        }

        $order->loadMissing('items');
        $itemDetails = $order->items->map(fn ($item) => [
            'id' => 'order-item-'.$item->getKey(),
            'price' => (int) $item->unit_price,
            'quantity' => (int) $item->quantity,
            'name' => mb_strimwidth((string) $item->product_name_snapshot, 0, 50, ''),
        ])->values()->all();
        $itemsTotal = array_sum(array_map(
            static fn (array $item): int => $item['price'] * $item['quantity'],
            $itemDetails,
        ));
        $adjustment = (int) $payment->amount - $itemsTotal;

        if ($adjustment !== 0) {
            $itemDetails[] = [
                'id' => 'order-adjustment-'.$order->getKey(),
                'price' => $adjustment,
                'quantity' => 1,
                'name' => 'Pajak dan penyesuaian',
            ];
        }

        $payload = [
            'transaction_details' => [
                'order_id' => $payment->provider_reference,
                'gross_amount' => (int) $payment->amount,
            ],
            'item_details' => $itemDetails,
            'callbacks' => ['finish' => $finishUrl],
        ];

        if ($order->customer_name !== null && $order->customer_name !== '') {
            $payload['customer_details'] = ['first_name' => $order->customer_name];
        }

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

            throw new PaymentGatewayException('Sesi pembayaran belum dapat dibuat.');
        }

        $token = is_array($response) ? ($response['token'] ?? null) : null;
        $redirectUrl = is_array($response) ? ($response['redirect_url'] ?? null) : null;

        if (! is_string($token) || ! is_string($redirectUrl) || $token === '' || $redirectUrl === '') {
            throw new PaymentGatewayException('Respons Midtrans tidak menyediakan sesi pembayaran.');
        }

        $metadata['midtrans'] = [
            'snap_token' => $token,
            'redirect_url' => $redirectUrl,
        ];
        $payment->update(['metadata' => $metadata]);

        return $this->result($payment, $redirectUrl);
    }

    /** @return array<string, mixed> */
    public function getStatus(Payment $payment): array
    {
        $serverKey = $this->serverKey();
        $providerReference = (string) $payment->provider_reference;

        if ($providerReference === '') {
            throw new PaymentGatewayException('Referensi payment Midtrans tidak tersedia.');
        }

        if ($payment->currency !== 'IDR') {
            throw new PaymentGatewayException('Midtrans Snap saat ini hanya mendukung payment IDR.');
        }

        $url = rtrim((string) config('payments.midtrans.status_url'), '/').'/'.rawurlencode($providerReference).'/status';

        try {
            $response = Http::acceptJson()
                ->withBasicAuth($serverKey, '')
                ->retry([250, 750])
                ->timeout(10)
                ->get($url)
                ->throw()
                ->json();
        } catch (\Throwable $exception) {
            report($exception);

            throw new PaymentGatewayException('Status pembayaran belum dapat diperiksa.');
        }

        if (! is_array($response)
            || ($response['order_id'] ?? null) !== $providerReference
            || ! is_string($response['transaction_id'] ?? null)
            || ! is_string($response['transaction_status'] ?? null)
            || ! is_string($response['status_code'] ?? null)
            || ! is_string($response['gross_amount'] ?? null)
            || ! is_string($response['transaction_time'] ?? null)) {
            throw new PaymentGatewayException('Respons status Midtrans tidak valid.');
        }

        return $response;
    }

    private function serverKey(): string
    {
        $serverKey = config('payments.midtrans.server_key');

        if (! is_string($serverKey) || trim($serverKey) === '') {
            throw new PaymentGatewayException('Midtrans belum dikonfigurasi.');
        }

        return $serverKey;
    }

    /**
     * @return array{provider: string, redirect_url: string, expires_at: string|null}
     */
    private function result(Payment $payment, string $redirectUrl): array
    {
        return [
            'provider' => 'midtrans',
            'redirect_url' => $redirectUrl,
            'expires_at' => $payment->expires_at?->toIso8601String(),
        ];
    }
}
