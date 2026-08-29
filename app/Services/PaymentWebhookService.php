<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class PaymentWebhookService
{
    public function __construct(private readonly OrderStatusService $statuses) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{processed: bool, duplicate: bool, payment_id: int, payment_status: string, order_status: string}
     */
    public function handle(string $provider, array $data): array
    {
        $provider = strtolower(trim($provider));
        $eventId = (string) $data['event_id'];
        $occurredAt = CarbonImmutable::parse((string) $data['occurred_at']);
        $payloadHash = $this->payloadHash($data);

        return DB::transaction(function () use ($provider, $eventId, $occurredAt, $payloadHash, $data): array {
            $payment = Payment::withoutGlobalScopes()
                ->where('provider_reference', (string) $data['provider_reference'])
                ->where(function (Builder $query) use ($provider): void {
                    $query->whereNull('provider')->orWhere('provider', $provider);
                })
                ->lockForUpdate()
                ->first();

            if ($payment === null) {
                $this->invalid('provider_reference', 'Payment tidak ditemukan.');
            }

            if ((int) $payment->amount !== (int) $data['amount']) {
                $this->invalid('amount', 'Nominal payment tidak sesuai dengan order.');
            }

            if (strtoupper((string) $payment->currency) !== (string) $data['currency']) {
                $this->invalid('currency', 'Currency payment tidak sesuai dengan order.');
            }

            $existing = PaymentEvent::withoutGlobalScopes()
                ->where('provider', $provider)
                ->where('event_id', $eventId)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if (! hash_equals((string) $existing->payload_hash, $payloadHash)) {
                    throw new ConflictHttpException('Event payment yang sama memiliki payload berbeda.');
                }

                return $this->result($payment, true);
            }

            $event = PaymentEvent::withoutGlobalScopes()->create([
                'tenant_id' => $payment->tenant_id,
                'outlet_id' => $payment->outlet_id,
                'payment_id' => $payment->getKey(),
                'provider' => $provider,
                'event_id' => $eventId,
                'event_type' => (string) $data['event_type'],
                'amount' => (int) $data['amount'],
                'currency' => (string) $data['currency'],
                'occurred_at' => $occurredAt,
                'payload_hash' => $payloadHash,
                'payload' => $data,
            ]);

            $order = Order::withoutGlobalScopes()
                ->whereKey($payment->order_id)
                ->where('tenant_id', $payment->tenant_id)
                ->where('outlet_id', $payment->outlet_id)
                ->lockForUpdate()
                ->firstOrFail();
            $target = $this->targetStatus((string) $data['event_type']);
            $isNewer = $payment->last_webhook_at === null
                || $occurredAt->isAfter(CarbonImmutable::parse((string) $payment->last_webhook_at));

            $payment->provider = $provider;

            if ($isNewer) {
                if ($this->canTransitionPayment($payment->status, $target)) {
                    if ($this->applyPaymentStatus($payment, $order, $target)) {
                        $payment->last_webhook_at = $occurredAt;
                    }
                }
            }

            $payment->save();
            $event->update(['processed_at' => now()]);

            return $this->result($payment, false);
        }, attempts: 3);
    }

    private function applyPaymentStatus(Payment $payment, Order $order, PaymentStatus $target): bool
    {
        if ($target === PaymentStatus::Paid) {
            if ($order->status === OrderStatus::AwaitingPayment) {
                $this->statuses->transition($order, OrderStatus::Paid, 'payment_webhook');
            } elseif ($order->status !== OrderStatus::Paid) {
                return false;
            }

            $payment->paid_at = now();
        }

        if ($target === PaymentStatus::Expired && $order->status === OrderStatus::AwaitingPayment) {
            $this->statuses->transition($order, OrderStatus::PaymentExpired, 'payment_webhook');
        }

        if ($target === PaymentStatus::Refunded && $order->status === OrderStatus::Paid) {
            $this->statuses->transition($order, OrderStatus::Refunded, 'payment_webhook');
        }

        $payment->status = $target;

        return true;
    }

    private function canTransitionPayment(PaymentStatus $from, PaymentStatus $to): bool
    {
        return match ($from) {
            PaymentStatus::Pending => in_array($to, [PaymentStatus::Paid, PaymentStatus::Failed, PaymentStatus::Expired], true),
            PaymentStatus::Paid => in_array($to, [PaymentStatus::PartiallyRefunded, PaymentStatus::Refunded], true),
            PaymentStatus::PartiallyRefunded => $to === PaymentStatus::Refunded,
            PaymentStatus::Failed, PaymentStatus::Expired, PaymentStatus::Refunded => false,
        };
    }

    private function targetStatus(string $eventType): PaymentStatus
    {
        return match ($eventType) {
            'payment.paid' => PaymentStatus::Paid,
            'payment.failed' => PaymentStatus::Failed,
            'payment.expired' => PaymentStatus::Expired,
            'payment.refunded' => PaymentStatus::Refunded,
            'payment.partially_refunded' => PaymentStatus::PartiallyRefunded,
            default => throw new ConflictHttpException('Tipe event payment tidak didukung.'),
        };
    }

    /** @param array<string, mixed> $data */
    private function payloadHash(array $data): string
    {
        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    /** @return array{processed: bool, duplicate: bool, payment_id: int, payment_status: string, order_status: string} */
    private function result(Payment $payment, bool $duplicate): array
    {
        $order = Order::withoutGlobalScopes()->findOrFail($payment->order_id);

        return [
            'processed' => true,
            'duplicate' => $duplicate,
            'payment_id' => (int) $payment->getKey(),
            'payment_status' => $payment->status->value,
            'order_status' => $order->status->value,
        ];
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
