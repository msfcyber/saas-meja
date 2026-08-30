<?php

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('outlet.{outletId}.orders', function (User $user, int|string $outletId): bool {
    $context = app(TenantContext::class);

    return $context->tenantId() !== null
        && $context->outletId() === (int) $outletId
        && $user->can('order.view');
});
