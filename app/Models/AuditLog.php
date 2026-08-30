<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int|null $tenant_id
 * @property int|null $outlet_id
 * @property string $actor_type
 * @property int|null $actor_id
 * @property string $event
 * @property string|null $auditable_type
 * @property int|null $auditable_id
 * @property string|null $request_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'tenant_id',
    'outlet_id',
    'actor_type',
    'actor_id',
    'event',
    'auditable_type',
    'auditable_id',
    'request_id',
    'ip_address',
    'user_agent',
    'old_values',
    'new_values',
])]
class AuditLog extends Model
{
    use BelongsToTenant;

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

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }
}
