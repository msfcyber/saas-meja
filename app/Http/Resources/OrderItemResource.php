<?php

namespace App\Http\Resources;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrderItem */
class OrderItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var OrderItem $item */
        $item = $this->resource;

        return [
            'id' => $item->getKey(),
            'product_name' => $item->product_name_snapshot,
            'variant_name' => $item->variant_name_snapshot,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'line_total' => $item->line_total,
            'note' => $item->note,
            'modifiers' => $item->relationLoaded('modifiers')
                ? $item->modifiers->map(fn ($modifier) => [
                    'modifier_name' => $modifier->modifier_name_snapshot,
                    'option_name' => $modifier->option_name_snapshot,
                    'price_delta' => $modifier->price_delta_snapshot,
                ])->values()
                : [],
        ];
    }
}
