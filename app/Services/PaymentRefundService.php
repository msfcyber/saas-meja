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

    public function refund(Order $order, string $idempotencyKey, string $reason, ?int $actorId): PaymentRefund
    {
        $refund = DB::transaction(function () use ($order, $idempotencyKey, $reason, $actorId): PaymentRefund {
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

            if ($lockedOrder->status !== OrderStatus::Paid) {
                throw new ConflictHttpException('Hanya order berstatus paid yang dapat direfund.');
            }

            $payment = Payment::withoutGlobalScopes()
                ->where('tenant_id', $lockedOrder->tenant_id)
                ->where('outlet_id', $lockedOrder->outlet_id)
                ->where('order_id', $lockedOrder->getKey())
                ->where('status', PaymentStatus::Paid)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($payment === null) {
                throw new ConflictHttpException('Payment lunas untuk order ini tidak ditemukan.');
            }

            return PaymentRefund::withoutGlobalScopes()->create([
                'tenant_id' => $lockedOrder->tenant_id,
                'outlet_id' => $lockedOrder->outlet_id,
                'payment_id' => $payment->getKey(),
                'idempotency_key' => $idempotencyKey,
                'provider' => (string) $payment->provider,
                'provider_refund_key' => 'meja-refund-'.$payment->getKey().'-'.Str::uuid(),
                'status' => PaymentRefundStatus::Pending,
                'amount' => (int) $payment->amount,
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
            $this->markFailed($refund, $message);

            if ($exception instanceof PaymentGatewayException) {
                throw $exception;
            }

            throw new PaymentGatewayException($message, previous: $exception);
        }

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

            if ($lockedPayment->status !== PaymentStatus::Paid || $lockedOrder->status !== OrderStatus::Paid) {
                throw new ConflictHttpException('Payment atau order berubah sebelum refund selesai diproses.');
            }

            $this->statuses->transition(
                $lockedOrder,
                OrderStatus::Refunded,
                'user',
                $lockedRefund->requested_by,
                'Refund manual: '.$lockedRefund->reason,
            );
            $metadata = is_array($lockedPayment->metadata) ? $lockedPayment->metadata : [];
            $metadata['refund'] = [
                'id' => (int) $lockedRefund->getKey(),
                'amount' => (int) $lockedRefund->amount,
                'provider_reference' => $result['provider_reference'],
                'completed_at' => now()->toIso8601String(),
            ];
            $lockedPayment->update([
                'status' => PaymentStatus::Refunded,
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
                    'payment_status' => PaymentStatus::Refunded->value,
                    'order_status' => OrderStatus::Refunded->value,
                    'amount' => (int) $lockedRefund->amount,
                    'provider' => $lockedRefund->provider,
                ],
            ]);

            return $lockedRefund;
        }, attempts: 3);
    }

    private function markFailed(PaymentRefund $refund, string $message): void
    {
        PaymentRefund::withoutGlobalScopes()
            ->whereKey($refund->getKey())
            ->update([
                'status' => PaymentRefundStatus::Failed,
                'failure_reason' => $message,
            ]);
        $this->audits->record('payment.refund_failed', [
            'tenant_id' => (int) $refund->tenant_id,
            'outlet_id' => (int) $refund->outlet_id,
            'actor_type' => 'user',
            'actor_id' => $refund->requested_by,
            'auditable_type' => PaymentRefund::class,
            'auditable_id' => (int) $refund->getKey(),
            'new_values' => [
                'status' => PaymentRefundStatus::Failed->value,
                'reason' => $message,
            ],
        ]);
    }
}
