<?php

namespace App\Policies;

class ProductVariantPolicy extends TenantResourcePolicy
{
    protected function permission(): string
    {
        return 'menu.manage';
    }
}
