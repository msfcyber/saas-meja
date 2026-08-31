<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Events\OrderStatusUpdated;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class OrderStatusService
{
    public function __construct(
        private readonly TelemetryService $telemetry,
        private readonly AnalyticsEventService $analytics,
    ) {}

    public function transition(
        Order $order,
        OrderStatus $to,
        string $actorType = 'system',
        ?int $actorId = null,
        ?string $note = null,
    ): bool {
        /** @var OrderStatus $from */
        $from = $order->status;

        if ($from === $to) {
            return false;
        }

        $startedAt = hrtime(true);

        if ($to === OrderStatus::Paid && $actorType !== 'payment_webhook') {
            throw new ConflictHttpException('Order hanya dapat menjadi paid setelah payment terverifikasi.');
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

        $order->update($updates);

        $this->record($order, $from, $to, $actorType, $actorId, $note);
        OrderStatusUpdated::dispatch(
            (int) $order->getKey(),
            (int) $order->tenant_id,
            (int) $order->outlet_id,
            (string) $order->access_token_hash,
        );

        $this->telemetry->recordDuration('order.status_changed', $startedAt, [
            'from_status' => $from->value,
            'to_status' => $to->value,
            'actor_type' => $actorType,
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
            $this->analytics->record($analyticsEvent, (int) $order->tenant_id, (int) $order->outlet_id, [
                'order_id' => (int) $order->getKey(),
                'properties' => [
                    'from_status' => $from->value,
                    'to_status' => $to->value,
                ],
            ]);
        }

        $createdAt = $order->getAttribute('created_at');

        if ($to === OrderStatus::Paid && $createdAt !== null) {
            $this->telemetry->record('order.payment_latency', [
                'latency_ms' => max(0, (int) CarbonImmutable::parse((string) $createdAt)->diffInMilliseconds(now())),
                'from_status' => $from->value,
                'to_status' => $to->value,
            ]);
        }

        return true;
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
