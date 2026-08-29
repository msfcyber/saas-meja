<?php

namespace App\Policies;

class TableQrTokenPolicy extends TenantResourcePolicy
{
    protected function permission(): string
    {
        return 'table.manage';
    }
}
