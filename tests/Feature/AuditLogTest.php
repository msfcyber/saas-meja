<?php

use App\Actions\Tenancy\CreateOwnerWorkspace;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\AuditLog;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\MidtransSubscriptionWebhookService;
use App\Services\PaymentWebhookService;
use App\Services\SaasInvoiceService;
use App\Support\Tenancy\TenantContext;

test('owner workspace provisioning records sensitive setup changes with the actor', function () {
    $owner = User::factory()->create();
    $workspace = app(CreateOwnerWorkspace::class)->handle($owner, [
        'business_name' => 'Audit Cafe',
        'outlet_name' => 'Audit Cafe Pusat',
        'address' => null,
        'phone' => null,
        'timezone' => 'Asia/Jakarta',
        'tax_enabled' => true,
        'tax_name' => 'PB1',
        'tax_rate' => 10.0,
        'tax_inclusive' => false,
    ]);

    $logs = AuditLog::withoutGlobalScopes()
        ->where('tenant_id', $workspace['tenant']->id)
        ->orderBy('id')
        ->get();

    expect($logs->pluck('event')->all())
        ->toContain('subscription.created', 'role.permissions_provisioned', 'tax_setting.created');

    $roleLog = $logs->firstWhere('event', 'role.permissions_provisioned');
    $taxLog = $logs->firstWhere('event', 'tax_setting.created');

    expect($roleLog?->actor_id)->toBe($owner->id)
        ->and($taxLog?->actor_id)->toBe($owner->id)
        ->and($taxLog?->new_values['rate_basis_points'])->toBe(1000)
        ->and($taxLog?->new_values['is_enabled'])->toBeTrue();
});

test('payment status changes create one curated audit event per processed transition', function () {
    $tenant = Tenant::factory()->create();
    $outlet = $tenant->outlets()->create([
        'name' => 'Audit Outlet',
        'slug' => 'audit-outlet',
        'code' => 'AUD-001',
        'timezone' => 'Asia/Jakarta',
        'currency' => 'IDR',
        'is_active' => true,
        'accepts_orders' => true,
    ]);
    $table = DiningTable::factory()->for($outlet)->create();
    $order = Order::factory()->for($table, 'table')->create([
        'tenant_id' => $tenant->id,
        'outlet_id' => $outlet->id,
        'status' => OrderStatus::AwaitingPayment,
    ]);
    $payment = Payment::factory()->for($order)->create([
        'tenant_id' => $tenant->id,
        'outlet_id' => $outlet->id,
        'provider' => 'generic',
        'provider_reference' => 'audit-payment-1',
    ]);
    $payload = [
        'event_id' => 'audit-event-1',
        'event_type' => 'payment.paid',
        'provider_reference' => $payment->provider_reference,
        'amount' => $payment->amount,
        'currency' => 'IDR',
        'occurred_at' => now()->toIso8601String(),
    ];

    app(PaymentWebhookService::class)->handle('generic', $payload);
    app(PaymentWebhookService::class)->handle('generic', $payload);

    $log = AuditLog::withoutGlobalScopes()
        ->where('event', 'payment.status_changed')
        ->where('auditable_id', $payment->id)
        ->firstOrFail();

    expect(AuditLog::withoutGlobalScopes()->where('event', 'payment.status_changed')->count())->toBe(1)
        ->and($log->old_values)->toMatchArray([
            'status' => PaymentStatus::Pending->value,
            'order_status' => OrderStatus::AwaitingPayment->value,
        ])
        ->and($log->new_values)->toMatchArray([
            'status' => PaymentStatus::Paid->value,
            'order_status' => OrderStatus::Paid->value,
            'event_type' => 'payment.paid',
            'provider' => 'generic',
        ])
        ->and(array_key_exists('signature_key', $log->new_values ?? []))->toBeFalse();
});

test('subscription invoice lifecycle records creation and verified status changes', function () {
    $tenant = Tenant::factory()->create();
    $subscription = Subscription::factory()->for($tenant)->create([
        'status' => SubscriptionStatus::Trialing,
        'trial_ends_at' => now()->addDay(),
    ]);
    $invoice = app(SaasInvoiceService::class)->pendingFor($subscription);
    config(['payments.midtrans.server_key' => 'audit-server-key']);
    $grossAmount = $invoice->amount.'.00';
    $payload = [
        'transaction_id' => 'audit-subscription-transaction',
        'transaction_status' => 'settlement',
        'order_id' => $invoice->provider_reference,
        'status_code' => '200',
        'gross_amount' => $grossAmount,
        'signature_key' => hash('sha512', $invoice->provider_reference.'200'.$grossAmount.'audit-server-key'),
        'transaction_time' => now()->toDateTimeString(),
        'currency' => 'IDR',
    ];

    app(MidtransSubscriptionWebhookService::class)->handle($payload);

    $logs = AuditLog::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->whereIn('event', ['subscription.invoice_created', 'subscription.invoice_status_changed'])
        ->orderBy('id')
        ->get();
    $statusLog = $logs->firstWhere('event', 'subscription.invoice_status_changed');

    expect($logs->pluck('event')->all())->toBe([
        'subscription.invoice_created',
        'subscription.invoice_status_changed',
    ])
        ->and($statusLog?->old_values['invoice_status'])->toBe('pending')
        ->and($statusLog?->old_values['subscription_status'])->toBe(SubscriptionStatus::Trialing->value)
        ->and($statusLog?->new_values['invoice_status'])->toBe('paid')
        ->and($statusLog?->new_values['subscription_status'])->toBe(SubscriptionStatus::Active->value);
});

test('audit logs follow the active tenant scope', function () {
    $firstTenant = Tenant::factory()->create();
    $secondTenant = Tenant::factory()->create();
    $audits = app(AuditLogService::class);

    $audits->record('tenant.first.event', ['tenant_id' => $firstTenant->id]);
    $audits->record('tenant.second.event', ['tenant_id' => $secondTenant->id]);

    $context = app(TenantContext::class);
    $context->setTenant($firstTenant);
    $context->markResolved();

    try {
        expect(AuditLog::query()->pluck('event')->all())->toBe(['tenant.first.event']);
    } finally {
        $context->clear();
    }
});
