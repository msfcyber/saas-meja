<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Order $order */
        $order = $this->resource;
        $payment = $order->relationLoaded('payments') ? $order->payments->first() : null;

        return [
            'id' => $order->getKey(),
            'number' => $order->order_number,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'payment_status' => $payment?->status?->value,
            'payment_method' => $payment?->method,
            'customer_name' => $order->customer_name,
            'outlet' => $order->relationLoaded('outlet') && $order->outlet !== null
                ? [
                    'name' => $order->outlet->name,
                    'currency' => $order->currency,
                ]
                : null,
            'table' => $order->relationLoaded('table') && $order->table !== null
                ? [
                    'name' => $order->table->name,
                    'code' => $order->table->code,
                ]
                : null,
            'subtotal' => $order->subtotal,
            'discount_amount' => $order->discount_amount,
            'tax_name' => $order->tax_name_snapshot,
            'tax_rate_basis_points' => $order->tax_rate_snapshot,
            'tax_inclusive' => $order->tax_inclusive_snapshot,
            'tax_amount' => $order->tax_amount,
            'fee_amount' => $order->fee_amount,
            'grand_total' => $order->grand_total,
            'currency' => $order->currency,
            'paid_at' => $order->paid_at,
            'completed_at' => $order->completed_at,
            'created_at' => $order->created_at,
            'items' => $order->relationLoaded('items')
                ? $order->items
                    ->map(fn ($item) => (new OrderItemResource($item))->resolve($request))
                    ->values()
                : [],
            'status_history' => $order->relationLoaded('statusHistories')
                ? $order->statusHistories->map(fn ($history) => [
                    'from_status' => $history->from_status?->value,
                    'to_status' => $history->to_status->value,
                    'to_status_label' => $history->to_status->label(),
                    'actor_type' => $history->actor_type,
                    'created_at' => $history->created_at,
                ])->values()
                : [],
        ];
    }
}
