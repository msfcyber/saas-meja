<?php

use App\Models\AnalyticsEvent;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\TableQrToken;
use App\Models\Tenant;
use App\Services\AnalyticsEventService;
use App\Services\PublicAnalyticsSessionService;
use App\Support\PublicTableAccess;

function analyticsTokenFor(string $plainToken, TableQrToken $qrToken, Tenant $tenant, Outlet $outlet, DiningTable $table): string
{
    return app(PublicAnalyticsSessionService::class)->issue(
        new PublicTableAccess($plainToken, $qrToken, $tenant, $outlet, $table),
    )['token'];
}

test('public analytics records an accepted event with hashed identifiers', function () {
    $tenant = Tenant::factory()->withTrialSubscription()->create();
    $outlet = Outlet::factory()->for($tenant)->create();
    $table = DiningTable::factory()->for($outlet)->create();
    $product = Product::factory()->for(Category::factory()->for($outlet))->create();
    $plainToken = str_repeat('a', 64);
    $tableQrToken = TableQrToken::factory()->for($table, 'table')->create([
        'token_hash' => hash('sha256', $plainToken),
    ]);

    $this->postJson(route('analytics.events.store'), [
        'event' => 'add_to_cart',
        'qr_token' => $plainToken,
        'analytics_token' => analyticsTokenFor($plainToken, $tableQrToken, $tenant, $outlet, $table),
        'product_id' => $product->id,
    ])
        ->assertAccepted()
        ->assertJson(['accepted' => true]);

    $event = AnalyticsEvent::query()->firstOrFail();

    expect($event->tenant_id)->toBe($tenant->id)
        ->and($event->outlet_id)->toBe($outlet->id)
        ->and($event->product_id)->toBe($product->id)
        ->and($event->event)->toBe('add_to_cart')
        ->and($event->session_hash)->not->toBeNull()
        ->and($event->qr_token_hash)->toBe(hash('sha256', $plainToken));
});

test('analytics service keeps only allowlisted scalar properties', function () {
    $tenant = Tenant::factory()->create();
    $outlet = Outlet::factory()->for($tenant)->create();

    app(AnalyticsEventService::class)->record('menu_viewed', $tenant->id, $outlet->id, [
        'properties' => [
            'source' => 'customer_web',
            'status' => 'visible',
            'secret' => 'must-not-persist',
            'nested' => ['value' => 'must-not-persist'],
        ],
    ]);

    expect(AnalyticsEvent::query()->firstOrFail()->properties)->toBe([
        'source' => 'customer_web',
        'status' => 'visible',
    ]);
});

test('public analytics rejects a foreign product for the QR outlet', function () {
    $tenant = Tenant::factory()->withTrialSubscription()->create();
    $outlet = Outlet::factory()->for($tenant)->create();
    $table = DiningTable::factory()->for($outlet)->create();
    $foreignOutlet = Outlet::factory()->for($tenant)->create();
    $foreignProduct = Product::factory()->for(Category::factory()->for($foreignOutlet))->create();
    $plainToken = str_repeat('b', 64);
    $tableQrToken = TableQrToken::factory()->for($table, 'table')->create([
        'token_hash' => hash('sha256', $plainToken),
    ]);

    $this->postJson(route('analytics.events.store'), [
        'event' => 'product_viewed',
        'qr_token' => $plainToken,
        'analytics_token' => analyticsTokenFor($plainToken, $tableQrToken, $tenant, $outlet, $table),
        'product_id' => $foreignProduct->id,
    ])->assertUnprocessable();

    expect(AnalyticsEvent::query()->count())->toBe(0);
});

test('public analytics rejects server-only lifecycle events', function () {
    $this->postJson(route('analytics.events.store'), [
        'event' => 'payment_paid',
        'qr_token' => str_repeat('c', 64),
        'session_id' => 'browser-session-3',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('event');

    expect(AnalyticsEvent::query()->count())->toBe(0);
});

test('public analytics rejects a token signed for another QR', function () {
    $tenant = Tenant::factory()->withTrialSubscription()->create();
    $outlet = Outlet::factory()->for($tenant)->create();
    $table = DiningTable::factory()->for($outlet)->create();
    $otherTable = DiningTable::factory()->for($outlet)->create();
    $plainToken = str_repeat('d', 64);
    $otherPlainToken = str_repeat('e', 64);
    $tableQrToken = TableQrToken::factory()->for($table, 'table')->create(['token_hash' => hash('sha256', $plainToken)]);
    TableQrToken::factory()->for($otherTable, 'table')->create(['token_hash' => hash('sha256', $otherPlainToken)]);

    $this->postJson(route('analytics.events.store'), [
        'event' => 'checkout_started',
        'qr_token' => $otherPlainToken,
        'analytics_token' => analyticsTokenFor($plainToken, $tableQrToken, $tenant, $outlet, $table),
    ])->assertUnprocessable()->assertJsonValidationErrors('analytics_token');
});

test('public analytics rejects an expired token', function () {
    $tenant = Tenant::factory()->withTrialSubscription()->create();
    $outlet = Outlet::factory()->for($tenant)->create();
    $table = DiningTable::factory()->for($outlet)->create();
    $plainToken = str_repeat('e', 64);
    $tableQrToken = TableQrToken::factory()->for($table, 'table')->create(['token_hash' => hash('sha256', $plainToken)]);
    $analyticsToken = analyticsTokenFor($plainToken, $tableQrToken, $tenant, $outlet, $table);

    $this->travel(61)->minutes();

    $this->postJson(route('analytics.events.store'), [
        'event' => 'checkout_started',
        'qr_token' => $plainToken,
        'analytics_token' => $analyticsToken,
    ])->assertUnprocessable()->assertJsonValidationErrors('analytics_token');

    $this->travelBack();
});

test('public analytics deduplicates identical browser events for one minute', function () {
    $tenant = Tenant::factory()->withTrialSubscription()->create();
    $outlet = Outlet::factory()->for($tenant)->create();
    $table = DiningTable::factory()->for($outlet)->create();
    $plainToken = str_repeat('f', 64);
    $tableQrToken = TableQrToken::factory()->for($table, 'table')->create(['token_hash' => hash('sha256', $plainToken)]);
    $payload = [
        'event' => 'checkout_started',
        'qr_token' => $plainToken,
        'analytics_token' => analyticsTokenFor($plainToken, $tableQrToken, $tenant, $outlet, $table),
    ];

    $this->postJson(route('analytics.events.store'), $payload)->assertAccepted();
    $this->postJson(route('analytics.events.store'), $payload)->assertAccepted();

    expect(AnalyticsEvent::query()->where('event', 'checkout_started')->count())->toBe(1);
});
