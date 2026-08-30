<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class TenantOutletUser extends Pivot
{
    protected $table = 'tenant_outlet_user';
}
