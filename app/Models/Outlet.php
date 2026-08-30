<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\OutletFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['tenant_id', 'name', 'slug', 'code', 'address', 'phone', 'timezone', 'currency', 'is_active', 'accepts_orders'])]
class Outlet extends Model
{
    /** @use HasFactory<OutletFactory> */
    use BelongsToTenant, HasFactory;

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsToMany<User, $this, TenantOutletUser, 'assignment'> */
    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_outlet_user')
            ->using(TenantOutletUser::class)
            ->as('assignment')
            ->withPivot('tenant_id')
            ->withTimestamps();
    }

    /** @return HasMany<Category, $this> */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** @return HasMany<Modifier, $this> */
    public function modifiers(): HasMany
    {
        return $this->hasMany(Modifier::class);
    }

    /** @return HasMany<DiningTable, $this> */
    public function tables(): HasMany
    {
        return $this->hasMany(DiningTable::class);
    }

    /** @return HasOne<TaxSetting, $this> */
    public function taxSetting(): HasOne
    {
        return $this->hasOne(TaxSetting::class);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'accepts_orders' => 'boolean',
        ];
    }
}
