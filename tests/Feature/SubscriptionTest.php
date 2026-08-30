<?php

use App\Actions\Tenancy\CreateOwnerWorkspace;
use App\Enums\SaasInvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\DiningTable;
use App\Models\Outlet;
use App\Models\Plan;
use App\Models\SaasInvoice;
use App\Models\Subscription;
use App\Models\TableQrToken;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SubscriptionEntitlementService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{user: User, tenant: Tenant, outlet: Outlet, subscription: Subscription}
 */
function createBillingWorkspace(): array
{
    $user = User::factory()->create();
    $workspace = app(CreateOwnerWorkspace::class)->handle($user, [
        'business_name' => 'Billing Sore',
        'outlet_name' => 'Billing Sore Pusat',
        'address' => null,
        'phone' => null,
        'timezone' => 'Asia/Jakarta',
        'tax_enabled' => false,
        'tax_name' => null,
        'tax_rate' => null,
        'tax_inclusive' => false,
    ]);
    $subscription = Subscription::withoutGlobalScopes()
        ->where('tenant_id', $workspace['tenant']->id)
        ->firstOrFail();

    return [
        'user' => $user,
        'tenant' => $workspace['tenant'],
        'outlet' => $workspace['outlet'],
        'subscription' => $subscription,
    ];
}

test('onboarding provisions a configurable trial on the default plan', function () {
    config(['subscriptions.trial_days' => 21]);
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('onboarding.store'), [
        'business_name' => 'Kopi Senja',
        'outlet_name' => 'Kopi Senja Pusat',
        'timezone' => 'Asia/Jakarta',
        'tax_enabled' => false,
        'tax_inclusive' => false,
    ])->assertRedirect(route('dashboard'));

    $tenant = Tenant::query()->where('slug', 'kopi-senja')->firstOrFail();
    $subscription = Subscription::withoutGlobalScopes()
        ->with('plan')
        ->where('tenant_id', $tenant->id)
        ->firstOrFail();

    expect($subscription->status)->toBe(SubscriptionStatus::Trialing)
        ->and($subscription->plan->code)->toBe('tumbuh')
        ->and($subscription->trial_starts_at)->not->toBeNull()
        ->and((int) $subscription->trial_starts_at?->diffInDays($subscription->trial_ends_at))->toBe(21);
});

test('entitlements count active tenant resources against the subscribed plan', function () {
    $tenant = Tenant::factory()->create();
    $plan = Plan::factory()->create([
        'limits' => [
            'outlets' => 1,
            'active_tables' => 2,
            'staff' => 2,
        ],
    ]);
    Subscription::factory()->for($tenant)->for($plan)->create([
        'status' => SubscriptionStatus::Trialing,
        'trial_ends_at' => now()->addDay(),
    ]);
    $outlet = Outlet::factory()->for($tenant)->create();
    Outlet::factory()->for($tenant)->create(['is_active' => false]);
    DiningTable::factory()->for($outlet)->create(['is_active' => true]);
    DiningTable::factory()->for($outlet)->create(['is_active' => false]);
    $owner = User::factory()->create();
    $tenant->users()->attach($owner, [
        'status' => 'active',
        'is_owner' => true,
        'joined_at' => now(),
    ]);
    $inactiveStaff = User::factory()->create();
    $tenant->users()->attach($inactiveStaff, [
        'status' => 'inactive',
        'is_owner' => false,
        'joined_at' => now(),
    ]);

    $entitlements = app(SubscriptionEntitlementService::class);
    $summary = $entitlements->summary($tenant);

    expect($summary['can_accept_orders'])->toBeTrue()
        ->and($summary['usage'])->toBe([
            'outlets' => 1,
            'active_tables' => 1,
            'staff' => 1,
        ])
        ->and($entitlements->canAdd($tenant, SubscriptionEntitlementService::LIMIT_ACTIVE_TABLES))->toBeTrue()
        ->and($entitlements->canAdd($tenant, SubscriptionEntitlementService::LIMIT_OUTLETS))->toBeFalse()
        ->and($entitlements->canAdd($tenant, SubscriptionEntitlementService::LIMIT_STAFF))->toBeTrue();
});

test('expired subscription periods cannot accept new orders', function () {
    $tenant = Tenant::factory()->create();
    $subscription = Subscription::factory()->for($tenant)->create([
        'status' => SubscriptionStatus::Trialing,
        'trial_ends_at' => now()->subSecond(),
    ]);
    $entitlements = app(SubscriptionEntitlementService::class);

    expect($entitlements->canAcceptOrders($tenant))->toBeFalse();

    $subscription->update([
        'status' => SubscriptionStatus::Active,
        'trial_ends_at' => null,
        'current_period_ends_at' => now()->subSecond(),
    ]);

    expect($entitlements->canAcceptOrders($tenant))->toBeFalse();

    $subscription->update([
        'status' => SubscriptionStatus::Suspended,
        'current_period_ends_at' => now()->addDay(),
    ]);

    expect($entitlements->canAcceptOrders($tenant))->toBeFalse();
});

test('subscription lifecycle command marks finished trials as expired', function () {
    $tenant = Tenant::factory()->create();
    $subscription = Subscription::factory()->for($tenant)->create([
        'status' => SubscriptionStatus::Trialing,
        'trial_ends_at' => now()->subSecond(),
    ]);

    $this->artisan('subscriptions:expire')->assertExitCode(0);

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Expired);
});

test('public QR access rejects a tenant without an active subscription', function () {
    $tenant = Tenant::factory()->create();
    $outlet = Outlet::factory()->for($tenant)->create();
    $table = DiningTable::factory()->for($outlet)->create();
    $plainToken = str_repeat('f', 64);
    TableQrToken::factory()->for($table, 'table')->create([
        'token_hash' => hash('sha256', $plainToken),
    ]);

    $this->get(route('public.qr', ['qrToken' => $plainToken]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('customer/menu')
            ->where('access.valid', false),
        );
});

test('saas invoices cannot reference a subscription from another tenant', function () {
    $firstTenant = Tenant::factory()->create();
    $secondTenant = Tenant::factory()->create();
    $subscription = Subscription::factory()->for($secondTenant)->create();

    expect(fn () => SaasInvoice::withoutGlobalScopes()->create([
        'tenant_id' => $firstTenant->id,
        'subscription_id' => $subscription->id,
        'invoice_number' => 'INV-CROSS-TENANT',
        'status' => SaasInvoiceStatus::Pending,
        'amount' => 299000,
        'currency' => 'IDR',
    ]))->toThrow(QueryException::class);
});

test('owner can view billing and receives an idempotent Midtrans subscription checkout', function () {
    $workspace = createBillingWorkspace();
    $session = [
        'active_tenant_id' => $workspace['tenant']->id,
        'active_outlet_id' => $workspace['outlet']->id,
    ];

    $this->actingAs($workspace['user'])->withSession($session)
        ->get(route('subscription'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('subscription')
            ->where('subscription.status', 'trialing')
            ->where('subscription.plan.name', 'Tumbuh')
            ->has('invoices', 0),
        );

    config(['payments.midtrans.server_key' => 'server-key']);
    Http::fake([
        config('payments.midtrans.snap_url') => Http::response([
            'token' => 'snap-token',
            'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token',
        ]),
    ]);

    $first = $this->actingAs($workspace['user'])->withSession($session)
        ->postJson(route('subscription.checkout'))
        ->assertOk()
        ->assertJsonPath('provider', 'midtrans');
    $invoice = SaasInvoice::withoutGlobalScopes()->firstOrFail();

    $second = $this->actingAs($workspace['user'])->withSession($session)
        ->postJson(route('subscription.checkout'))
        ->assertOk()
        ->assertJsonPath('invoice_id', $invoice->id);

    expect($first->json('invoice_id'))->toBe($invoice->id)
        ->and($second->json('redirect_url'))->toBe('https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token')
        ->and($invoice->provider)->toBe('midtrans')
        ->and($invoice->provider_reference)->toBe('meja-subscription-'.$invoice->id)
        ->and($workspace['subscription']->fresh()->status)->toBe(SubscriptionStatus::Trialing);
    Http::assertSentCount(1);
});

test('verified Midtrans subscription webhook activates the subscription and is idempotent', function () {
    $workspace = createBillingWorkspace();
    $session = [
        'active_tenant_id' => $workspace['tenant']->id,
        'active_outlet_id' => $workspace['outlet']->id,
    ];
    config(['payments.midtrans.server_key' => 'server-key']);
    Http::fake([
        config('payments.midtrans.snap_url') => Http::response([
            'token' => 'snap-token',
            'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token',
        ]),
    ]);

    $this->actingAs($workspace['user'])->withSession($session)
        ->postJson(route('subscription.checkout'))
        ->assertOk();
    $invoice = SaasInvoice::withoutGlobalScopes()->firstOrFail();
    $grossAmount = number_format($invoice->amount, 2, '.', '');
    $payload = [
        'transaction_id' => 'subscription-transaction-1',
        'transaction_status' => 'settlement',
        'order_id' => $invoice->provider_reference,
        'status_code' => '200',
        'gross_amount' => $grossAmount,
        'signature_key' => hash('sha512', $invoice->provider_reference.'200'.$grossAmount.'server-key'),
        'transaction_time' => now()->toDateTimeString(),
        'currency' => 'IDR',
    ];

    $this->postJson(route('payments.midtrans.webhook'), $payload)
        ->assertOk()
        ->assertJsonPath('invoice_status', 'paid')
        ->assertJsonPath('subscription_status', 'active');

    $invoice->refresh();
    $workspace['subscription']->refresh();

    expect($invoice->status)->toBe(SaasInvoiceStatus::Paid)
        ->and($invoice->paid_at)->not->toBeNull()
        ->and($workspace['subscription']->status)->toBe(SubscriptionStatus::Active)
        ->and($workspace['subscription']->trial_ends_at)->toBeNull()
        ->and($workspace['subscription']->current_period_ends_at)->not->toBeNull();

    $this->postJson(route('payments.midtrans.webhook'), $payload)
        ->assertOk()
        ->assertJsonPath('duplicate', true);
});
