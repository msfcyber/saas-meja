<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOutlet;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\DiningTableFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['tenant_id', 'outlet_id', 'name', 'code', 'zone', 'capacity', 'is_active'])]
class DiningTable extends Model
{
    /** @use HasFactory<DiningTableFactory> */
    use BelongsToOutlet, BelongsToTenant, HasFactory;

    protected $table = 'tables';

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

    /** @return HasMany<TableQrToken, $this> */
    public function qrTokens(): HasMany
    {
        return $this->hasMany(TableQrToken::class, 'table_id');
    }

    /** @return HasOne<TableQrToken, $this> */
    public function activeQrToken(): HasOne
    {
        return $this->hasOne(TableQrToken::class, 'table_id')
            ->ofMany(['id' => 'max'], fn ($query) => $query->whereNull('revoked_at'));
    }

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
