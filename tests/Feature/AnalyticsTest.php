<?php

use App\Models\AnalyticsEvent;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\TableQrToken;
use App\Models\Tenant;
use App\Services\AnalyticsEventService;

test('public analytics records an accepted event with hashed identifiers', function () {
    $tenant = Tenant::factory()->withTrialSubscription()->create();
    $outlet = Outlet::factory()->for($tenant)->create();
    $table = DiningTable::factory()->for($outlet)->create();
    $product = Product::factory()->for(Category::factory()->for($outlet))->create();
    $plainToken = str_repeat('a', 64);
    TableQrToken::factory()->for($table, 'table')->create([
        'token_hash' => hash('sha256', $plainToken),
    ]);

    $this->postJson(route('analytics.events.store'), [
        'event' => 'add_to_cart',
        'qr_token' => $plainToken,
        'session_id' => 'browser-session-1',
        'product_id' => $product->id,
    ])
        ->assertAccepted()
        ->assertJson(['accepted' => true]);

    $event = AnalyticsEvent::query()->firstOrFail();

    expect($event->tenant_id)->toBe($tenant->id)
        ->and($event->outlet_id)->toBe($outlet->id)
        ->and($event->product_id)->toBe($product->id)
        ->and($event->event)->toBe('add_to_cart')
        ->and($event->session_hash)->toBe(hash('sha256', 'browser-session-1'))
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
    TableQrToken::factory()->for($table, 'table')->create([
        'token_hash' => hash('sha256', $plainToken),
    ]);

    $this->postJson(route('analytics.events.store'), [
        'event' => 'product_viewed',
        'qr_token' => $plainToken,
        'session_id' => 'browser-session-2',
        'product_id' => $foreignProduct->id,
    ])->assertUnprocessable();

    expect(AnalyticsEvent::query()->count())->toBe(0);
});
