<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentRefundStatus;
use App\Enums\PaymentStatus;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\Payment;
use App\Models\PaymentGatewayCredential;
use App\Models\PaymentRefund;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\PermissionRegistrar;

/** @return array{user: User, tenant: Tenant, outlet: Outlet, order: Order, payment: Payment} */
function createPaymentRefundWorkspace(bool $canRefund = true): array
{
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withTrialSubscription()->create();
    $outlet = Outlet::factory()->for($tenant)->create();
    $tenant->users()->attach($user, [
        'status' => 'active',
        'is_owner' => false,
        'joined_at' => now(),
    ]);

    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($tenant->id);
    $role = Role::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'refund-manager-'.$tenant->id,
        'guard_name' => 'web',
    ]);

    if ($canRefund) {
        $role->givePermissionTo(Permission::query()->firstOrCreate([
            'name' => 'payment.refund',
            'guard_name' => 'web',
        ]));
    }

    $user->assignRole($role);
    $registrar->setPermissionsTeamId(null);

    $credential = PaymentGatewayCredential::factory()->for($tenant)->create([
        'secret' => 'tenant-refund-key',
    ]);
    $table = DiningTable::factory()->for($outlet)->create();
    $order = Order::factory()->for($table, 'table')->create([
        'status' => OrderStatus::Paid,
        'paid_at' => now(),
    ]);
    $payment = Payment::factory()->for($order)->create([
        'status' => PaymentStatus::Paid,
        'amount' => 28000,
        'provider' => 'midtrans',
        'gateway_credential_id' => $credential->id,
        'provider_reference' => 'meja-payment-'.$order->id,
        'paid_at' => now(),
    ]);

    return compact('user', 'tenant', 'outlet', 'order', 'payment');
}

function paymentRefundSession(array $workspace): array
{
    return [
        'active_tenant_id' => $workspace['tenant']->id,
        'active_outlet_id' => $workspace['outlet']->id,
    ];
}

test('authorized staff can issue an idempotent full Midtrans refund', function () {
    $workspace = createPaymentRefundWorkspace();
    Http::fake([
        'https://api.sandbox.midtrans.com/v2/*/refund' => Http::response([
            'status_code' => '200',
            'status_message' => 'Success, refund transaction is successful',
            'refund_chargeback_id' => 'refund-123',
        ]),
    ]);

    $response = $this->actingAs($workspace['user'])
        ->withSession(paymentRefundSession($workspace))
        ->withHeaders(['Idempotency-Key' => 'refund-order-1'])
        ->post(route('orders.refund', $workspace['order']), [
            'reason' => 'Permintaan refund pelanggan',
        ]);

    $response->assertRedirect(route('orders'));
    $refund = PaymentRefund::withoutGlobalScopes()->firstOrFail();

    expect($refund->status)->toBe(PaymentRefundStatus::Succeeded)
        ->and($refund->amount)->toBe($workspace['payment']->amount)
        ->and($refund->provider_reference)->toBe('refund-123')
        ->and($workspace['payment']->fresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($workspace['order']->fresh()->status)->toBe(OrderStatus::Refunded);
    $this->assertDatabaseHas('audit_logs', [
        'event' => 'payment.refunded',
        'auditable_id' => $refund->id,
    ]);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://api.sandbox.midtrans.com/v2/'.$workspace['payment']->provider_reference.'/refund'
        && $request->header('Authorization') === ['Basic '.base64_encode('tenant-refund-key:')]
        && $request->data() === [
            'refund_key' => $refund->provider_refund_key,
            'amount' => $workspace['payment']->amount,
            'reason' => 'Permintaan refund pelanggan',
        ]);

    $this->actingAs($workspace['user'])
        ->withSession(paymentRefundSession($workspace))
        ->withHeaders(['Idempotency-Key' => 'refund-order-1'])
        ->post(route('orders.refund', $workspace['order']), [
            'reason' => 'Alasan berbeda tidak boleh mengulang refund',
        ])
        ->assertRedirect(route('orders'));
    Http::assertSentCount(1);
});

test('refund gateway failures require reconciliation and preserve payment state', function () {
    $workspace = createPaymentRefundWorkspace();
    Http::fake([
        'https://api.sandbox.midtrans.com/v2/*/refund' => Http::response([
            'status_code' => '500',
            'status_message' => 'Temporary failure',
        ], 500),
    ]);

    $this->actingAs($workspace['user'])
        ->withSession(paymentRefundSession($workspace))
        ->withHeaders(['Idempotency-Key' => 'refund-failure-1'])
        ->from(route('orders'))
        ->post(route('orders.refund', $workspace['order']), ['reason' => 'Gateway error test'])
        ->assertRedirect(route('orders'))
        ->assertSessionHasErrors('refund');

    $refund = PaymentRefund::withoutGlobalScopes()->firstOrFail();
    expect($refund->status)->toBe(PaymentRefundStatus::Pending)
        ->and($workspace['payment']->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($workspace['order']->fresh()->status)->toBe(OrderStatus::Paid);
    $this->assertDatabaseHas('audit_logs', [
        'event' => 'payment.refund_reconciliation_required',
        'auditable_id' => $refund->id,
    ]);
});

test('a different idempotency key cannot start a second refund while one is pending', function () {
    $workspace = createPaymentRefundWorkspace();
    PaymentRefund::withoutGlobalScopes()->create([
        'tenant_id' => $workspace['tenant']->id,
        'outlet_id' => $workspace['outlet']->id,
        'payment_id' => $workspace['payment']->id,
        'idempotency_key' => 'refund-in-flight-1',
        'provider' => 'midtrans',
        'provider_refund_key' => 'meja-refund-in-flight-1',
        'status' => PaymentRefundStatus::Pending,
        'amount' => $workspace['payment']->amount,
        'currency' => $workspace['payment']->currency,
        'reason' => 'Refund sebelumnya sedang diproses',
        'requested_by' => $workspace['user']->id,
        'requested_at' => now(),
    ]);
    Http::fake();

    $this->actingAs($workspace['user'])
        ->withSession(paymentRefundSession($workspace))
        ->withHeaders(['Idempotency-Key' => 'refund-in-flight-2'])
        ->post(route('orders.refund', $workspace['order']), ['reason' => 'Tidak boleh refund kedua'])
        ->assertConflict();

    expect(PaymentRefund::withoutGlobalScopes()->count())->toBe(1);
    Http::assertNothingSent();
});

test('refund route requires the payment refund permission and active outlet context', function () {
    $workspace = createPaymentRefundWorkspace(false);
    Http::fake();

    $this->actingAs($workspace['user'])
        ->withSession(paymentRefundSession($workspace))
        ->withHeaders(['Idempotency-Key' => 'refund-forbidden'])
        ->post(route('orders.refund', $workspace['order']), ['reason' => 'Tidak boleh'])
        ->assertForbidden();

    expect(PaymentRefund::withoutGlobalScopes()->count())->toBe(0)
        ->and($workspace['payment']->fresh()->status)->toBe(PaymentStatus::Paid);
    Http::assertNothingSent();
});
