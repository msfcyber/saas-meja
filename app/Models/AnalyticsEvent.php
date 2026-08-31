<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $tenant_id
 * @property int $outlet_id
 * @property int|null $order_id
 * @property int|null $product_id
 * @property string $event
 * @property string|null $session_hash
 * @property string|null $qr_token_hash
 * @property array<string, mixed>|null $properties
 * @property CarbonImmutable $occurred_at
 */
#[Fillable([
    'tenant_id',
    'outlet_id',
    'order_id',
    'product_id',
    'event',
    'session_hash',
    'qr_token_hash',
    'properties',
    'occurred_at',
])]
class AnalyticsEvent extends Model
{
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

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
