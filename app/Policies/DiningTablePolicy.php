<?php

namespace App\Policies;

class DiningTablePolicy extends TenantResourcePolicy
{
    protected function permission(): string
    {
        return 'table.manage';
    }
}
