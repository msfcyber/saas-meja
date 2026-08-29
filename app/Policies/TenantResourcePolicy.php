<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;

abstract class TenantResourcePolicy
{
    public function __construct(protected readonly TenantContext $context) {}

    abstract protected function permission(): string;

    public function viewAny(User $user): bool
    {
        return $this->hasPermissionInContext($user);
    }

    public function view(User $user, Model $model): bool
    {
        return $this->belongsToContext($model) && $this->hasPermissionInContext($user);
    }

    public function create(User $user): bool
    {
        return $this->hasPermissionInContext($user);
    }

    public function update(User $user, Model $model): bool
    {
        return $this->view($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->view($user, $model);
    }

    private function hasPermissionInContext(User $user): bool
    {
        return $this->context->tenant() !== null && $user->can($this->permission());
    }

    private function belongsToContext(Model $model): bool
    {
        return $this->context->tenantId() !== null
            && $model->getAttribute('tenant_id') === $this->context->tenantId()
            && $model->getAttribute('outlet_id') === $this->context->outletId();
    }
}
