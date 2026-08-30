<?php

namespace App\Models;

use App\Enums\SaasInvoiceStatus;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\SaasInvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property SaasInvoiceStatus $status
 * @property CarbonImmutable|null $period_starts_at
 * @property CarbonImmutable|null $period_ends_at
 * @property CarbonImmutable|null $due_at
 * @property CarbonImmutable|null $paid_at
 * @property array<string, mixed>|null $metadata
 */
#[Fillable([
    'tenant_id',
    'subscription_id',
    'invoice_number',
    'status',
    'amount',
    'currency',
    'provider',
    'provider_reference',
    'period_starts_at',
    'period_ends_at',
    'due_at',
    'paid_at',
    'metadata',
])]
class SaasInvoice extends Model
{
    protected $table = 'saas_invoices';

    /** @use HasFactory<SaasInvoiceFactory> */
    use BelongsToTenant, HasFactory;

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    protected function casts(): array
    {
        return [
            'status' => SaasInvoiceStatus::class,
            'amount' => 'integer',
            'period_starts_at' => 'datetime',
            'period_ends_at' => 'datetime',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
