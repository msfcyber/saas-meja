<?php

use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Modifier;
use App\Models\ModifierOption;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\TableQrToken;
use App\Models\TaxSetting;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;

test('domain factories create a tenant-consistent catalog graph', function () {
    $outlet = Outlet::factory()->create();
    $category = Category::factory()->for($outlet)->create();
    $product = Product::factory()->for($category)->create();
    $variant = ProductVariant::factory()->for($product)->create();
    $modifier = Modifier::factory()->for($outlet)->create();
    $option = ModifierOption::factory()->for($modifier)->create();
    $table = DiningTable::factory()->for($outlet)->create();
    $token = TableQrToken::factory()->for($table, 'table')->create();
    $taxSetting = TaxSetting::factory()->for($outlet)->enabled()->create();

    $product->modifiers()->attach($modifier, [
        'tenant_id' => $outlet->tenant_id,
        'outlet_id' => $outlet->id,
        'position' => 0,
    ]);

    expect($category->tenant_id)->toBe($outlet->tenant_id)
        ->and($product->tenant_id)->toBe($outlet->tenant_id)
        ->and($variant->outlet_id)->toBe($outlet->id)
        ->and($option->tenant_id)->toBe($outlet->tenant_id)
        ->and($token->tenant_id)->toBe($outlet->tenant_id)
        ->and($taxSetting->tenant_id)->toBe($outlet->tenant_id)
        ->and($taxSetting->outlet_id)->toBe($outlet->id)
        ->and($product->fresh()->modifiers)->toHaveCount(1)
        ->and($table->fresh()->activeQrToken?->is($token))->toBeTrue()
        ->and($token->toArray())->not->toHaveKey('token_hash');
});

test('database rejects cross-tenant outlet relationships', function () {
    $firstTenant = Tenant::factory()->create();
    $secondOutlet = Outlet::factory()->create();

    expect(fn () => Category::query()->create([
        'tenant_id' => $firstTenant->id,
        'outlet_id' => $secondOutlet->id,
        'name' => 'Invalid category',
        'slug' => 'invalid-category',
        'position' => 0,
        'is_active' => true,
    ]))->toThrow(QueryException::class);
});

test('database seeder is idempotent and assigns tenant owner permissions', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    $tenant = Tenant::query()->where('slug', 'kedai-sore')->firstOrFail();
    $owner = User::query()->where('email', 'owner@meja.test')->firstOrFail();

    setPermissionsTeamId($tenant->id);

    expect(Tenant::query()->where('slug', 'kedai-sore')->count())->toBe(1)
        ->and(Outlet::query()->where('tenant_id', $tenant->id)->count())->toBe(1)
        ->and(Product::query()->where('tenant_id', $tenant->id)->count())->toBe(6)
        ->and(DiningTable::query()->where('tenant_id', $tenant->id)->count())->toBe(12)
        ->and(TableQrToken::query()->where('tenant_id', $tenant->id)->count())->toBe(12)
        ->and($owner->tenants)->toHaveCount(1)
        ->and($owner->hasRole('owner'))->toBeTrue()
        ->and($owner->can('menu.manage'))->toBeTrue();
});
