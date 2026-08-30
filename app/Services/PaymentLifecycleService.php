<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class PaymentLifecycleService
{
    private const PAYMENT_LIFETIME_MINUTES = 15;

    public function __construct(
        private readonly OrderStatusService $statuses,
        private readonly AuditLogService $audits,
    ) {}

    public function expireIfDue(Payment $payment): bool
    {
        return DB::transaction(function () use ($payment): bool {
            $lockedPayment = Payment::withoutGlobalScopes()
                ->whereKey($payment->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $order = Order::withoutGlobalScopes()
                ->whereKey($lockedPayment->order_id)
                ->where('tenant_id', $lockedPayment->tenant_id)
                ->where('outlet_id', $lockedPayment->outlet_id)
                ->lockForUpdate()
                ->firstOrFail();

            return $this->expireLocked($lockedPayment, $order);
        }, attempts: 3);
    }

    public function expireDue(int $limit = 100): int
    {
        $paymentIds = Payment::withoutGlobalScopes()
            ->where('status', PaymentStatus::Pending)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');
        $expired = 0;

        foreach ($paymentIds as $paymentId) {
            $payment = Payment::withoutGlobalScopes()->whereKey($paymentId)->first();

            if ($payment !== null && $this->expireIfDue($payment)) {
                $expired++;
            }
        }

        return $expired;
    }

    public function paymentForCheckout(Order $order): Payment
    {
        return DB::transaction(function () use ($order): Payment {
            $lockedOrder = Order::withoutGlobalScopes()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $payment = Payment::withoutGlobalScopes()
                ->where('order_id', $lockedOrder->getKey())
                ->latest('id')
                ->lockForUpdate()
                ->firstOrFail();

            $this->expireLocked($payment, $lockedOrder);

            if ($payment->status === PaymentStatus::Pending) {
                return $payment;
            }

            if ($payment->status !== PaymentStatus::Expired || $lockedOrder->status !== OrderStatus::PaymentExpired) {
                throw new ConflictHttpException('Payment ini tidak lagi menunggu pembayaran.');
            }

            $replacement = Payment::withoutGlobalScopes()->create([
                'tenant_id' => $lockedOrder->tenant_id,
                'outlet_id' => $lockedOrder->outlet_id,
                'order_id' => $lockedOrder->getKey(),
                'method' => $payment->method,
                'status' => PaymentStatus::Pending,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'provider' => $payment->provider,
                'expires_at' => now()->addMinutes(self::PAYMENT_LIFETIME_MINUTES),
            ]);
            $replacement->update([
                'provider_reference' => 'meja-payment-'.$replacement->getKey(),
            ]);
            $this->statuses->transition(
                $lockedOrder,
                OrderStatus::AwaitingPayment,
                'customer',
                note: 'Membuat payment pengganti setelah payment kedaluwarsa.',
            );
            $this->audits->record('payment.retried', [
                'tenant_id' => (int) $replacement->tenant_id,
                'outlet_id' => (int) $replacement->outlet_id,
                'actor_type' => 'customer',
                'auditable_type' => Payment::class,
                'auditable_id' => (int) $replacement->getKey(),
                'old_values' => [
                    'payment_id' => (int) $payment->getKey(),
                    'status' => $payment->status->value,
                ],
                'new_values' => [
                    'status' => $replacement->status->value,
                    'order_id' => (int) $lockedOrder->getKey(),
                ],
            ]);

            return $replacement;
        }, attempts: 3);
    }

    public function expireLocked(Payment $payment, Order $order): bool
    {
        if ($payment->status !== PaymentStatus::Pending
            || $payment->expires_at === null
            || $payment->expires_at->isFuture()) {
            return false;
        }

        $previousOrderStatus = $order->status;
        $payment->update(['status' => PaymentStatus::Expired]);

        if ($order->status === OrderStatus::AwaitingPayment) {
            $this->statuses->transition($order, OrderStatus::PaymentExpired, 'payment_expiry');
        }

        $this->audits->record('payment.status_changed', [
            'tenant_id' => (int) $payment->tenant_id,
            'outlet_id' => (int) $payment->outlet_id,
            'actor_type' => 'system',
            'auditable_type' => Payment::class,
            'auditable_id' => (int) $payment->getKey(),
            'old_values' => [
                'status' => PaymentStatus::Pending->value,
                'order_status' => $previousOrderStatus->value,
            ],
            'new_values' => [
                'status' => PaymentStatus::Expired->value,
                'order_status' => $order->status->value,
                'event_type' => 'payment.expired',
                'provider' => $payment->provider,
            ],
        ]);

        return true;
    }
}
