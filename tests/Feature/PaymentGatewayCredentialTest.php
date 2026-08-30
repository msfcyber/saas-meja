<?php

use App\Actions\Tenancy\CreateOwnerWorkspace;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\AuditLog;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\Payment;
use App\Models\PaymentGatewayCredential;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PaymentCheckoutService;
use App\Services\PaymentGatewayCredentialService;
use App\Services\PaymentGatewayException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\PermissionRegistrar;

/**
 * @return array{owner: User, tenant: Tenant, outlet: Outlet}
 */
function createGatewaySettingsWorkspace(): array
{
    $owner = User::factory()->create();
    $workspace = app(CreateOwnerWorkspace::class)->handle($owner, [
        'business_name' => 'Gateway Settings',
        'outlet_name' => 'Gateway Settings Pusat',
        'address' => null,
        'phone' => null,
        'timezone' => 'Asia/Jakarta',
        'tax_enabled' => false,
        'tax_name' => null,
        'tax_rate' => null,
        'tax_inclusive' => false,
    ]);

    return [
        'owner' => $owner,
        'tenant' => $workspace['tenant'],
        'outlet' => $workspace['outlet'],
    ];
}

/** @param array{tenant: Tenant, outlet: Outlet} $workspace */
function gatewaySettingsSession(array $workspace): array
{
    return [
        'active_tenant_id' => $workspace['tenant']->id,
        'active_outlet_id' => $workspace['outlet']->id,
    ];
}

function grantGatewayPermission(User $user, Tenant $tenant): void
{
    $registrar = app(PermissionRegistrar::class);
    $registrar->forgetCachedPermissions();
    $registrar->setPermissionsTeamId($tenant->id);
    $permission = Permission::query()->firstOrCreate([
        'name' => 'gateway.manage',
        'guard_name' => 'web',
    ]);
    $role = Role::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'gateway-member-'.$tenant->id,
        'guard_name' => 'web',
    ]);
    $role->givePermissionTo($permission);
    $user->assignRole($role);
    $user->unsetRelation('roles')->unsetRelation('permissions');
    $registrar->setPermissionsTeamId(null);
}

/** @return array{order: Order, payment: Payment} */
function createGatewayOrderPayment(Tenant $tenant): array
{
    $outlet = Outlet::factory()->for($tenant)->create();
    $table = DiningTable::factory()->for($outlet)->create();
    $order = Order::factory()->for($table, 'table')->create([
        'tenant_id' => $tenant->id,
        'outlet_id' => $outlet->id,
        'status' => OrderStatus::AwaitingPayment,
    ]);
    $payment = Payment::factory()->for($order)->create([
        'tenant_id' => $tenant->id,
        'outlet_id' => $outlet->id,
        'provider' => 'midtrans',
        'provider_reference' => 'gateway-payment-'.$order->id,
    ]);

    return ['order' => $order, 'payment' => $payment];
}

test('tenant gateway secrets are encrypted at rest and hidden from serialization', function () {
    $tenant = Tenant::factory()->create();
    $creator = User::factory()->create();
    $secret = 'tenant-encryption-secret';

    $credential = app(PaymentGatewayCredentialService::class)->rotate(
        $tenant,
        $creator,
        'midtrans',
        $secret,
    );
    $storedSecret = DB::table('payment_gateway_credentials')
        ->where('id', $credential->id)
        ->value('secret');

    expect($storedSecret)->toBeString()
        ->and($storedSecret)->not->toBe($secret)
        ->and($credential->fresh()->secret)->toBe($secret)
        ->and($credential->toArray())->not->toHaveKey('secret')
        ->and($credential->toArray())->not->toHaveKey('fingerprint');

    $credential->update(['metadata' => ['environment' => 'sandbox']]);

    expect($credential->fresh()->metadata)->toBe(['environment' => 'sandbox']);
});

test('gateway settings are restricted to owners with gateway permission', function () {
    $workspace = createGatewaySettingsWorkspace();
    $member = User::factory()->create();
    $workspace['tenant']->users()->attach($member, [
        'status' => 'active',
        'is_owner' => false,
        'joined_at' => now(),
    ]);
    grantGatewayPermission($member, $workspace['tenant']);
    $session = gatewaySettingsSession($workspace);

    $this->actingAs($workspace['owner'])
        ->withSession($session)
        ->get(route('gateway.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/gateway')
            ->where('gateway.configured', false)
            ->where('gateway.provider', 'midtrans')
            ->where('tenancy.is_owner', true)
            ->missing('gateway.secret')
            ->missing('gateway.server_key')
            ->missing('gateway.metadata')
            ->missing('gateway.fingerprint'));

    $this->actingAs($member)
        ->withSession($session)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('tenancy.is_owner', false));

    $this->actingAs($member)
        ->withSession($session)
        ->get(route('gateway.edit'))
        ->assertForbidden();

    $this->actingAs($member)
        ->withSession($session)
        ->post(route('gateway.rotate'), ['server_key' => 'member-secret'])
        ->assertForbidden();
});

test('gateway rotation retires the previous version and audits only safe metadata', function () {
    $workspace = createGatewaySettingsWorkspace();
    $session = gatewaySettingsSession($workspace);

    $this->actingAs($workspace['owner'])
        ->withSession($session)
        ->post(route('gateway.rotate'), ['server_key' => 'first-tenant-secret'])
        ->assertRedirect(route('gateway.edit'));
    $this->actingAs($workspace['owner'])
        ->withSession($session)
        ->post(route('gateway.rotate'), ['server_key' => 'second-tenant-secret'])
        ->assertRedirect(route('gateway.edit'));

    $credentials = PaymentGatewayCredential::withoutGlobalScopes()
        ->where('tenant_id', $workspace['tenant']->id)
        ->orderBy('version')
        ->get();
    $logs = AuditLog::withoutGlobalScopes()
        ->where('tenant_id', $workspace['tenant']->id)
        ->where('event', 'gateway.credential_rotated')
        ->orderBy('id')
        ->get();

    expect($credentials)->toHaveCount(2)
        ->and($credentials[0]->version)->toBe(1)
        ->and($credentials[0]->retired_at)->not->toBeNull()
        ->and($credentials[0]->created_by)->toBe($workspace['owner']->id)
        ->and($credentials[1]->version)->toBe(2)
        ->and($credentials[1]->retired_at)->toBeNull()
        ->and($credentials[1]->created_by)->toBe($workspace['owner']->id)
        ->and($credentials[0]->secret)->toBe('first-tenant-secret')
        ->and($credentials[1]->secret)->toBe('second-tenant-secret')
        ->and($logs)->toHaveCount(2)
        ->and($logs[1]->old_values)->toMatchArray([
            'provider' => 'midtrans',
            'version' => 1,
            'credential_id' => $credentials[0]->id,
        ])
        ->and($logs[1]->new_values)->toMatchArray([
            'provider' => 'midtrans',
            'version' => 2,
            'credential_id' => $credentials[1]->id,
            'previous_credential_id' => $credentials[0]->id,
        ]);

    foreach ($logs as $log) {
        $serialized = json_encode($log->getAttributes(), JSON_THROW_ON_ERROR);

        expect($serialized)
            ->not->toContain('first-tenant-secret')
            ->not->toContain('second-tenant-secret')
            ->not->toContain('secret')
            ->not->toContain('fingerprint')
            ->not->toContain('ciphertext');
    }
});

test('gateway credentials and payment binding cannot cross tenants', function () {
    $first = createGatewaySettingsWorkspace();
    $second = createGatewaySettingsWorkspace();
    $credentials = app(PaymentGatewayCredentialService::class);
    $firstCredential = $credentials->rotate($first['tenant'], $first['owner'], 'midtrans', 'first-secret');
    $secondCredential = $credentials->rotate($second['tenant'], $second['owner'], 'midtrans', 'second-secret');
    $firstPayment = createGatewayOrderPayment($first['tenant'])['payment'];

    expect(fn () => $credentials->bind($firstPayment, $secondCredential))
        ->toThrow(PaymentGatewayException::class);

    expect(fn () => $firstPayment->update(['gateway_credential_id' => $secondCredential->id]))
        ->toThrow(QueryException::class);

    expect($firstPayment->fresh()->gateway_credential_id)->toBeNull()
        ->and($credentials->forPayment($firstPayment->fresh())->id)->toBe($firstCredential->id)
        ->and(PaymentGatewayCredential::withoutGlobalScopes()
            ->where('tenant_id', $first['tenant']->id)
            ->orderBy('id')
            ->pluck('id')
            ->all())->toBe([$firstCredential->id])
        ->and(PaymentGatewayCredential::withoutGlobalScopes()
            ->where('tenant_id', $second['tenant']->id)
            ->orderBy('id')
            ->pluck('id')
            ->all())->toBe([$secondCredential->id]);
});

test('new order checkout uses the tenant credential instead of the platform config key', function () {
    $workspace = createGatewaySettingsWorkspace();
    $credential = app(PaymentGatewayCredentialService::class)->rotate(
        $workspace['tenant'],
        $workspace['owner'],
        'midtrans',
        'tenant-checkout-secret',
    );
    $paymentData = createGatewayOrderPayment($workspace['tenant']);
    config(['payments.midtrans.server_key' => 'platform-subscription-secret']);
    Http::fake([
        config('payments.midtrans.snap_url') => Http::response([
            'token' => 'tenant-snap-token',
            'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/tenant-snap-token',
        ]),
    ]);

    app(PaymentCheckoutService::class)->start(
        $paymentData['payment'],
        $paymentData['order'],
        'https://meja.test/order/finish',
    );

    expect($paymentData['payment']->fresh()->gateway_credential_id)->toBe($credential->id);
    Http::assertSent(fn (Request $request): bool => $request->header('Authorization') === [
        'Basic '.base64_encode('tenant-checkout-secret:'),
    ]);
});

test('a linked payment still accepts a webhook signed with its retired credential', function () {
    $workspace = createGatewaySettingsWorkspace();
    $credentials = app(PaymentGatewayCredentialService::class);
    $oldCredential = $credentials->rotate(
        $workspace['tenant'],
        $workspace['owner'],
        'midtrans',
        'retired-webhook-secret',
    );
    $paymentData = createGatewayOrderPayment($workspace['tenant']);
    $payment = $paymentData['payment'];
    $credentials->forPayment($payment);
    $newCredential = $credentials->rotate(
        $workspace['tenant'],
        $workspace['owner'],
        'midtrans',
        'active-webhook-secret',
    );
    config(['payments.midtrans.server_key' => 'platform-subscription-secret']);
    $grossAmount = $payment->amount.'.00';
    $payload = [
        'transaction_id' => 'retired-webhook-transaction',
        'transaction_status' => 'settlement',
        'order_id' => $payment->provider_reference,
        'status_code' => '200',
        'gross_amount' => $grossAmount,
        'signature_key' => hash(
            'sha512',
            $payment->provider_reference.'200'.$grossAmount.'retired-webhook-secret',
        ),
        'transaction_time' => now()->toDateTimeString(),
        'settlement_time' => now()->toDateTimeString(),
        'payment_type' => 'qris',
    ];

    $this->postJson(route('payments.midtrans.webhook'), $payload)
        ->assertOk()
        ->assertJsonPath('payment_status', PaymentStatus::Paid->value);

    expect($payment->fresh()->gateway_credential_id)->toBe($oldCredential->id)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($oldCredential->fresh()->retired_at)->not->toBeNull()
        ->and($newCredential->fresh()->retired_at)->toBeNull();
});
