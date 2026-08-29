<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOutlet;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'outlet_id',
    'order_item_id',
    'modifier_id',
    'modifier_option_id',
    'modifier_name_snapshot',
    'option_name_snapshot',
    'price_delta_snapshot',
])]
class OrderItemModifier extends Model
{
    use BelongsToOutlet, BelongsToTenant;

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<Outlet, $this> */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /** @return BelongsTo<Modifier, $this> */
    public function modifier(): BelongsTo
    {
        return $this->belongsTo(Modifier::class);
    }

    /** @return BelongsTo<ModifierOption, $this> */
    public function option(): BelongsTo
    {
        return $this->belongsTo(ModifierOption::class, 'modifier_option_id');
    }

    protected function casts(): array
    {
        return [
            'price_delta_snapshot' => 'integer',
        ];
    }
}
