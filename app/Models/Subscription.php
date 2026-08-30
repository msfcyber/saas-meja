<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property SubscriptionStatus $status
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $trial_starts_at
 * @property CarbonImmutable|null $trial_ends_at
 * @property CarbonImmutable|null $current_period_starts_at
 * @property CarbonImmutable|null $current_period_ends_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $suspended_at
 * @property array<string, mixed>|null $metadata
 */
#[Fillable([
    'tenant_id',
    'plan_id',
    'status',
    'provider',
    'provider_reference',
    'started_at',
    'trial_starts_at',
    'trial_ends_at',
    'current_period_starts_at',
    'current_period_ends_at',
    'cancelled_at',
    'suspended_at',
    'metadata',
])]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use BelongsToTenant, HasFactory;

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** @return HasMany<SaasInvoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(SaasInvoice::class);
    }

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'started_at' => 'datetime',
            'trial_starts_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'current_period_starts_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'suspended_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
