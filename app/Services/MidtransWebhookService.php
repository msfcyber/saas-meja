<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentGatewayCredential;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class MidtransWebhookService
{
    public function __construct(
        private readonly PaymentWebhookService $payments,
        private readonly PaymentGatewayCredentialService $credentials,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{processed: bool, duplicate: bool, payment_id?: int, payment_status?: string, order_status?: string}
     */
    public function handle(array $data): array
    {
        [$payment, $credential] = $this->paymentCredential($data);
        $this->verifySignature($data, $this->secret($credential));
        $this->credentials->bind($payment, $credential);

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
        [$payment, $credential] = $this->paymentCredential($data);

        if (array_key_exists('signature_key', $data)) {
            $this->verifySignature($data, $this->secret($credential));
        }

        $this->credentials->bind($payment, $credential);

        return $this->process($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: Payment, 1: PaymentGatewayCredential}
     */
    private function paymentCredential(array $data): array
    {
        $providerReference = (string) ($data['order_id'] ?? '');

        if ($providerReference === '') {
            throw ValidationException::withMessages([
                'provider_reference' => 'Payment tidak ditemukan.',
            ]);
        }

        $payment = Payment::withoutGlobalScopes()
            ->where('provider', 'midtrans')
            ->where('provider_reference', $providerReference)
            ->first();

        if ($payment === null) {
            throw ValidationException::withMessages([
                'provider_reference' => 'Payment tidak ditemukan.',
            ]);
        }

        try {
            $credential = $this->credentials->forPayment($payment, bind: false);
        } catch (PaymentGatewayException $exception) {
            throw new HttpException(503, $exception->getMessage(), $exception);
        }

        return [$payment, $credential];
    }

    private function secret(PaymentGatewayCredential $credential): string
    {
        $secret = $credential->secret;

        if ($secret === '') {
            throw new HttpException(503, 'Midtrans belum dikonfigurasi.');
        }

        return $secret;
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
