<?php

namespace App\Http\Controllers;

use App\Http\Requests\Staff\StoreStaffRequest;
use App\Http\Requests\Staff\UpdateStaffRequest;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\SubscriptionEntitlementService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StaffController extends Controller
{
    private const DEFAULT_STAFF_PASSWORD = 'password';

    /** @var list<string> */
    private const STAFF_ROLES = ['admin', 'cashier', 'kitchen'];

    /** @var array<string, string> */
    private const ROLE_LABELS = [
        'admin' => 'Admin / manager',
        'cashier' => 'Kasir',
        'kitchen' => 'Kitchen / bar',
    ];

    public function index(
        TenantContext $context,
        SubscriptionEntitlementService $entitlements,
    ): Response {
        $this->authorize('viewAny', User::class);
        $tenant = $context->tenantOrFail();
        $usage = $entitlements->usage($tenant)['staff'];
        $subscription = $entitlements->current($tenant);
        $limit = $subscription?->plan?->limit(SubscriptionEntitlementService::LIMIT_STAFF);
        $canAdd = $entitlements->canAdd($tenant, SubscriptionEntitlementService::LIMIT_STAFF);

        return Inertia::render('staff', [
            'staff' => $tenant->users()
                ->orderBy('name')
                ->get()
                ->map(function (User $staff): array {
                    $role = $this->staffRole($staff);

                    return [
                        'id' => $staff->id,
                        'name' => $staff->name,
                        'email' => $staff->email,
                        'status' => (string) $staff->membership->status,
                        'is_owner' => (bool) $staff->membership->is_owner,
                        'role' => $role,
                        'role_label' => $this->roleLabel($role),
                    ];
                })->values(),
            'roles' => collect(self::STAFF_ROLES)->map(fn (string $role): array => [
                'value' => $role,
                'label' => self::ROLE_LABELS[$role],
            ])->values(),
            'usage' => [
                'current' => $usage,
                'limit' => $limit,
            ],
            'can_add' => $canAdd,
            'limit_message' => $canAdd
                ? null
                : $entitlements->limitMessage($tenant, SubscriptionEntitlementService::LIMIT_STAFF),
        ]);
    }

    public function store(
        StoreStaffRequest $request,
        TenantContext $context,
        SubscriptionEntitlementService $entitlements,
        AuditLogService $audits,
    ): RedirectResponse {
        $this->authorize('create', User::class);
        $tenant = $context->tenantOrFail();

        if (! $entitlements->canAdd($tenant, SubscriptionEntitlementService::LIMIT_STAFF)) {
            throw ValidationException::withMessages([
                'subscription' => $entitlements->limitMessage($tenant, SubscriptionEntitlementService::LIMIT_STAFF),
            ]);
        }

        $attributes = $request->validated();
        $role = $this->tenantRole($tenant, $attributes['role']);

        $staff = DB::transaction(function () use ($tenant, $attributes, $role, $audits): User {
            $staff = User::query()->where('email', $attributes['email'])->first();

            if ($staff === null) {
                if (blank($attributes['name'] ?? null)) {
                    throw ValidationException::withMessages([
                        'name' => 'Nama staf wajib diisi untuk akun baru.',
                    ]);
                }

                $staff = User::query()->create([
                    'name' => $attributes['name'],
                    'email' => $attributes['email'],
                    'password' => self::DEFAULT_STAFF_PASSWORD,
                ]);
            }

            if ($tenant->users()->whereKey($staff->getKey())->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'Akun tersebut sudah menjadi anggota workspace ini.',
                ]);
            }

            $tenant->users()->attach($staff, [
                'status' => 'active',
                'is_owner' => false,
                'joined_at' => now(),
            ]);

            $this->syncRole($staff, $role);

            $audits->record('staff.added', [
                'tenant_id' => (int) $tenant->getKey(),
                'auditable_type' => User::class,
                'auditable_id' => (int) $staff->getKey(),
                'new_values' => [
                    'email' => $staff->email,
                    'role' => $role->name,
                    'status' => 'active',
                ],
            ]);

            return $staff;
        }, attempts: 3);

        return to_route('staff')->with('success', "{$staff->name} berhasil ditambahkan sebagai staf.");
    }

    public function update(
        UpdateStaffRequest $request,
        User $staff,
        TenantContext $context,
        SubscriptionEntitlementService $entitlements,
        AuditLogService $audits,
    ): RedirectResponse {
        $this->authorize('update', $staff);
        $tenant = $context->tenantOrFail();
        $membership = $this->membership($tenant, $staff);

        if ($membership === null) {
            abort(404);
        }

        $attributes = $request->validated();
        $role = $this->tenantRole($tenant, $attributes['role']);
        $oldRole = $this->staffRole($staff);
        $oldStatus = (string) $membership->membership->status;

        if ($oldStatus !== 'active' && $attributes['status'] === 'active'
            && ! $entitlements->canAdd($tenant, SubscriptionEntitlementService::LIMIT_STAFF)) {
            throw ValidationException::withMessages([
                'subscription' => $entitlements->limitMessage($tenant, SubscriptionEntitlementService::LIMIT_STAFF),
            ]);
        }

        DB::transaction(function () use ($tenant, $staff, $role, $attributes, $audits, $oldRole, $oldStatus): void {
            $tenant->users()->updateExistingPivot($staff->getKey(), [
                'status' => $attributes['status'],
            ]);

            $this->syncRole($staff, $role);

            $audits->record('staff.updated', [
                'tenant_id' => (int) $tenant->getKey(),
                'auditable_type' => User::class,
                'auditable_id' => (int) $staff->getKey(),
                'old_values' => [
                    'role' => $oldRole,
                    'status' => $oldStatus,
                ],
                'new_values' => [
                    'role' => $role->name,
                    'status' => $attributes['status'],
                ],
            ]);
        }, attempts: 3);

        return to_route('staff')->with('success', "{$staff->name} berhasil diperbarui.");
    }

    public function destroy(
        User $staff,
        TenantContext $context,
        AuditLogService $audits,
    ): RedirectResponse {
        $this->authorize('delete', $staff);
        $tenant = $context->tenantOrFail();
        $membership = $this->membership($tenant, $staff);

        if ($membership === null) {
            abort(404);
        }

        $role = $this->staffRole($staff);

        DB::transaction(function () use ($tenant, $staff, $audits, $role): void {
            $tenant->users()->detach($staff->getKey());
            $this->clearRoles($staff);

            $audits->record('staff.removed', [
                'tenant_id' => (int) $tenant->getKey(),
                'auditable_type' => User::class,
                'auditable_id' => (int) $staff->getKey(),
                'old_values' => [
                    'email' => $staff->email,
                    'role' => $role,
                ],
            ]);
        }, attempts: 3);

        return to_route('staff')->with('success', "{$staff->name} berhasil dihapus dari workspace.");
    }

    private function tenantRole(Tenant $tenant, string $name): Role
    {
        return Role::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('guard_name', 'web')
            ->whereIn('name', self::STAFF_ROLES)
            ->where('name', $name)
            ->first()
            ?? throw ValidationException::withMessages([
                'role' => 'Role staf belum tersedia pada workspace ini.',
            ]);
    }

    private function syncRole(User $staff, Role $role): void
    {
        $staff->unsetRelation('roles')->unsetRelation('permissions');
        $staff->syncRoles([$role]);
        $staff->unsetRelation('roles')->unsetRelation('permissions');
    }

    private function clearRoles(User $staff): void
    {
        $staff->unsetRelation('roles')->unsetRelation('permissions');
        $staff->syncRoles([]);
        $staff->unsetRelation('roles')->unsetRelation('permissions');
    }

    private function staffRole(User $staff): ?string
    {
        return $staff->getRoleNames()
            ->first(fn (string $role): bool => in_array($role, self::STAFF_ROLES, true));
    }

    private function roleLabel(?string $role): string
    {
        return $role === null ? 'Belum ditentukan' : (self::ROLE_LABELS[$role] ?? $role);
    }

    private function membership(Tenant $tenant, User $staff): ?User
    {
        return $tenant->users()->whereKey($staff->getKey())->first();
    }
}
