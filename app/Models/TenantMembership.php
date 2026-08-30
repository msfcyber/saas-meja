<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property string $status
 * @property bool $is_owner
 * @property Carbon|null $joined_at
 */
class TenantMembership extends Pivot
{
    protected static function booted(): void
    {
        static::created(function (self $membership): void {
            $now = now();

            // Direct memberships retain the historic all-current-outlets access default.
            $assignments = Outlet::withoutGlobalScopes()
                ->where('tenant_id', $membership->tenant_id)
                ->pluck('id')
                ->map(fn (int $outletId): array => [
                    'tenant_id' => $membership->tenant_id,
                    'outlet_id' => $outletId,
                    'user_id' => $membership->user_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all();

            if ($assignments !== []) {
                DB::table('tenant_outlet_user')->insertOrIgnore($assignments);
            }
        });

        static::deleted(function (self $membership): void {
            DB::table('tenant_outlet_user')
                ->where('tenant_id', $membership->tenant_id)
                ->where('user_id', $membership->user_id)
                ->delete();
        });
    }

    protected function casts(): array
    {
        return [
            'is_owner' => 'boolean',
            'joined_at' => 'datetime',
        ];
    }
}
