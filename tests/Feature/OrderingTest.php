<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Modifier;
use App\Models\ModifierOption;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\PaymentGatewayCredential;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Subscription;
use App\Models\TableQrToken;
use App\Models\TaxSetting;
use App\Models\Tenant;
use App\Services\PublicOrderService;
use App\Services\PublicTableAccessService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{tenant: Tenant, outlet: Outlet, product: Product, variant: ProductVariant, option: ModifierOption, token: string}
 */
function createOrderingWorkspace(bool $withGatewayCredential = false): array
{
    $tenant = Tenant::factory()->withTrialSubscription()->create();

    if ($withGatewayCredential) {
        PaymentGatewayCredential::factory()->for($tenant)->create([
            'secret' => 'tenant-midtrans-key',
        ]);
    }

    $outlet = Outlet::factory()->for($tenant)->create(['accepts_orders' => true]);
    $category = Category::factory()->for($outlet)->create(['is_active' => true]);
    $product = Product::factory()->for($category)->create([
        'base_price' => 28000,
        'is_active' => true,
        'is_available' => true,
    ]);
    $variant = ProductVariant::factory()->for($product)->create([
        'name' => 'Large',
        'price_delta' => 5000,
        'is_default' => false,
        'is_active' => true,
    ]);
    $modifier = Modifier::factory()->for($outlet)->required()->create([
        'name' => 'Level Pedas',
        'maximum_selections' => 1,
        'is_active' => true,
    ]);
    $option = ModifierOption::factory()->for($modifier)->create([
        'name' => 'Sedang',
        'price_delta' => 3000,
        'is_active' => true,
    ]);
    $product->modifiers()->attach($modifier, [
        'tenant_id' => $tenant->id,
        'outlet_id' => $outlet->id,
        'position' => 0,
    ]);
    TaxSetting::factory()->for($outlet)->enabled()->create([
        'rate_basis_points' => 1000,
        'is_inclusive' => false,
    ]);

    $table = DiningTable::factory()->for($outlet)->create(['is_active' => true]);
    $token = str_repeat('a', 64);
    TableQrToken::factory()->for($table, 'table')->create([
        'token_hash' => hash('sha256', $token),
    ]);

    return compact('tenant', 'outlet', 'product', 'variant', 'option', 'token');
}

/** @return array<string, mixed> */
function orderingPayload(array $workspace, string $key = 'checkout-1'): array
{
    return [
        'qr_token' => $workspace['token'],
        'idempotency_key' => $key,
        'customer_name' => 'Raka',
        'payment_method' => 'qris',
        'items' => [
            [
                'product_id' => $workspace['product']->id,
                'variant_id' => $workspace['variant']->id,
                'modifier_option_ids' => [$workspace['option']->id],
                'quantity' => 2,
                'note' => 'Tanpa bawang',
            ],
        ],
    ];
}

function createOrder(TestResponse $response): Order
{
    return Order::withoutGlobalScopes()->where('order_number', $response->json('order.number'))->firstOrFail();
}

/** @param array<string, mixed> $payload */
function paymentWebhookHeaders(array $payload, int $timestamp, string $provider = 'generic'): array
{
    $raw = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = hash_hmac('sha256', $provider.'.'.$timestamp.'.'.$raw, (string) config('payments.webhook_secret'));

    return [
        'X-Payment-Timestamp' => (string) $timestamp,
        'X-Payment-Signature' => 'sha256='.$signature,
    ];
}

test('guest checkout validates the QR context and snapshots the final server price', function () {
    $workspace = createOrderingWorkspace();
    $payload = orderingPayload($workspace);
    $payload['items'][0]['base_price'] = 1;
    $payload['tenant_id'] = 999999;
    $payload['outlet_id'] = 999999;

    $response = $this->postJson(route('public.orders.store'), $payload);

    $response->assertCreated()
        ->assertJsonPath('order.status', OrderStatus::AwaitingPayment->value)
        ->assertJsonPath('order.payment_status', PaymentStatus::Pending->value)
        ->assertJsonPath('order.subtotal', 72000)
        ->assertJsonPath('order.tax_amount', 7200)
        ->assertJsonPath('order.grand_total', 79200)
        ->assertJsonMissingPath('order.access_token_hash');

    $order = createOrder($response);
    $item = $order->items()->withoutGlobalScopes()->firstOrFail();

    expect($order->tenant_id)->toBe($workspace['tenant']->id)
        ->and($order->outlet_id)->toBe($workspace['outlet']->id)
        ->and($order->table_id)->toBe($workspace['outlet']->tables()->firstOrFail()->id)
        ->and($order->outlet_name_snapshot)->toBe($workspace['outlet']->name)
        ->and($order->outlet_address_snapshot)->toBe($workspace['outlet']->address)
        ->and($order->outlet_phone_snapshot)->toBe($workspace['outlet']->phone)
        ->and($order->table_name_snapshot)->toBe($workspace['outlet']->tables()->firstOrFail()->name)
        ->and($order->table_code_snapshot)->toBe($workspace['outlet']->tables()->firstOrFail()->code)
        ->and($item->product_name_snapshot)->toBe($workspace['product']->name)
        ->and($item->variant_name_snapshot)->toBe('Large')
        ->and($item->unit_price)->toBe(36000)
        ->and($item->line_total)->toBe(72000)
        ->and($item->modifiers()->withoutGlobalScopes()->count())->toBe(1)
        ->and(Payment::withoutGlobalScopes()->where('order_id', $order->id)->count())->toBe(1)
        ->and($order->statusHistories()->withoutGlobalScopes()->pluck('to_status')->map(fn (OrderStatus $status) => $status->value)->all())->toBe([
            OrderStatus::Draft->value,
            OrderStatus::AwaitingPayment->value,
        ]);
});

test('guest cart preview returns a server fingerprint and stale quotes cannot create an order', function () {
    $workspace = createOrderingWorkspace();

    $preview = $this->postJson(route('public.carts.validate'), [
        'qr_token' => $workspace['token'],
        'items' => orderingPayload($workspace)['items'],
    ]);

    $preview->assertOk()
        ->assertJsonPath('quote.items.0.product_id', $workspace['product']->id)
        ->assertJsonPath('quote.items.0.variant_id', $workspace['variant']->id)
        ->assertJsonPath('quote.items.0.unit_price', 36000)
        ->assertJsonPath('quote.items.0.line_total', 72000)
        ->assertJsonPath('quote.subtotal', 72000)
        ->assertJsonPath('quote.tax_amount', 7200)
        ->assertJsonPath('quote.grand_total', 79200);

    expect($preview->json('quote.fingerprint'))
        ->toBeString()
        ->toHaveLength(64);

    $workspace['product']->update(['base_price' => 30000]);
    $payload = orderingPayload($workspace);
    $payload['quote_fingerprint'] = $preview->json('quote.fingerprint');

    $this->postJson(route('public.orders.store'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('quote');

    expect(Order::withoutGlobalScopes()->count())->toBe(0);
});

test('repeating a checkout with the same idempotency key returns the original order', function () {
    $workspace = createOrderingWorkspace();
    $payload = orderingPayload($workspace, 'same-checkout');

    $first = $this->postJson(route('public.orders.store'), $payload)->assertCreated();
    $second = $this->postJson(route('public.orders.store'), $payload)->assertOk();

    $second->assertJsonPath('created', false)
        ->assertJsonPath('order.id', $first->json('order.id'))
        ->assertJsonPath('access_token', $first->json('access_token'));

    expect(Order::withoutGlobalScopes()->count())->toBe(1)
        ->and(Payment::withoutGlobalScopes()->count())->toBe(1);
});

test('a guest checkout starts an idempotent Midtrans Snap session after the order is stored', function () {
    Http::fake([
        'https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
            'token' => 'snap-token-123',
            'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token-123',
        ]),
    ]);
    $workspace = createOrderingWorkspace(true);
    $orderResponse = $this->postJson(route('public.orders.store'), orderingPayload($workspace))->assertCreated();
    $paymentResponse = $this->postJson(route('public.orders.payment.start', [
        'accessToken' => $orderResponse->json('access_token'),
    ]));

    $paymentResponse->assertOk()
        ->assertJsonPath('provider', 'midtrans')
        ->assertJsonPath('redirect_url', 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token-123');

    $order = createOrder($orderResponse);
    $payment = Payment::withoutGlobalScopes()->where('order_id', $order->id)->firstOrFail();
    $credential = PaymentGatewayCredential::withoutGlobalScopes()
        ->where('tenant_id', $workspace['tenant']->id)
        ->where('provider', 'midtrans')
        ->firstOrFail();

    expect($payment->provider)->toBe('midtrans')
        ->and($payment->gateway_credential_id)->toBe($credential->id)
        ->and($payment->provider_reference)->toBe('meja-payment-'.$payment->id)
        ->and($payment->metadata)->toMatchArray([
            'midtrans' => [
                'snap_token' => 'snap-token-123',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token-123',
            ],
        ]);
    Http::assertSent(fn (Request $request) => $request->url() === 'https://app.sandbox.midtrans.com/snap/v1/transactions'
        && $request->header('Authorization') === ['Basic '.base64_encode('tenant-midtrans-key:')]
        && $request->data()['transaction_details'] === [
            'order_id' => 'meja-payment-'.$payment->id,
            'gross_amount' => $payment->amount,
        ]
        && $request->data()['enabled_payments'] === ['qris']
        && $request->data()['callbacks'] === [
            'finish' => route('public.order', ['accessToken' => $orderResponse->json('access_token')]),
        ]);

    $this->postJson(route('public.orders.payment.start', [
        'accessToken' => $orderResponse->json('access_token'),
    ]))->assertOk();
    Http::assertSentCount(1);
});

test('a guest checkout keeps its pending payment when Midtrans is not configured', function () {
    $workspace = createOrderingWorkspace();
    $orderResponse = $this->postJson(route('public.orders.store'), orderingPayload($workspace))->assertCreated();

    $this->postJson(route('public.orders.payment.start', [
        'accessToken' => $orderResponse->json('access_token'),
    ]))->assertServiceUnavailable()
        ->assertJsonPath('message', 'Midtrans belum dikonfigurasi.');

    $order = createOrder($orderResponse);
    expect(Payment::withoutGlobalScopes()->where('order_id', $order->id)->firstOrFail()->status)
        ->toBe(PaymentStatus::Pending);
});

test('payment expiry command marks overdue payments and orders as expired', function () {
    $workspace = createOrderingWorkspace();
    $response = $this->postJson(route('public.orders.store'), orderingPayload($workspace))->assertCreated();
    $order = createOrder($response);
    $payment = Payment::withoutGlobalScopes()->where('order_id', $order->id)->firstOrFail();
    $payment->update(['expires_at' => now()->subSecond()]);

    $this->artisan('payments:expire')->assertExitCode(0);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Expired)
        ->and($order->fresh()->status)->toBe(OrderStatus::PaymentExpired);
    $this->assertDatabaseHas('audit_logs', [
        'event' => 'payment.status_changed',
        'auditable_id' => $payment->id,
    ]);
});

test('an expired payment creates a replacement for the same order', function () {
    Http::fake([
        'https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
            'token' => 'replacement-snap-token',
            'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/replacement-snap-token',
        ]),
    ]);
    $workspace = createOrderingWorkspace(true);
    $response = $this->postJson(route('public.orders.store'), orderingPayload($workspace))->assertCreated();
    $order = createOrder($response);
    $expiredPayment = Payment::withoutGlobalScopes()->where('order_id', $order->id)->firstOrFail();
    $expiredPayment->update(['expires_at' => now()->subSecond()]);

    $this->postJson(route('public.orders.payment.start', [
        'accessToken' => $response->json('access_token'),
    ]))->assertOk()
        ->assertJsonPath('redirect_url', 'https://app.sandbox.midtrans.com/snap/v2/vtweb/replacement-snap-token');

    $replacement = Payment::withoutGlobalScopes()
        ->where('order_id', $order->id)
        ->latest('id')
        ->firstOrFail();

    expect(Order::withoutGlobalScopes()->count())->toBe(1)
        ->and(Payment::withoutGlobalScopes()->where('order_id', $order->id)->count())->toBe(2)
        ->and($expiredPayment->fresh()->status)->toBe(PaymentStatus::Expired)
        ->and($replacement->status)->toBe(PaymentStatus::Pending)
        ->and($replacement->provider_reference)->not->toBe($expiredPayment->provider_reference)
        ->and($replacement->expires_at?->isFuture())->toBeTrue()
        ->and($order->fresh()->status)->toBe(OrderStatus::AwaitingPayment);
    $this->assertDatabaseHas('audit_logs', [
        'event' => 'payment.retried',
        'auditable_id' => $replacement->id,
    ]);
});

test('a failed payment can be retried with a different payment method', function () {
    Http::fake([
        'https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
            'token' => 'failed-retry-token',
            'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/failed-retry-token',
        ]),
    ]);
    $workspace = createOrderingWorkspace(true);
    $response = $this->postJson(route('public.orders.store'), orderingPayload($workspace))->assertCreated();
    $order = createOrder($response);
    $failedPayment = Payment::withoutGlobalScopes()->where('order_id', $order->id)->firstOrFail();
    $failedPayment->update(['status' => PaymentStatus::Failed]);

    $this->postJson(route('public.orders.payment.start', [
        'accessToken' => $response->json('access_token'),
    ]), ['payment_method' => 'va'])
        ->assertOk()
        ->assertJsonPath('redirect_url', 'https://app.sandbox.midtrans.com/snap/v2/vtweb/failed-retry-token');

    $replacement = Payment::withoutGlobalScopes()
        ->where('order_id', $order->id)
        ->latest('id')
        ->firstOrFail();

    expect($failedPayment->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and($replacement->method)->toBe('va')
        ->and($replacement->status)->toBe(PaymentStatus::Pending)
        ->and($order->fresh()->status)->toBe(OrderStatus::AwaitingPayment);
    Http::assertSent(fn (Request $request): bool => $request->data()['enabled_payments'] === [
        'bca_va',
        'bni_va',
        'bri_va',
        'permata_va',
        'other_va',
    ]);
});

test('checkout rejects a product from another outlet', function () {
    $workspace = createOrderingWorkspace();
    $otherOutlet = Outlet::factory()->for($workspace['tenant'])->create(['accepts_orders' => true]);
    $foreignProduct = Product::factory()->for(Category::factory()->for($otherOutlet))->create();
    $payload = orderingPayload($workspace);
    $payload['items'][0]['product_id'] = $foreignProduct->id;

    $this->postJson(route('public.orders.store'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items.0.product_id');

    expect(Order::withoutGlobalScopes()->count())->toBe(0);
});

test('checkout rejects a product that becomes unavailable after the menu is viewed', function () {
    $workspace = createOrderingWorkspace();
    $workspace['product']->update(['is_available' => false]);

    $this->postJson(route('public.orders.store'), orderingPayload($workspace))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items.0.product_id');

    expect(Order::withoutGlobalScopes()->count())->toBe(0);
});

test('checkout is rejected when the QR outlet stops accepting orders', function () {
    $workspace = createOrderingWorkspace();
    $workspace['outlet']->update(['accepts_orders' => false]);

    $this->postJson(route('public.orders.store'), orderingPayload($workspace))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('qr_token');
});

test('public menu exposes the active product configuration for cart selection', function () {
    $workspace = createOrderingWorkspace();

    $this->get(route('public.qr', ['qrToken' => $workspace['token']]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('customer/menu')
            ->where('access.qr_token', $workspace['token'])
            ->where('products.0.variants.0.id', $workspace['variant']->id)
            ->where('products.0.modifiers.0.id', $workspace['product']->modifiers()->firstOrFail()->id)
            ->where('products.0.modifiers.0.options.0.id', $workspace['option']->id),
        );
});

test('customer tracking is scoped to the random order access token', function () {
    $workspace = createOrderingWorkspace();
    $response = $this->postJson(route('public.orders.store'), orderingPayload($workspace))->assertCreated();
    $accessToken = $response->json('access_token');

    $this->get(route('public.order', ['accessToken' => $accessToken]))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertInertia(fn (Assert $page) => $page
            ->component('customer/tracking')
            ->where('access.valid', true)
            ->where('realtime.channel', 'order.'.hash('sha256', $accessToken))
            ->where('realtime.poll_url', route('public.orders.show', ['accessToken' => $accessToken]))
            ->where('order.number', $response->json('order.number'))
            ->where('order.items.0.product_name', $workspace['product']->name),
        );

    $this->getJson(route('public.orders.show', ['accessToken' => str_repeat('b', 64)]))
        ->assertNotFound();
});

test('checkout rechecks the subscription inside its transaction', function () {
    $workspace = createOrderingWorkspace();
    $access = app(PublicTableAccessService::class)->resolve($workspace['token']);

    if ($access === null) {
        throw new LogicException('QR test workspace tidak dapat diakses.');
    }

    $subscription = Subscription::withoutGlobalScopes()
        ->where('tenant_id', $workspace['tenant']->id)
        ->latest('id')
        ->firstOrFail();
    $subscription->update(['trial_ends_at' => now()->subSecond()]);

    expect(fn () => app(PublicOrderService::class)->create($access, orderingPayload($workspace)))
        ->toThrow(ValidationException::class);
    expect(Order::withoutGlobalScopes()->count())->toBe(0);
});

test('customer payment status exposes a safe retry URL without leaking gateway references', function () {
    $workspace = createOrderingWorkspace();
    $response = $this->postJson(route('public.orders.store'), orderingPayload($workspace))->assertCreated();
    $accessToken = $response->json('access_token');

    $this->getJson(route('public.orders.payment.status', ['accessToken' => $accessToken]))
        ->assertOk()
        ->assertJsonPath('status', PaymentStatus::Pending->value)
        ->assertJsonPath('provider', 'midtrans')
        ->assertJsonPath('redirect_url', null)
        ->assertJsonPath('start_url', route('public.orders.payment.start', ['accessToken' => $accessToken]))
        ->assertJsonMissingPath('provider_reference');

    $this->getJson(route('public.orders.payment.status', ['accessToken' => str_repeat('b', 64)]))
        ->assertNotFound();
});

test('receipt is unavailable before payment and uses immutable order snapshots after payment', function () {
    $workspace = createOrderingWorkspace();
    $response = $this->postJson(route('public.orders.store'), orderingPayload($workspace))->assertCreated();
    $accessToken = $response->json('access_token');
    $order = createOrder($response);
    $payment = Payment::withoutGlobalScopes()->where('order_id', $order->id)->firstOrFail();
    $snapshotName = $workspace['product']->name;

    $this->get(route('public.order.receipt', ['accessToken' => $accessToken]))
        ->assertNotFound();

    $workspace['product']->update(['name' => 'Nama produk terbaru']);
    $payment->update(['status' => PaymentStatus::Paid, 'paid_at' => now()]);
    $order->update(['status' => OrderStatus::Paid, 'paid_at' => now()]);

    $this->get(route('public.order.receipt', ['accessToken' => $accessToken]))
        ->assertOk()
        ->assertSee($snapshotName)
        ->assertDontSee('Nama produk terbaru');
    $this->getJson(route('public.orders.receipt', ['accessToken' => $accessToken]))
        ->assertOk()
        ->assertJsonPath('receipt.order.number', $order->order_number)
        ->assertJsonPath('receipt.items.0.product_name', $snapshotName)
        ->assertJsonPath('receipt.payment.status', PaymentStatus::Paid->value);
});

test('a valid payment webhook marks payment and order as paid exactly once', function () {
    config(['payments.webhook_secret' => 'test-webhook-secret']);
    $workspace = createOrderingWorkspace();
    $orderResponse = $this->postJson(route('public.orders.store'), orderingPayload($workspace))->assertCreated();
    $order = createOrder($orderResponse);
    $payment = Payment::withoutGlobalScopes()->where('order_id', $order->id)->firstOrFail();
    $payment->update(['provider' => 'generic', 'provider_reference' => 'pay-123']);
    $payload = [
        'event_id' => 'evt-paid-1',
        'event_type' => 'payment.paid',
        'provider_reference' => 'pay-123',
        'amount' => $payment->amount,
        'currency' => 'IDR',
        'occurred_at' => now()->toIso8601String(),
    ];
    $timestamp = now()->timestamp;

    $first = $this->withHeaders(paymentWebhookHeaders($payload, $timestamp))
        ->postJson(route('payments.webhook', ['provider' => 'generic']), $payload)
        ->assertOk()
        ->assertJsonPath('processed', true)
        ->assertJsonPath('duplicate', false)
        ->assertJsonPath('payment_status', PaymentStatus::Paid->value)
        ->assertJsonPath('order_status', OrderStatus::Paid->value);

    $this->withHeaders(paymentWebhookHeaders($payload, $timestamp))
        ->postJson(route('payments.webhook', ['provider' => 'generic']), $payload)
        ->assertOk()
        ->assertJsonPath('duplicate', true);

    $order->refresh();
    $payment->refresh();

    expect($first->json('payment_id'))->toBe($payment->id)
        ->and($payment->status)->toBe(PaymentStatus::Paid)
        ->and($order->status)->toBe(OrderStatus::Paid)
        ->and(PaymentEvent::withoutGlobalScopes()->count())->toBe(1)
        ->and($order->statusHistories()->withoutGlobalScopes()->pluck('to_status')->map(fn (OrderStatus $status) => $status->value)->all())->toContain(OrderStatus::Paid->value);
});

test('a late paid webhook cannot revive an expired payment or order', function () {
    config(['payments.webhook_secret' => 'test-webhook-secret']);
    $workspace = createOrderingWorkspace();
    $orderResponse = $this->postJson(route('public.orders.store'), orderingPayload($workspace))->assertCreated();
    $order = createOrder($orderResponse);
    $payment = Payment::withoutGlobalScopes()->where('order_id', $order->id)->firstOrFail();
    $payment->update([
        'provider' => 'generic',
        'provider_reference' => 'pay-expired-late',
        'expires_at' => now()->subSecond(),
    ]);
    $this->artisan('payments:expire')->assertExitCode(0);
    $payload = [
        'event_id' => 'evt-paid-after-expiry',
        'event_type' => 'payment.paid',
        'provider_reference' => 'pay-expired-late',
        'amount' => $payment->amount,
        'currency' => 'IDR',
        'occurred_at' => now()->toIso8601String(),
    ];

    $this->withHeaders(paymentWebhookHeaders($payload, now()->timestamp))
        ->postJson(route('payments.webhook', ['provider' => 'generic']), $payload)
        ->assertOk()
        ->assertJsonPath('payment_status', PaymentStatus::Expired->value)
        ->assertJsonPath('order_status', OrderStatus::PaymentExpired->value);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Expired)
        ->and($order->fresh()->status)->toBe(OrderStatus::PaymentExpired);
});

test('a valid Midtrans notification marks payment and order as paid exactly once', function () {
    $workspace = createOrderingWorkspace(true);
    $orderResponse = $this->postJson(route('public.orders.store'), orderingPayload($workspace))->assertCreated();
    $order = createOrder($orderResponse);
    $payment = Payment::withoutGlobalScopes()->where('order_id', $order->id)->firstOrFail();
    $occurredAt = now()->format('Y-m-d H:i:s');
    $grossAmount = $payment->amount.'.00';
    $payload = [
        'transaction_id' => 'midtrans-transaction-123',
        'transaction_status' => 'settlement',
        'order_id' => $payment->provider_reference,
        'status_code' => '200',
        'gross_amount' => $grossAmount,
        'transaction_time' => $occurredAt,
        'settlement_time' => $occurredAt,
        'payment_type' => 'qris',
    ];
    $payload['signature_key'] = hash(
        'sha512',
        $payload['order_id'].$payload['status_code'].$grossAmount.'tenant-midtrans-key',
    );

    $this->postJson(route('payments.midtrans.webhook'), $payload)
        ->assertOk()
        ->assertJsonPath('processed', true)
        ->assertJsonPath('duplicate', false)
        ->assertJsonPath('payment_status', PaymentStatus::Paid->value)
        ->assertJsonPath('order_status', OrderStatus::Paid->value);
    $this->postJson(route('payments.midtrans.webhook'), $payload)
        ->assertOk()
        ->assertJsonPath('duplicate', true);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and(PaymentEvent::withoutGlobalScopes()->count())->toBe(1);
});

test('payment reconciliation retrieves a Midtrans status and applies it through the webhook state machine', function () {
    $workspace = createOrderingWorkspace(true);
    $orderResponse = $this->postJson(route('public.orders.store'), orderingPayload($workspace))->assertCreated();
    $order = createOrder($orderResponse);
    $payment = Payment::withoutGlobalScopes()->where('order_id', $order->id)->firstOrFail();
    $occurredAt = now()->format('Y-m-d H:i:s');
    $grossAmount = $payment->amount.'.00';
    $statusPayload = [
        'transaction_id' => 'midtrans-status-123',
        'transaction_status' => 'settlement',
        'order_id' => $payment->provider_reference,
        'status_code' => '200',
        'gross_amount' => $grossAmount,
        'currency' => 'IDR',
        'transaction_time' => $occurredAt,
        'settlement_time' => $occurredAt,
        'payment_type' => 'qris',
        'fraud_status' => 'accept',
    ];
    $statusPayload['signature_key'] = hash(
        'sha512',
        $statusPayload['order_id'].$statusPayload['status_code'].$grossAmount.'tenant-midtrans-key',
    );
    Http::fake([
        'https://api.sandbox.midtrans.com/v2/*/status' => Http::response($statusPayload),
    ]);

    $this->artisan('payments:reconcile')
        ->expectsOutputToContain('1 diperiksa')
        ->assertSuccessful();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and(PaymentEvent::withoutGlobalScopes()->count())->toBe(1);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && $request->url() === 'https://api.sandbox.midtrans.com/v2/'.rawurlencode($payment->provider_reference).'/status');
});

test('a Midtrans notification rejects an invalid signature without changing state', function () {
    $workspace = createOrderingWorkspace(true);
    $orderResponse = $this->postJson(route('public.orders.store'), orderingPayload($workspace))->assertCreated();
    $order = createOrder($orderResponse);
    $payment = Payment::withoutGlobalScopes()->where('order_id', $order->id)->firstOrFail();

    $this->postJson(route('payments.midtrans.webhook'), [
        'transaction_id' => 'midtrans-transaction-invalid',
        'transaction_status' => 'settlement',
        'order_id' => $payment->provider_reference,
        'status_code' => '200',
        'gross_amount' => $payment->amount.'.00',
        'signature_key' => str_repeat('0', 128),
        'transaction_time' => now()->format('Y-m-d H:i:s'),
    ])->assertUnauthorized();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($order->fresh()->status)->toBe(OrderStatus::AwaitingPayment)
        ->and(PaymentEvent::withoutGlobalScopes()->count())->toBe(0);
});

test('a payment webhook rejects an invalid signature without changing state', function () {
    config(['payments.webhook_secret' => 'test-webhook-secret']);
    $workspace = createOrderingWorkspace();
    $orderResponse = $this->postJson(route('public.orders.store'), orderingPayload($workspace))->assertCreated();
    $order = createOrder($orderResponse);
    $payment = Payment::withoutGlobalScopes()->where('order_id', $order->id)->firstOrFail();
    $payment->update(['provider' => 'generic', 'provider_reference' => 'pay-invalid-signature']);
    $payload = [
        'event_id' => 'evt-invalid-signature',
        'event_type' => 'payment.paid',
        'provider_reference' => 'pay-invalid-signature',
        'amount' => $payment->amount,
        'currency' => 'IDR',
        'occurred_at' => now()->toIso8601String(),
    ];

    $this->withHeaders([
        'X-Payment-Timestamp' => (string) now()->timestamp,
        'X-Payment-Signature' => 'sha256='.str_repeat('0', 64),
    ])->postJson(route('payments.webhook', ['provider' => 'generic']), $payload)
        ->assertUnauthorized();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and(PaymentEvent::withoutGlobalScopes()->count())->toBe(0);
});

test('a late failed webhook cannot downgrade a paid payment', function () {
    config(['payments.webhook_secret' => 'test-webhook-secret']);
    $workspace = createOrderingWorkspace();
    $orderResponse = $this->postJson(route('public.orders.store'), orderingPayload($workspace))->assertCreated();
    $order = createOrder($orderResponse);
    $payment = Payment::withoutGlobalScopes()->where('order_id', $order->id)->firstOrFail();
    $payment->update(['provider' => 'generic', 'provider_reference' => 'pay-out-of-order']);
    $paidAt = now()->subMinute();
    $paidPayload = [
        'event_id' => 'evt-paid-ordered',
        'event_type' => 'payment.paid',
        'provider_reference' => 'pay-out-of-order',
        'amount' => $payment->amount,
        'currency' => 'IDR',
        'occurred_at' => $paidAt->toIso8601String(),
    ];
    $failedPayload = [
        'event_id' => 'evt-failed-late',
        'event_type' => 'payment.failed',
        'provider_reference' => 'pay-out-of-order',
        'amount' => $payment->amount,
        'currency' => 'IDR',
        'occurred_at' => $paidAt->subSeconds(10)->toIso8601String(),
    ];

    $this->withHeaders(paymentWebhookHeaders($paidPayload, now()->timestamp))
        ->postJson(route('payments.webhook', ['provider' => 'generic']), $paidPayload)
        ->assertOk();
    $this->withHeaders(paymentWebhookHeaders($failedPayload, now()->timestamp))
        ->postJson(route('payments.webhook', ['provider' => 'generic']), $failedPayload)
        ->assertOk();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and(PaymentEvent::withoutGlobalScopes()->count())->toBe(2);
});

test('a payment webhook rejects a mismatched amount', function () {
    config(['payments.webhook_secret' => 'test-webhook-secret']);
    $workspace = createOrderingWorkspace();
    $orderResponse = $this->postJson(route('public.orders.store'), orderingPayload($workspace))->assertCreated();
    $order = createOrder($orderResponse);
    $payment = Payment::withoutGlobalScopes()->where('order_id', $order->id)->firstOrFail();
    $payment->update(['provider' => 'generic', 'provider_reference' => 'pay-wrong-amount']);
    $payload = [
        'event_id' => 'evt-wrong-amount',
        'event_type' => 'payment.paid',
        'provider_reference' => 'pay-wrong-amount',
        'amount' => $payment->amount + 1,
        'currency' => 'IDR',
        'occurred_at' => now()->toIso8601String(),
    ];

    $this->withHeaders(paymentWebhookHeaders($payload, now()->timestamp))
        ->postJson(route('payments.webhook', ['provider' => 'generic']), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('amount');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and(PaymentEvent::withoutGlobalScopes()->count())->toBe(0);
});

test('an expired payment webhook closes an unpaid order without marking it paid', function () {
    config(['payments.webhook_secret' => 'test-webhook-secret']);
    $workspace = createOrderingWorkspace();
    $orderResponse = $this->postJson(route('public.orders.store'), orderingPayload($workspace))->assertCreated();
    $order = createOrder($orderResponse);
    $payment = Payment::withoutGlobalScopes()->where('order_id', $order->id)->firstOrFail();
    $payment->update(['provider' => 'generic', 'provider_reference' => 'pay-expired']);
    $payload = [
        'event_id' => 'evt-expired-1',
        'event_type' => 'payment.expired',
        'provider_reference' => 'pay-expired',
        'amount' => $payment->amount,
        'currency' => 'IDR',
        'occurred_at' => now()->toIso8601String(),
    ];

    $this->withHeaders(paymentWebhookHeaders($payload, now()->timestamp))
        ->postJson(route('payments.webhook', ['provider' => 'generic']), $payload)
        ->assertOk()
        ->assertJsonPath('payment_status', PaymentStatus::Expired->value)
        ->assertJsonPath('order_status', OrderStatus::PaymentExpired->value);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Expired)
        ->and($order->fresh()->status)->toBe(OrderStatus::PaymentExpired);
});
