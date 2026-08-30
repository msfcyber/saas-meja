<?php

namespace App\Models;

use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property array<string, mixed> $limits
 * @property array<int, string>|null $features
 */
#[Fillable([
    'code',
    'name',
    'description',
    'price',
    'currency',
    'billing_interval',
    'limits',
    'features',
    'is_active',
    'position',
])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    /** @return HasMany<Subscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function limit(string $key): ?int
    {
        if (! array_key_exists($key, $this->limits)) {
            return 0;
        }

        $value = $this->limits[$key] ?? null;

        return $value === null ? null : (is_numeric($value) ? (int) $value : 0);
    }

    public function hasFeature(string $feature): bool
    {
        return is_array($this->features) && in_array($feature, $this->features, true);
    }

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'limits' => 'array',
            'features' => 'array',
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }
}
