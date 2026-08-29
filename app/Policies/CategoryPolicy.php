<?php

namespace App\Policies;

class CategoryPolicy extends TenantResourcePolicy
{
    protected function permission(): string
    {
        return 'menu.manage';
    }
}
