<?php

namespace App\Services;

use App\Enums\SaasInvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\SaasInvoice;
use App\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class MidtransSubscriptionWebhookService
{
    public function __construct(private readonly AuditLogService $audits) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function handle(array $data): array
    {
        $serverKey = $this->serverKey();
        $this->verifySignature($data, $serverKey);

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
        );
        $transactionId = (string) ($data['transaction_id'] ?? '');
        $transactionStatus = (string) ($data['transaction_status'] ?? '');
        $settlementTime = (string) ($data['settlement_time'] ?? '');
        $refundAmount = (string) ($data['refund_amount'] ?? '');
        $eventId = 'midtrans-subscription-'.hash('sha256', $transactionId.'|'.$transactionStatus.'|'.$settlementTime.'|'.$refundAmount);

        return DB::transaction(function () use ($data, $grossAmount, $eventType, $occurredAt, $eventId): array {
            $providerReference = (string) ($data['order_id'] ?? '');
            $invoice = SaasInvoice::withoutGlobalScopes()
                ->where('provider_reference', $providerReference)
                ->lockForUpdate()
                ->first();

            if ($invoice === null) {
                throw ValidationException::withMessages(['provider_reference' => 'Invoice subscription tidak ditemukan.']);
            }

            if ((int) $invoice->amount !== (int) explode('.', $grossAmount)[0]) {
                throw ValidationException::withMessages(['amount' => 'Nominal invoice subscription tidak sesuai.']);
            }

            $currency = strtoupper((string) ($data['currency'] ?? 'IDR'));

            if (strtoupper((string) $invoice->currency) !== $currency) {
                throw ValidationException::withMessages(['currency' => 'Currency invoice subscription tidak sesuai.']);
            }

            $metadata = is_array($invoice->metadata) ? $invoice->metadata : [];
            $events = is_array($metadata['webhook_events'] ?? null) ? $metadata['webhook_events'] : [];

            if (array_key_exists($eventId, $events)) {
                return $this->result($invoice, true);
            }

            $lastWebhookAt = $metadata['last_webhook_at'] ?? null;
            $isNewer = ! is_string($lastWebhookAt)
                || $occurredAt->isAfter(CarbonImmutable::parse($lastWebhookAt));
            $target = $this->targetStatus($eventType);
            $previousInvoiceStatus = $invoice->status;
            $previousSubscriptionStatus = null;

            if ($isNewer && $this->canTransition($invoice->status, $target)) {
                $previousSubscriptionStatus = $this->applyStatus($invoice, $target, $occurredAt);
                $metadata['last_webhook_at'] = $occurredAt->toIso8601String();
            }

            $events[$eventId] = [
                'type' => $eventType,
                'occurred_at' => $occurredAt->toIso8601String(),
            ];
            $metadata['webhook_events'] = count($events) > 20
                ? array_slice($events, -20, null, true)
                : $events;
            $invoice->metadata = $metadata;
            $invoice->save();

            if ($previousInvoiceStatus !== $invoice->status) {
                $oldValues = ['invoice_status' => $previousInvoiceStatus->value];
                $newValues = [
                    'invoice_status' => $invoice->status->value,
                    'event_type' => $eventType,
                ];

                if ($previousSubscriptionStatus !== null) {
                    $oldValues['subscription_status'] = $previousSubscriptionStatus->value;
                    $newValues['subscription_status'] = SubscriptionStatus::Active->value;
                }

                $this->audits->record('subscription.invoice_status_changed', [
                    'tenant_id' => (int) $invoice->tenant_id,
                    'actor_type' => 'system',
                    'auditable_type' => SaasInvoice::class,
                    'auditable_id' => (int) $invoice->getKey(),
                    'old_values' => $oldValues,
                    'new_values' => $newValues,
                ]);
            }

            return $this->result($invoice, false);
        }, attempts: 3);
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

    private function serverKey(): string
    {
        $serverKey = config('payments.midtrans.server_key');

        if (! is_string($serverKey) || trim($serverKey) === '') {
            throw new HttpException(503, 'Midtrans belum dikonfigurasi.');
        }

        return $serverKey;
    }

    private function eventType(string $status, mixed $fraudStatus): ?string
    {
        return match ($status) {
            'capture' => match ($fraudStatus) {
                'accept' => 'subscription.paid',
                'deny' => 'subscription.failed',
                default => null,
            },
            'settlement' => 'subscription.paid',
            'deny', 'cancel', 'failure' => 'subscription.failed',
            'expire' => 'subscription.expired',
            'refund' => 'subscription.refunded',
            default => null,
        };
    }

    private function targetStatus(string $eventType): SaasInvoiceStatus
    {
        return match ($eventType) {
            'subscription.paid' => SaasInvoiceStatus::Paid,
            'subscription.failed' => SaasInvoiceStatus::Failed,
            'subscription.expired' => SaasInvoiceStatus::Expired,
            'subscription.refunded' => SaasInvoiceStatus::Refunded,
            default => throw new HttpException(409, 'Tipe event subscription tidak didukung.'),
        };
    }

    private function canTransition(SaasInvoiceStatus $from, SaasInvoiceStatus $to): bool
    {
        return match ($from) {
            SaasInvoiceStatus::Pending => in_array($to, [SaasInvoiceStatus::Paid, SaasInvoiceStatus::Failed, SaasInvoiceStatus::Expired], true),
            SaasInvoiceStatus::Paid => $to === SaasInvoiceStatus::Refunded,
            SaasInvoiceStatus::Failed, SaasInvoiceStatus::Expired, SaasInvoiceStatus::Refunded, SaasInvoiceStatus::Void => false,
        };
    }

    private function applyStatus(SaasInvoice $invoice, SaasInvoiceStatus $target, CarbonImmutable $occurredAt): ?SubscriptionStatus
    {
        $previousSubscriptionStatus = null;

        if ($target === SaasInvoiceStatus::Paid) {
            $subscription = Subscription::withoutGlobalScopes()
                ->whereKey($invoice->subscription_id)
                ->where('tenant_id', $invoice->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();
            $previousSubscriptionStatus = $subscription->status;
            $periodStartsAt = $invoice->period_starts_at ?? $occurredAt;
            $periodEndsAt = $invoice->period_ends_at ?? $periodStartsAt->addMonth();

            $subscription->update([
                'status' => SubscriptionStatus::Active,
                'provider' => $invoice->provider,
                'provider_reference' => $invoice->provider_reference,
                'trial_ends_at' => null,
                'current_period_starts_at' => $periodStartsAt,
                'current_period_ends_at' => $periodEndsAt,
                'cancelled_at' => null,
                'suspended_at' => null,
            ]);
            $invoice->paid_at = $occurredAt;
        }

        $invoice->status = $target;

        return $previousSubscriptionStatus;
    }

    /** @return array<string, mixed> */
    private function result(SaasInvoice $invoice, bool $duplicate): array
    {
        $subscription = Subscription::withoutGlobalScopes()->find($invoice->subscription_id);

        return [
            'processed' => true,
            'duplicate' => $duplicate,
            'invoice_id' => (int) $invoice->getKey(),
            'invoice_status' => $invoice->status->value,
            'subscription_status' => $subscription?->status->value,
        ];
    }
}
