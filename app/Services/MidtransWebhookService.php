<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class MidtransWebhookService
{
    public function __construct(private readonly PaymentWebhookService $payments) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{processed: bool, duplicate: bool, payment_id?: int, payment_status?: string, order_status?: string}
     */
    public function handle(array $data): array
    {
        $serverKey = config('payments.midtrans.server_key');

        if (! is_string($serverKey) || trim($serverKey) === '') {
            throw new HttpException(503, 'Midtrans belum dikonfigurasi.');
        }

        $orderId = (string) $data['order_id'];
        $statusCode = (string) $data['status_code'];
        $grossAmount = (string) $data['gross_amount'];
        $signature = (string) $data['signature_key'];
        $expected = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

        if (! hash_equals($expected, $signature)) {
            throw new HttpException(401, 'Signature Midtrans tidak valid.');
        }

        if (! preg_match('/\A\d+(?:\.00)?\z/', $grossAmount)) {
            throw ValidationException::withMessages(['gross_amount' => 'Nominal Midtrans tidak valid.']);
        }

        $eventType = $this->eventType((string) $data['transaction_status'], $data['fraud_status'] ?? null);

        if ($eventType === null) {
            return ['processed' => false, 'duplicate' => false];
        }

        $occurredAt = CarbonImmutable::parse(
            (string) ($data['settlement_time'] ?? $data['transaction_time']),
            'Asia/Jakarta',
        )->toIso8601String();

        return $this->payments->handle('midtrans', [
            'event_id' => (string) $data['transaction_id'].':'.(string) $data['transaction_status'],
            'event_type' => $eventType,
            'provider_reference' => $orderId,
            'amount' => (int) explode('.', $grossAmount)[0],
            'currency' => 'IDR',
            'occurred_at' => $occurredAt,
            'metadata' => [
                'transaction_id' => (string) $data['transaction_id'],
                'transaction_status' => (string) $data['transaction_status'],
                'payment_type' => $data['payment_type'] ?? null,
                'status_code' => $statusCode,
            ],
        ]);
    }

    private function eventType(string $status, mixed $fraudStatus): ?string
    {
        return match ($status) {
            'capture' => $fraudStatus === 'accept' ? 'payment.paid' : null,
            'settlement' => 'payment.paid',
            'deny', 'cancel', 'failure' => 'payment.failed',
            'expire' => 'payment.expired',
            'refund' => 'payment.refunded',
            'partial_refund' => 'payment.partially_refunded',
            default => null,
        };
    }
}
