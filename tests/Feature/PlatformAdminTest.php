<?php

use App\Enums\SaasInvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
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

test('platform admin can manage plans tenant status subscriptions and pending invoices', function () {
    $admin = User::factory()->platformAdmin()->create();
    $tenant = Tenant::factory()->create();
    $oldPlan = Plan::factory()->create(['code' => 'starter']);
    $newPlan = Plan::factory()->create(['code' => 'growth']);
    $subscription = Subscription::factory()->for($tenant)->for($oldPlan)->create();
    $invoice = SaasInvoice::factory()->for($subscription, 'subscription')->create();

    $this->actingAs($admin)
        ->post(route('platform.plans.store'), [
            'code' => 'pro',
            'name' => 'Pro',
            'description' => 'Untuk bisnis berkembang.',
            'price' => 499000,
            'currency' => 'IDR',
            'billing_interval' => 'monthly',
            'limits' => ['outlets' => 5, 'active_tables' => 200, 'staff' => 20],
            'features' => ['menu', 'reports'],
            'is_active' => true,
            'position' => 2,
        ])
        ->assertRedirect(route('platform.dashboard'));

    $createdPlan = Plan::query()->where('code', 'pro')->firstOrFail();

    $this->patch(route('platform.plans.update', $createdPlan), [
        'code' => 'pro',
        'name' => 'Pro Plus',
        'description' => 'Untuk bisnis berkembang.',
        'price' => 599000,
        'currency' => 'IDR',
        'billing_interval' => 'yearly',
        'limits' => ['outlets' => 8, 'active_tables' => 300, 'staff' => 30],
        'features' => ['menu', 'reports', 'analytics'],
        'is_active' => true,
        'position' => 1,
    ])->assertRedirect(route('platform.dashboard'));

    $this->patch(route('platform.tenants.status.update', $tenant), [
        'status' => TenantStatus::Suspended->value,
    ])->assertRedirect(route('platform.dashboard'));

    $this->patch(route('platform.subscriptions.update', $subscription), [
        'plan_id' => $newPlan->id,
        'status' => SubscriptionStatus::Active->value,
    ])->assertRedirect(route('platform.dashboard'));

    $this->patch(route('platform.invoices.void', $invoice), [])
        ->assertRedirect(route('platform.dashboard'));

    expect($createdPlan->fresh()->name)->toBe('Pro Plus')
        ->and($tenant->fresh()->status)->toBe(TenantStatus::Suspended)
        ->and($subscription->fresh()->plan_id)->toBe($newPlan->id)
        ->and($subscription->fresh()->status)->toBe(SubscriptionStatus::Active)
        ->and($invoice->fresh()->status)->toBe(SaasInvoiceStatus::Void);

    expect(AuditLog::withoutGlobalScopes()
        ->whereIn('event', [
            'platform.plan.created',
            'platform.plan.updated',
            'platform.tenant.status_updated',
            'platform.subscription.updated',
            'platform.invoice.voided',
        ])
        ->count())->toBe(5);
});
