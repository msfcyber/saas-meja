<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy extends TenantResourcePolicy
{
    protected function permission(): string
    {
        return 'order.view';
    }

    public function updateStatus(User $user, Order $order): bool
    {
        return $this->belongsToContext($order)
            && $this->hasPermissionInContext($user, 'order.update_status');
    }

    public function refund(User $user, Order $order): bool
    {
        return $this->belongsToContext($order)
            && $this->hasPermissionInContext($user, 'payment.refund');
    }
}
