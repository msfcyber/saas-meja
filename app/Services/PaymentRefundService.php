<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentRefundStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentRefund;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

final class PaymentRefundService
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly OrderStatusService $statuses,
        private readonly AuditLogService $audits,
    ) {}

    public function refund(Order $order, string $idempotencyKey, string $reason, ?int $actorId, ?int $amount = null): PaymentRefund
    {
        $refund = DB::transaction(function () use ($order, $idempotencyKey, $reason, $actorId, $amount): PaymentRefund {
            $existing = PaymentRefund::withoutGlobalScopes()
                ->where('tenant_id', $order->tenant_id)
                ->where('outlet_id', $order->outlet_id)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $lockedOrder = Order::withoutGlobalScopes()
                ->whereKey($order->getKey())
                ->where('tenant_id', $order->tenant_id)
                ->where('outlet_id', $order->outlet_id)
                ->lockForUpdate()
                ->firstOrFail();

            $payment = Payment::withoutGlobalScopes()
                ->where('tenant_id', $lockedOrder->tenant_id)
                ->where('outlet_id', $lockedOrder->outlet_id)
                ->where('order_id', $lockedOrder->getKey())
                ->whereIn('status', [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded])
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($payment === null) {
                throw new ConflictHttpException('Payment lunas untuk order ini tidak ditemukan.');
            }

            $activeRefund = PaymentRefund::withoutGlobalScopes()
                ->where('payment_id', $payment->getKey())
                ->where('status', PaymentRefundStatus::Pending)
                ->lockForUpdate()
                ->first();

            if ($activeRefund !== null) {
                if ($activeRefund->idempotency_key === $idempotencyKey) {
                    return $activeRefund;
                }

                throw new ConflictHttpException('Refund untuk payment ini sedang atau sudah diproses.');
            }

            $refundedAmount = (int) PaymentRefund::withoutGlobalScopes()
                ->where('payment_id', $payment->getKey())
                ->where('status', PaymentRefundStatus::Succeeded)
                ->sum('amount');
            $remainingAmount = (int) $payment->amount - $refundedAmount;

            if ($remainingAmount < 1) {
                throw new ConflictHttpException('Payment ini sudah direfund penuh.');
            }

            $refundAmount = $amount ?? $remainingAmount;

            if ($refundAmount < 1 || $refundAmount > $remainingAmount) {
                throw new ConflictHttpException('Nominal refund melebihi sisa payment.');
            }

            return PaymentRefund::withoutGlobalScopes()->create([
                'tenant_id' => $lockedOrder->tenant_id,
                'outlet_id' => $lockedOrder->outlet_id,
                'payment_id' => $payment->getKey(),
                'idempotency_key' => $idempotencyKey,
                'provider' => (string) $payment->provider,
                'provider_refund_key' => 'meja-refund-'.$payment->getKey().'-'.Str::uuid(),
                'status' => PaymentRefundStatus::Pending,
                'amount' => $refundAmount,
                'currency' => (string) $payment->currency,
                'reason' => $reason,
                'requested_by' => $actorId,
                'requested_at' => now(),
            ]);
        }, attempts: 3);

        if ($refund->status === PaymentRefundStatus::Succeeded) {
            return $refund;
        }

        if ($refund->status === PaymentRefundStatus::Failed) {
            throw new PaymentGatewayException($refund->failure_reason ?? 'Refund sebelumnya gagal diproses.');
        }

        $payment = Payment::withoutGlobalScopes()
            ->whereKey($refund->payment_id)
            ->where('tenant_id', $refund->tenant_id)
            ->where('outlet_id', $refund->outlet_id)
            ->firstOrFail();

        try {
            $result = $this->gateways->for((string) $refund->provider)->refund(
                $payment,
                (int) $refund->amount,
                (string) $refund->provider_refund_key,
                (string) $refund->reason,
            );
        } catch (Throwable $exception) {
            $message = $exception instanceof PaymentGatewayException
                ? $exception->getMessage()
                : 'Refund belum dapat diproses.';
            report($exception);
            $this->audits->record('payment.refund_reconciliation_required', [
                'tenant_id' => (int) $refund->tenant_id,
                'outlet_id' => (int) $refund->outlet_id,
                'actor_type' => 'system',
                'auditable_type' => PaymentRefund::class,
                'auditable_id' => (int) $refund->getKey(),
                'new_values' => ['reason' => $message],
            ]);

            if ($exception instanceof PaymentGatewayException) {
                throw $exception;
            }

            throw new PaymentGatewayException($message, previous: $exception);
        }

        return $this->complete($refund, $payment, $result);
    }

    public function reconcilePending(int $limit = 100): int
    {
        $refunds = PaymentRefund::withoutGlobalScopes()
            ->where('status', PaymentRefundStatus::Pending)
            ->where('provider', 'midtrans')
            ->orderBy('id')
            ->limit(max(1, min(500, $limit)))
            ->get();
        $resolved = 0;

        foreach ($refunds as $refund) {
            $payment = Payment::withoutGlobalScopes()->find($refund->payment_id);

            if ($payment === null) {
                continue;
            }

            try {
                $status = $this->gateways->for('midtrans')->getStatus($payment);
            } catch (Throwable $exception) {
                report($exception);

                continue;
            }

            $providerRefund = null;
            $providerRefunds = $status['refunds'] ?? [];

            if (is_array($providerRefunds)) {
                foreach ($providerRefunds as $entry) {
                    if (is_array($entry) && ($entry['refund_key'] ?? null) === $refund->provider_refund_key) {
                        $providerRefund = $entry;

                        break;
                    }
                }
            }

            if (! is_array($providerRefund)) {
                continue;
            }

            $this->complete($refund, $payment, [
                'provider_reference' => (string) ($providerRefund['refund_chargeback_id'] ?? $providerRefund['refund_chargeback_uuid'] ?? ''),
                'response' => $status,
            ]);
            $resolved++;
        }

        return $resolved;
    }

    /** @param array{provider_reference: string|null, response: array<string, mixed>} $result */
    private function complete(PaymentRefund $refund, Payment $payment, array $result): PaymentRefund
    {
        return DB::transaction(function () use ($refund, $payment, $result): PaymentRefund {
            $lockedRefund = PaymentRefund::withoutGlobalScopes()
                ->whereKey($refund->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedPayment = Payment::withoutGlobalScopes()
                ->whereKey($payment->getKey())
                ->where('tenant_id', $refund->tenant_id)
                ->where('outlet_id', $refund->outlet_id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedOrder = Order::withoutGlobalScopes()
                ->whereKey($lockedPayment->order_id)
                ->where('tenant_id', $refund->tenant_id)
                ->where('outlet_id', $refund->outlet_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRefund->status === PaymentRefundStatus::Succeeded) {
                return $lockedRefund;
            }

            if (! in_array($lockedPayment->status, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded], true)) {
                throw new ConflictHttpException('Payment atau order berubah sebelum refund selesai diproses.');
            }

            $previousRefundedAmount = (int) PaymentRefund::withoutGlobalScopes()
                ->where('payment_id', $lockedPayment->getKey())
                ->where('status', PaymentRefundStatus::Succeeded)
                ->whereKeyNot($lockedRefund->getKey())
                ->sum('amount');
            $refundedAmount = $previousRefundedAmount + (int) $lockedRefund->amount;

            if ($refundedAmount > (int) $lockedPayment->amount) {
                throw new ConflictHttpException('Nominal refund melebihi payment.');
            }

            $isFullRefund = $refundedAmount === (int) $lockedPayment->amount;

            if ($isFullRefund && $lockedOrder->status !== OrderStatus::Refunded) {
                $this->statuses->transition(
                    $lockedOrder,
                    OrderStatus::Refunded,
                    'user',
                    $lockedRefund->requested_by,
                    'Refund manual: '.$lockedRefund->reason,
                );
            }
            $metadata = is_array($lockedPayment->metadata) ? $lockedPayment->metadata : [];
            $metadata['refund'] = [
                'id' => (int) $lockedRefund->getKey(),
                'amount' => $refundedAmount,
                'provider_reference' => $result['provider_reference'],
                'completed_at' => now()->toIso8601String(),
            ];
            $lockedPayment->update([
                'status' => $isFullRefund ? PaymentStatus::Refunded : PaymentStatus::PartiallyRefunded,
                'metadata' => $metadata,
            ]);
            $lockedRefund->update([
                'status' => PaymentRefundStatus::Succeeded,
                'provider_reference' => $result['provider_reference'],
                'provider_response' => $result['response'],
                'completed_at' => now(),
                'failure_reason' => null,
            ]);
            $this->audits->record('payment.refunded', [
                'tenant_id' => (int) $lockedRefund->tenant_id,
                'outlet_id' => (int) $lockedRefund->outlet_id,
                'actor_type' => 'user',
                'actor_id' => $lockedRefund->requested_by,
                'auditable_type' => PaymentRefund::class,
                'auditable_id' => (int) $lockedRefund->getKey(),
                'old_values' => [
                    'payment_status' => PaymentStatus::Paid->value,
                    'order_status' => OrderStatus::Paid->value,
                ],
                'new_values' => [
                    'payment_status' => ($isFullRefund ? PaymentStatus::Refunded : PaymentStatus::PartiallyRefunded)->value,
                    'order_status' => $lockedOrder->status->value,
                    'amount' => (int) $lockedRefund->amount,
                    'refunded_amount' => $refundedAmount,
                    'provider' => $lockedRefund->provider,
                ],
            ]);

            return $lockedRefund;
        }, attempts: 3);
    }
}
