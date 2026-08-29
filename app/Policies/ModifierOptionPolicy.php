<?php

namespace App\Policies;

class ModifierOptionPolicy extends TenantResourcePolicy
{
    protected function permission(): string
    {
        return 'menu.manage';
    }
}
