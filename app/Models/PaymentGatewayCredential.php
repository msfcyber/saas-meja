<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\PaymentGatewayCredentialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $tenant_id
 * @property string $provider
 * @property int $version
 * @property string $secret
 * @property string|null $fingerprint
 * @property array<string, mixed>|null $metadata
 * @property int|null $created_by
 * @property CarbonImmutable|null $retired_at
 */
#[Fillable([
    'tenant_id',
    'provider',
    'version',
    'secret',
    'fingerprint',
    'metadata',
    'created_by',
    'retired_at',
])]
#[Hidden(['secret', 'fingerprint'])]
class PaymentGatewayCredential extends Model
{
    /** @use HasFactory<PaymentGatewayCredentialFactory> */
    use BelongsToTenant, HasFactory;

    protected static function booted(): void
    {
        static::creating(function (PaymentGatewayCredential $credential): void {
            if ($credential->fingerprint === null || $credential->fingerprint === '') {
                $credential->fingerprint = self::fingerprintFor((string) $credential->secret);
            }
        });
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'gateway_credential_id');
    }

    public static function fingerprintFor(string $secret): string
    {
        return hash('sha256', $secret);
    }

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'secret' => 'encrypted',
            'metadata' => 'array',
            'retired_at' => 'datetime',
        ];
    }
}
