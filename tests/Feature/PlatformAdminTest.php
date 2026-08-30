<?php

use App\Enums\SubscriptionStatus;
use App\Models\AuditLog;
use App\Models\Outlet;
use App\Models\Plan;
use App\Models\SaasInvoice;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('platform dashboard rejects users without platform access', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('platform.dashboard'))
        ->assertForbidden();
});

test('platform admin can inspect tenant data outside the active tenant scope', function () {
    $admin = User::factory()->platformAdmin()->create();
    $plan = Plan::factory()->create([
        'name' => 'Growth',
        'code' => 'growth',
    ]);
    $firstTenant = Tenant::factory()->create(['name' => 'Kedai Selatan']);
    $secondTenant = Tenant::factory()->create(['name' => 'Kedai Utara']);
    $firstOutlet = Outlet::factory()->for($firstTenant)->create();
    $firstSubscription = Subscription::factory()->for($firstTenant)->for($plan)->active()->create();
    Subscription::factory()->for($secondTenant)->for($plan)->create();
    SaasInvoice::factory()->for($firstSubscription, 'subscription')->create([
        'status' => 'pending',
    ]);
    AuditLog::withoutGlobalScopes()->create([
        'tenant_id' => $firstTenant->id,
        'actor_type' => 'user',
        'actor_id' => $admin->id,
        'event' => 'subscription.created',
        'new_values' => ['plan' => 'growth'],
    ]);
    $admin->tenants()->attach($firstTenant, [
        'status' => 'active',
        'is_owner' => false,
        'joined_at' => now(),
    ]);

    $this->actingAs($admin)
        ->withSession(['active_tenant_id' => $firstTenant->id])
        ->get(route('platform.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('platform/dashboard')
            ->where('tenancy.platform_admin', true)
            ->where('overview.tenants', 2)
            ->where('overview.active_subscriptions', 1)
            ->where('overview.trialing_subscriptions', 1)
            ->where('overview.pending_invoices', 1)
            ->where('plans.0.name', 'Growth')
            ->where('plans.0.subscribers', 2)
            ->has('tenants', 2)
            ->where('tenants.0.name', $secondTenant->name)
            ->where('tenants.1.name', $firstTenant->name)
            ->where('tenants.1.outlets', 1)
            ->where('tenants.1.subscription.status', SubscriptionStatus::Active->value)
            ->where('audit_logs.0.event', 'subscription.created')
            ->where('audit_logs.0.resource', null)
            ->has('payment_events', 0),
        );

    expect($firstOutlet->tenant_id)->toBe($firstTenant->id);
});

test('platform access can be granted and revoked from the console', function () {
    $user = User::factory()->create();

    $this->artisan('platform:grant', ['email' => $user->email])
        ->assertExitCode(0);
    expect($user->fresh()->is_platform_admin)->toBeTrue();

    $this->artisan('platform:revoke', ['email' => $user->email])
        ->assertExitCode(0);
    expect($user->fresh()->is_platform_admin)->toBeFalse();
});
