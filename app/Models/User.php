<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $google_id
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property bool $is_platform_admin
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TenantMembership $membership
 */
#[Fillable(['name', 'email', 'google_id', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /** @return BelongsToMany<Tenant, $this, TenantMembership, 'membership'> */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class)
            ->using(TenantMembership::class)
            ->as('membership')
            ->withPivot(['status', 'is_owner', 'joined_at'])
            ->withTimestamps();
    }

    /** @return BelongsToMany<Outlet, $this, TenantOutletUser, 'assignment'> */
    public function assignedOutlets(): BelongsToMany
    {
        return $this->belongsToMany(Outlet::class, 'tenant_outlet_user')
            ->using(TenantOutletUser::class)
            ->as('assignment')
            ->withPivot('tenant_id')
            ->withTimestamps()
            ->withoutGlobalScopes();
    }

    /** @return BelongsToMany<Outlet, $this, TenantOutletUser, 'assignment'> */
    public function assignedOutletsFor(Tenant $tenant): BelongsToMany
    {
        return $this->assignedOutlets()
            ->wherePivot('tenant_id', $tenant->getKey())
            ->withPivotValue('tenant_id', $tenant->getKey());
    }

    /** @return HasMany<PaymentGatewayCredential, $this> */
    public function createdGatewayCredentials(): HasMany
    {
        return $this->hasMany(PaymentGatewayCredential::class, 'created_by');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_platform_admin' => 'boolean',
        ];
    }
}
