<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class OrderStatusService
{
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
