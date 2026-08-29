<?php

namespace App\Models\Concerns;

use App\Models\Scopes\OutletScope;

trait BelongsToOutlet
{
    public static function bootBelongsToOutlet(): void
    {
        static::addGlobalScope(new OutletScope);
    }
}
