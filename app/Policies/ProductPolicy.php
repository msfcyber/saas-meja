<?php

namespace App\Policies;

class ProductPolicy extends TenantResourcePolicy
{
    protected function permission(): string
    {
        return 'menu.manage';
    }
}
