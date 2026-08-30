<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOutlet;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $outlet_id
 * @property int $payment_id
 * @property string $provider
 * @property string $event_id
 * @property string $event_type
 * @property int $amount
 * @property string $currency
 * @property CarbonImmutable $occurred_at
 * @property CarbonImmutable|null $processed_at
 */
#[Fillable([
    'tenant_id',
    'outlet_id',
    'payment_id',
    'provider',
    'event_id',
    'event_type',
    'amount',
    'currency',
    'occurred_at',
    'payload_hash',
    'payload',
    'processed_at',
])]
class PaymentEvent extends Model
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

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'occurred_at' => 'datetime',
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
