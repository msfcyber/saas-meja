<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Models\Concerns\BelongsToOutlet;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property OrderStatus $status
 * @property int $order_sequence
 * @property string $order_number
 * @property int $subtotal
 * @property int $discount_amount
 * @property int $tax_rate_snapshot
 * @property bool $tax_inclusive_snapshot
 * @property int $tax_amount
 * @property int $fee_amount
 * @property int $grand_total
 * @property string $currency
 * @property string|null $outlet_name_snapshot
 * @property string|null $table_name_snapshot
 * @property string $access_token_encrypted
 * @property CarbonImmutable|null $paid_at
 * @property CarbonImmutable|null $completed_at
 */
#[Fillable([
    'tenant_id',
    'outlet_id',
    'table_id',
    'outlet_name_snapshot',
    'outlet_address_snapshot',
    'outlet_phone_snapshot',
    'table_name_snapshot',
    'table_code_snapshot',
    'order_sequence',
    'order_number',
    'customer_name',
    'status',
    'subtotal',
    'discount_amount',
    'tax_name_snapshot',
    'tax_rate_snapshot',
    'tax_inclusive_snapshot',
    'tax_amount',
    'fee_amount',
    'grand_total',
    'currency',
    'idempotency_key',
    'idempotency_fingerprint',
    'access_token_hash',
    'access_token_encrypted',
    'paid_at',
    'completed_at',
])]
#[Hidden(['idempotency_key', 'idempotency_fingerprint', 'access_token_hash', 'access_token_encrypted'])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
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

    /** @return BelongsTo<DiningTable, $this> */
    public function table(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'table_id');
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasMany<OrderStatusHistory, $this> */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'subtotal' => 'integer',
            'discount_amount' => 'integer',
            'tax_rate_snapshot' => 'integer',
            'tax_inclusive_snapshot' => 'boolean',
            'tax_amount' => 'integer',
            'fee_amount' => 'integer',
            'grand_total' => 'integer',
            'paid_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
