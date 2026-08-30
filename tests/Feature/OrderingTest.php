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
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\TableQrToken;
use App\Models\TaxSetting;
use App\Models\Tenant;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{tenant: Tenant, outlet: Outlet, product: Product, variant: ProductVariant, option: ModifierOption, token: string}
 */
function createOrderingWorkspace(): array
{
    $tenant = Tenant::factory()->create();
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
    config(['payments.midtrans.server_key' => 'SB-Mid-server-key']);
    Http::fake([
        'https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
            'token' => 'snap-token-123',
            'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token-123',
        ]),
    ]);
    $workspace = createOrderingWorkspace();
    $orderResponse = $this->postJson(route('public.orders.store'), orderingPayload($workspace))->assertCreated();
    $paymentResponse = $this->postJson(route('public.orders.payment.start', [
        'accessToken' => $orderResponse->json('access_token'),
    ]));

    $paymentResponse->assertOk()
        ->assertJsonPath('provider', 'midtrans')
        ->assertJsonPath('redirect_url', 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token-123');

    $order = createOrder($orderResponse);
    $payment = Payment::withoutGlobalScopes()->where('order_id', $order->id)->firstOrFail();

    expect($payment->provider)->toBe('midtrans')
        ->and($payment->provider_reference)->toBe('meja-payment-'.$payment->id)
        ->and($payment->metadata)->toMatchArray([
            'midtrans' => [
                'snap_token' => 'snap-token-123',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token-123',
            ],
        ]);
    Http::assertSent(fn (Request $request) => $request->url() === 'https://app.sandbox.midtrans.com/snap/v1/transactions'
        && $request->data()['transaction_details'] === [
            'order_id' => 'meja-payment-'.$payment->id,
            'gross_amount' => $payment->amount,
        ]
        && $request->data()['callbacks'] === [
            'finish' => route('public.order', ['accessToken' => $orderResponse->json('access_token')]),
        ]);

    $this->postJson(route('public.orders.payment.start', [
        'accessToken' => $orderResponse->json('access_token'),
    ]))->assertOk();
    Http::assertSentCount(1);
});

test('a guest checkout keeps its pending payment when Midtrans is not configured', function () {
    config(['payments.midtrans.server_key' => null]);
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

test('a valid Midtrans notification marks payment and order as paid exactly once', function () {
    config(['payments.midtrans.server_key' => 'SB-Mid-server-key']);
    $workspace = createOrderingWorkspace();
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
        $payload['order_id'].$payload['status_code'].$grossAmount.'SB-Mid-server-key',
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

test('a Midtrans notification rejects an invalid signature without changing state', function () {
    config(['payments.midtrans.server_key' => 'SB-Mid-server-key']);
    $workspace = createOrderingWorkspace();
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
