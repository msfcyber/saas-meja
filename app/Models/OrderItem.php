<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOutlet;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'outlet_id',
    'order_id',
    'product_id',
    'variant_id',
    'product_name_snapshot',
    'product_description_snapshot',
    'variant_name_snapshot',
    'base_price_snapshot',
    'variant_price_delta_snapshot',
    'modifier_amount_snapshot',
    'unit_price',
    'quantity',
    'line_total',
    'note',
])]
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use BelongsToOutlet, BelongsToTenant, HasFactory;

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

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /** @return HasMany<OrderItemModifier, $this> */
    public function modifiers(): HasMany
    {
        return $this->hasMany(OrderItemModifier::class);
    }

    protected function casts(): array
    {
        return [
            'base_price_snapshot' => 'integer',
            'variant_price_delta_snapshot' => 'integer',
            'modifier_amount_snapshot' => 'integer',
            'unit_price' => 'integer',
            'quantity' => 'integer',
            'line_total' => 'integer',
        ];
    }
}
