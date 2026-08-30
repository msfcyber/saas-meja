<?php

namespace App\Events;

use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;

class OrderStatusUpdated implements ShouldBroadcastNow, ShouldDispatchAfterCommit, ShouldRescue
{
    use Dispatchable;

    public function __construct(
        public readonly int $orderId,
        public readonly int $tenantId,
        public readonly int $outletId,
        public readonly string $accessTokenHash,
    ) {}

    /**
     * @return array<int, Channel|PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(self::outletChannel($this->outletId)),
            new Channel(self::customerChannel($this->accessTokenHash)),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.status.updated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        $order = Order::withoutGlobalScopes()
            ->whereKey($this->orderId)
            ->where('tenant_id', $this->tenantId)
            ->where('outlet_id', $this->outletId)
            ->with([
                'items' => fn ($query) => $query->withoutGlobalScopes()->orderBy('id'),
                'items.modifiers' => fn ($query) => $query->withoutGlobalScopes()->orderBy('id'),
                'payments' => fn ($query) => $query->withoutGlobalScopes()->orderByDesc('id'),
                'statusHistories' => fn ($query) => $query->withoutGlobalScopes()
                    ->orderBy('created_at')
                    ->orderBy('id'),
                'table' => fn ($query) => $query->withoutGlobalScopes(),
                'outlet' => fn ($query) => $query->withoutGlobalScopes(),
            ])
            ->first();

        if ($order === null) {
            return [];
        }

        return [
            'order' => (new OrderResource($order))->resolve(Request::create('/')),
        ];
    }

    public static function outletChannel(int $outletId): string
    {
        return "outlet.{$outletId}.orders";
    }

    public static function customerChannel(string $accessTokenHash): string
    {
        return "order.{$accessTokenHash}";
    }
}
