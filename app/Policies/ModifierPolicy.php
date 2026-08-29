<?php

namespace App\Policies;

class ModifierPolicy extends TenantResourcePolicy
{
    protected function permission(): string
    {
        return 'menu.manage';
    }
}
