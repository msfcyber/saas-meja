<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Events\OrderStatusUpdated;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class OrderStatusService
{
    public function __construct(
        private readonly TelemetryService $telemetry,
        private readonly AnalyticsEventService $analytics,
        private readonly AuditLogService $audits,
    ) {}

    public function transition(
        Order $order,
        OrderStatus $to,
        string $actorType = 'system',
        ?int $actorId = null,
        ?string $note = null,
    ): bool {
        $startedAt = hrtime(true);

        if ($to === OrderStatus::Paid && $actorType !== 'payment_webhook') {
            throw new ConflictHttpException('Order hanya dapat menjadi paid setelah payment terverifikasi.');
        }

        $transition = DB::transaction(function () use ($order, $to, $actorType, $actorId, $note): array {
            $lockedOrder = Order::withoutGlobalScopes()
                ->whereKey($order->getKey())
                ->where('tenant_id', $order->tenant_id)
                ->where('outlet_id', $order->outlet_id)
                ->lockForUpdate()
                ->firstOrFail();
            /** @var OrderStatus $from */
            $from = $lockedOrder->status;

            if ($from === $to) {
                return [false, $from];
            }

            if (! $from->canTransitionTo($to)) {
                throw new ConflictHttpException("Transisi order {$from->value} ke {$to->value} tidak diizinkan.");
            }

            $updates = ['status' => $to];

            if ($to === OrderStatus::Paid) {
                $updates['paid_at'] = now();
            }

            if ($to === OrderStatus::Completed) {
                $updates['completed_at'] = now();
            }

            $lockedOrder->update($updates);
            $this->record($lockedOrder, $from, $to, $actorType, $actorId, $note);
            $this->audits->record('order.status_changed', [
                'tenant_id' => (int) $lockedOrder->tenant_id,
                'outlet_id' => (int) $lockedOrder->outlet_id,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'auditable_type' => Order::class,
                'auditable_id' => (int) $lockedOrder->getKey(),
                'old_values' => ['status' => $from->value],
                'new_values' => ['status' => $to->value, 'note' => $note],
            ]);

            $analyticsEvent = match ($to) {
                OrderStatus::Accepted => 'order_accepted',
                OrderStatus::Preparing => 'order_preparing',
                OrderStatus::Ready => 'order_ready',
                OrderStatus::Served => 'order_served',
                OrderStatus::Completed => 'order_completed',
                default => null,
            };

            if ($analyticsEvent !== null) {
                $this->analytics->record($analyticsEvent, (int) $lockedOrder->tenant_id, (int) $lockedOrder->outlet_id, [
                    'order_id' => (int) $lockedOrder->getKey(),
                    'properties' => [
                        'from_status' => $from->value,
                        'to_status' => $to->value,
                    ],
                ]);
            }

            OrderStatusUpdated::dispatch(
                (int) $lockedOrder->getKey(),
                (int) $lockedOrder->tenant_id,
                (int) $lockedOrder->outlet_id,
                (string) $lockedOrder->access_token_hash,
            );

            $order->setRawAttributes($lockedOrder->getAttributes(), true);

            return [true, $from];
        }, attempts: 3);

        [$changed, $from] = $transition;

        if (! $changed) {
            return false;
        }

        $this->telemetry->recordDuration('order.status_changed', $startedAt, [
            'from_status' => $from->value,
            'to_status' => $to->value,
            'actor_type' => $actorType,
        ]);

        $createdAt = $order->getAttribute('created_at');

        if ($to === OrderStatus::Paid && $createdAt !== null) {
            $this->telemetry->record('order.payment_latency', [
                'latency_ms' => max(0, (int) CarbonImmutable::parse((string) $createdAt)->diffInMilliseconds(now())),
                'from_status' => $from->value,
                'to_status' => $to->value,
            ]);
        }

        return $changed;
    }

    public function record(
        Order $order,
        ?OrderStatus $from,
        OrderStatus $to,
        string $actorType = 'system',
        ?int $actorId = null,
        ?string $note = null,
    ): void {
        OrderStatusHistory::withoutGlobalScopes()->create([
            'tenant_id' => $order->tenant_id,
            'outlet_id' => $order->outlet_id,
            'order_id' => $order->getKey(),
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'note' => $note,
        ]);
    }
}
