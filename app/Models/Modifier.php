<?php

namespace App\Models;

use App\Enums\ModifierSelectionType;
use App\Models\Concerns\BelongsToOutlet;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ModifierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property ModifierSelectionType $selection_type */
#[Fillable(['tenant_id', 'outlet_id', 'name', 'selection_type', 'minimum_selections', 'maximum_selections', 'is_required', 'is_active'])]
class Modifier extends Model
{
    /** @use HasFactory<ModifierFactory> */
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

    /** @return HasMany<ModifierOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(ModifierOption::class)->orderBy('position');
    }

    /** @return BelongsToMany<Product, $this> */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_modifier')
            ->withPivot(['tenant_id', 'outlet_id', 'position'])
            ->withTimestamps();
    }

    protected function casts(): array
    {
        return [
            'selection_type' => ModifierSelectionType::class,
            'minimum_selections' => 'integer',
            'maximum_selections' => 'integer',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
