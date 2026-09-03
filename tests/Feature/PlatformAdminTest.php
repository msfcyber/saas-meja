<?php

use App\Enums\SaasInvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Jobs\ReconcilePaymentJob;
use App\Models\AuditLog;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\Plan;
use App\Models\SaasInvoice;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
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

test('platform dashboard filters tenants and exposes workspace detail data', function () {
    $admin = User::factory()->platformAdmin()->create();
    $owner = User::factory()->create([
        'name' => 'Owner Filtered',
        'email' => 'owner-filtered@example.com',
    ]);
    $matchingTenant = Tenant::factory()->create([
        'name' => 'Filtered Workspace',
        'slug' => 'filtered-workspace',
    ]);
    $otherTenant = Tenant::factory()->create(['name' => 'Other Workspace']);
    $matchingTenant->users()->attach($owner, [
        'status' => 'active',
        'is_owner' => true,
        'joined_at' => now(),
    ]);
    Outlet::factory()->for($matchingTenant)->create();
    Outlet::factory()->for($matchingTenant)->create(['is_active' => false]);

    $this->actingAs($admin)
        ->get(route('platform.dashboard', [
            'tenant_search' => 'owner-filtered@example.com',
            'tenant_status' => TenantStatus::Active->value,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('platform/dashboard')
            ->where('filters.tenant_search', 'owner-filtered@example.com')
            ->where('filters.tenant_status', TenantStatus::Active->value)
            ->where('tenant_pagination.total', 1)
            ->where('tenants.0.id', $matchingTenant->id)
            ->where('tenants.0.owner.email', $owner->email)
            ->where('tenants.0.outlets', 2)
            ->where('tenants.0.active_outlets', 1)
            ->where('tenants.0.active_members', 1)
            ->where('tenants.0.subscription', null),
        );

});

test('platform admin can inspect payment events and request pending reconciliation', function () {
    Queue::fake();
    $admin = User::factory()->platformAdmin()->create();
    $tenant = Tenant::factory()->create(['name' => 'Payment Workspace']);
    $outlet = Outlet::factory()->for($tenant)->create(['name' => 'Payment Outlet']);
    $table = DiningTable::factory()->for($outlet)->create();
    $order = Order::factory()->for($table, 'table')->create([
        'tenant_id' => $tenant->id,
        'outlet_id' => $outlet->id,
        'order_number' => 'PAY-1001',
    ]);
    $payment = Payment::factory()->for($order)->create([
        'tenant_id' => $tenant->id,
        'outlet_id' => $outlet->id,
        'provider' => 'midtrans',
        'provider_reference' => 'payment-reference-1001',
    ]);
    $event = PaymentEvent::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'outlet_id' => $outlet->id,
        'payment_id' => $payment->id,
        'provider' => 'midtrans',
        'event_id' => 'webhook-event-1001',
        'event_type' => 'payment.paid',
        'amount' => $payment->amount,
        'currency' => 'IDR',
        'occurred_at' => now(),
        'payload_hash' => hash('sha256', 'platform-event'),
        'payload' => ['event_type' => 'payment.paid'],
    ]);

    $this->actingAs($admin)
        ->get(route('platform.dashboard', [
            'payment_event_search' => 'webhook-event-1001',
            'payment_event_status' => 'pending',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.payment_event_search', 'webhook-event-1001')
            ->where('filters.payment_event_status', 'pending')
            ->where('payment_event_pagination.total', 1)
            ->where('payment_events.0.event_id', $event->event_id)
            ->where('payment_events.0.order_number', $order->order_number)
            ->where('payment_events.0.payment_status', 'pending')
            ->where('pending_payments.0.id', $payment->id),
        );

    $this->actingAs($admin)
        ->post(route('platform.payments.reconcile', $payment))
        ->assertRedirect(route('platform.dashboard'));

    Queue::assertPushed(ReconcilePaymentJob::class, fn (ReconcilePaymentJob $job): bool => $job->paymentId === $payment->id);
    expect(AuditLog::withoutGlobalScopes()
        ->where('event', 'platform.payment.reconciliation_requested')
        ->where('auditable_id', $payment->id)
        ->exists())->toBeTrue();
});
