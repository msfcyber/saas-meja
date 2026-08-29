<?php

namespace App\Actions\Tenancy;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

class ProvisionTenantRoles
{
    public const PERMISSIONS = [
        'outlet.manage',
        'staff.manage',
        'menu.manage',
        'table.manage',
        'order.view',
        'order.update_status',
        'payment.view',
        'payment.refund',
        'report.view',
        'tax.manage',
        'gateway.manage',
        'subscription.manage',
    ];

    private const ROLE_PERMISSIONS = [
        'owner' => self::PERMISSIONS,
        'admin' => ['outlet.manage', 'staff.manage', 'menu.manage', 'table.manage', 'order.view', 'order.update_status', 'payment.view', 'report.view', 'tax.manage'],
        'cashier' => ['order.view', 'order.update_status', 'payment.view'],
        'kitchen' => ['order.view', 'order.update_status'],
    ];

    public function __construct(private readonly PermissionRegistrar $permissions) {}

    public function handle(Tenant $tenant, User $owner): void
    {
        $previousTeamId = $this->permissions->getPermissionsTeamId();

        try {
            foreach (self::PERMISSIONS as $permission) {
                Permission::query()->firstOrCreate([
                    'name' => $permission,
                    'guard_name' => 'web',
                ]);
            }

            $this->permissions->forgetCachedPermissions();
            $this->permissions->setPermissionsTeamId($tenant->getKey());

            foreach (self::ROLE_PERMISSIONS as $name => $permissionNames) {
                $role = Role::query()->firstOrCreate([
                    'tenant_id' => $tenant->getKey(),
                    'name' => $name,
                    'guard_name' => 'web',
                ]);
                $role->syncPermissions(Permission::query()->whereIn('name', $permissionNames)->get());
            }

            $owner->unsetRelation('roles')->unsetRelation('permissions');
            $owner->syncRoles(['owner']);
        } finally {
            $owner->unsetRelation('roles')->unsetRelation('permissions');
            $this->permissions->setPermissionsTeamId($previousTeamId);
        }
    }
}
