<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Models\Concerns\BelongsToOutlet;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property PaymentStatus $status
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $paid_at
 * @property CarbonImmutable|null $last_webhook_at
 * @property array<string, mixed>|null $metadata
 * @property int|null $gateway_credential_id
 */
#[Fillable([
    'tenant_id',
    'outlet_id',
    'order_id',
    'method',
    'status',
    'amount',
    'currency',
    'provider',
    'gateway_credential_id',
    'provider_reference',
    'expires_at',
    'paid_at',
    'last_webhook_at',
    'metadata',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
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

    /** @return BelongsTo<PaymentGatewayCredential, $this> */
    public function gatewayCredential(): BelongsTo
    {
        return $this->belongsTo(PaymentGatewayCredential::class, 'gateway_credential_id');
    }

    /** @return HasMany<PaymentRefund, $this> */
    public function refunds(): HasMany
    {
        return $this->hasMany(PaymentRefund::class);
    }

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => 'integer',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'last_webhook_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
