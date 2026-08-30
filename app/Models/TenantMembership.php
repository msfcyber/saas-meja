<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * @property string $status
 * @property bool $is_owner
 * @property Carbon|null $joined_at
 */
class TenantMembership extends Pivot
{
    protected function casts(): array
    {
        return [
            'is_owner' => 'boolean',
            'joined_at' => 'datetime',
        ];
    }
}
