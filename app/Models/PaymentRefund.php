<?php

namespace App\Models;

use App\Enums\PaymentRefundStatus;
use App\Models\Concerns\BelongsToOutlet;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property PaymentRefundStatus $status
 * @property int $amount
 * @property CarbonImmutable|null $requested_at
 * @property CarbonImmutable|null $completed_at
 * @property array<string, mixed>|null $provider_response
 */
#[Fillable([
    'tenant_id',
    'outlet_id',
    'payment_id',
    'idempotency_key',
    'provider',
    'provider_refund_key',
    'provider_reference',
    'status',
    'amount',
    'currency',
    'reason',
    'requested_by',
    'provider_response',
    'failure_reason',
    'requested_at',
    'completed_at',
])]
class PaymentRefund extends Model
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

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    protected function casts(): array
    {
        return [
            'status' => PaymentRefundStatus::class,
            'amount' => 'integer',
            'provider_response' => 'array',
            'requested_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
