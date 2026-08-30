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

        $this->verifySignature($data, $serverKey);

        return $this->process($data);
    }

    /**
     * Process a server-to-server status response from Midtrans.
     *
     * @param  array<string, mixed>  $data
     * @return array{processed: bool, duplicate: bool, payment_id?: int, payment_status?: string, order_status?: string}
     */
    public function handleStatus(array $data): array
    {
        $serverKey = config('payments.midtrans.server_key');

        if (! is_string($serverKey) || trim($serverKey) === '') {
            throw new HttpException(503, 'Midtrans belum dikonfigurasi.');
        }

        if (array_key_exists('signature_key', $data)) {
            $this->verifySignature($data, $serverKey);
        }

        return $this->process($data);
    }

    /** @param array<string, mixed> $data */
    private function verifySignature(array $data, string $serverKey): void
    {
        $orderId = (string) ($data['order_id'] ?? '');
        $statusCode = (string) ($data['status_code'] ?? '');
        $grossAmount = (string) ($data['gross_amount'] ?? '');
        $signature = (string) ($data['signature_key'] ?? '');
        $expected = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

        if (! hash_equals($expected, $signature)) {
            throw new HttpException(401, 'Signature Midtrans tidak valid.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{processed: bool, duplicate: bool, payment_id?: int, payment_status?: string, order_status?: string}
     */
    private function process(array $data): array
    {
        $orderId = (string) ($data['order_id'] ?? '');
        $grossAmount = (string) ($data['gross_amount'] ?? '');

        if (! preg_match('/\A\d+(?:\.00)?\z/', $grossAmount)) {
            throw ValidationException::withMessages(['gross_amount' => 'Nominal Midtrans tidak valid.']);
        }

        $eventType = $this->eventType((string) ($data['transaction_status'] ?? ''), $data['fraud_status'] ?? null);

        if ($eventType === null) {
            return ['processed' => false, 'duplicate' => false];
        }

        $occurredAt = CarbonImmutable::parse(
            (string) ($data['settlement_time'] ?? $data['transaction_time'] ?? ''),
            'Asia/Jakarta',
        )->toIso8601String();
        $transactionId = (string) ($data['transaction_id'] ?? '');
        $transactionStatus = (string) ($data['transaction_status'] ?? '');
        $settlementTime = (string) ($data['settlement_time'] ?? '');
        $refundAmount = (string) ($data['refund_amount'] ?? '');

        return $this->payments->handle('midtrans', [
            'event_id' => 'midtrans-'.hash('sha256', $transactionId.'|'.$transactionStatus.'|'.$settlementTime.'|'.$refundAmount),
            'event_type' => $eventType,
            'provider_reference' => $orderId,
            'amount' => (int) explode('.', $grossAmount)[0],
            'currency' => strtoupper((string) ($data['currency'] ?? 'IDR')),
            'occurred_at' => $occurredAt,
            'metadata' => [
                'transaction_id' => $transactionId,
                'transaction_status' => $transactionStatus,
                'payment_type' => $data['payment_type'] ?? null,
                'status_code' => (string) ($data['status_code'] ?? ''),
                'fraud_status' => $data['fraud_status'] ?? null,
                'settlement_time' => $data['settlement_time'] ?? null,
                'refund_amount' => $data['refund_amount'] ?? null,
            ],
        ]);
    }

    private function eventType(string $status, mixed $fraudStatus): ?string
    {
        return match ($status) {
            'capture' => match ($fraudStatus) {
                'accept' => 'payment.paid',
                'deny' => 'payment.failed',
                default => null,
            },
            'settlement' => 'payment.paid',
            'deny', 'cancel', 'failure' => 'payment.failed',
            'expire' => 'payment.expired',
            'refund' => 'payment.refunded',
            'partial_refund' => 'payment.partially_refunded',
            default => null,
        };
    }
}
