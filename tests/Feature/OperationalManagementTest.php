<?php

use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Outlet;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

/**
 * @param  list<string>  $permissions
 * @return array{user: User, tenant: Tenant, outlet: Outlet}
 */
function createOperationalWorkspace(array $permissions): array
{
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withTrialSubscription()->create();
    $outlet = Outlet::factory()->for($tenant)->create();
    $tenant->users()->attach($user, ['status' => 'active', 'is_owner' => false, 'joined_at' => now()]);

    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($tenant->id);
    $role = Role::query()->create(['tenant_id' => $tenant->id, 'name' => 'operations-'.$tenant->id, 'guard_name' => 'web']);
    $role->syncPermissions(collect($permissions)->map(fn (string $name) => Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web'])));
    $user->assignRole($role);
    $registrar->setPermissionsTeamId(null);

    return compact('user', 'tenant', 'outlet');
}

test('products and tables pages only expose the active outlet records', function () {
    $workspace = createOperationalWorkspace(['menu.manage', 'table.manage']);
    $otherOutlet = Outlet::factory()->for($workspace['tenant'])->create();
    $activeCategory = Category::factory()->for($workspace['outlet'])->create(['name' => 'Aktif']);
    $activeProduct = Product::factory()->for($activeCategory)->create(['name' => 'Produk Aktif']);
    Product::factory()->for(Category::factory()->for($otherOutlet))->create(['name' => 'Produk Outlet Lain']);
    $activeTable = DiningTable::factory()->for($workspace['outlet'])->create(['name' => 'Meja Aktif']);
    DiningTable::factory()->for($otherOutlet)->create(['name' => 'Meja Outlet Lain']);

    $session = ['active_tenant_id' => $workspace['tenant']->id, 'active_outlet_id' => $workspace['outlet']->id];

    $this->actingAs($workspace['user'])->withSession($session)->get(route('products'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('products')
            ->has('products', 1)
            ->where('products.0.id', $activeProduct->id)
            ->where('products.0.name', 'Produk Aktif'),
        );

    $this->actingAs($workspace['user'])->withSession($session)->get(route('tables'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tables')
            ->has('tables', 1)
            ->where('tables.0.id', $activeTable->id)
            ->missing('tables.0.token_hash'),
        );
});

test('product creation derives ownership from the active context', function () {
    $workspace = createOperationalWorkspace(['menu.manage']);
    $foreignTenant = Tenant::factory()->create();
    $foreignOutlet = Outlet::factory()->for($foreignTenant)->create();
    $category = Category::factory()->for($workspace['outlet'])->create();

    $response = $this->actingAs($workspace['user'])->withSession([
        'active_tenant_id' => $workspace['tenant']->id,
        'active_outlet_id' => $workspace['outlet']->id,
    ])->post(route('products.store'), [
        'name' => 'Soto Betawi',
        'category_id' => $category->id,
        'base_price' => 32000,
        'is_active' => true,
        'is_available' => true,
        'is_featured' => false,
        'tenant_id' => $foreignTenant->id,
        'outlet_id' => $foreignOutlet->id,
    ]);

    $response->assertRedirect(route('products'));
    $this->assertDatabaseHas('products', [
        'name' => 'Soto Betawi',
        'tenant_id' => $workspace['tenant']->id,
        'outlet_id' => $workspace['outlet']->id,
        'category_id' => $category->id,
        'slug' => 'soto-betawi',
    ]);
});

test('product creation rejects categories outside the active outlet', function () {
    $workspace = createOperationalWorkspace(['menu.manage']);
    $otherOutlet = Outlet::factory()->for($workspace['tenant'])->create();
    $foreignCategory = Category::factory()->for($otherOutlet)->create();

    $response = $this->actingAs($workspace['user'])->withSession([
        'active_tenant_id' => $workspace['tenant']->id,
        'active_outlet_id' => $workspace['outlet']->id,
    ])->from(route('products'))->post(route('products.store'), [
        'name' => 'Produk Salah Outlet',
        'category_id' => $foreignCategory->id,
        'base_price' => 10000,
        'is_active' => true,
        'is_available' => true,
        'is_featured' => false,
    ]);

    $response->assertRedirect(route('products'))->assertSessionHasErrors('category_id');
    expect(Product::query()->count())->toBe(0);
});

test('availability updates only the scoped product', function () {
    $workspace = createOperationalWorkspace(['menu.manage']);
    $product = Product::factory()->for(Category::factory()->for($workspace['outlet']))->create(['is_available' => true]);
    $otherOutlet = Outlet::factory()->for($workspace['tenant'])->create();
    $foreignProduct = Product::factory()->for(Category::factory()->for($otherOutlet))->create();
    $session = ['active_tenant_id' => $workspace['tenant']->id, 'active_outlet_id' => $workspace['outlet']->id];

    $this->actingAs($workspace['user'])->withSession($session)
        ->patch(route('products.availability.update', $product), ['is_available' => false])
        ->assertRedirect();

    expect($product->fresh()->is_available)->toBeFalse();

    $this->actingAs($workspace['user'])->withSession($session)
        ->patch(route('products.availability.update', $foreignProduct), ['is_available' => false])
        ->assertNotFound();
});

test('table creation validates its code within the active outlet and derives ownership', function () {
    $workspace = createOperationalWorkspace(['table.manage']);
    DiningTable::factory()->for($workspace['outlet'])->create(['code' => 'TBL-001']);
    $session = ['active_tenant_id' => $workspace['tenant']->id, 'active_outlet_id' => $workspace['outlet']->id];

    $this->actingAs($workspace['user'])->withSession($session)
        ->post(route('tables.store'), ['name' => 'Meja 02', 'code' => 'TBL-002', 'zone' => 'Teras', 'capacity' => 4, 'is_active' => true])
        ->assertRedirect(route('tables'));

    $this->assertDatabaseHas('tables', ['name' => 'Meja 02', 'code' => 'TBL-002', 'tenant_id' => $workspace['tenant']->id, 'outlet_id' => $workspace['outlet']->id]);

    $this->actingAs($workspace['user'])->withSession($session)->from(route('tables'))
        ->post(route('tables.store'), ['name' => 'Duplikat', 'code' => 'TBL-001', 'capacity' => 2, 'is_active' => true])
        ->assertRedirect(route('tables'))
        ->assertSessionHasErrors('code');
});

test('product image uploads are optimized and stored inside the active tenant outlet directory', function () {
    Storage::fake('public');
    $workspace = createOperationalWorkspace(['menu.manage']);
    $session = ['active_tenant_id' => $workspace['tenant']->id, 'active_outlet_id' => $workspace['outlet']->id];

    $this->actingAs($workspace['user'])->withSession($session)->post(route('products.store'), [
        'name' => 'Es Cendol',
        'base_price' => 18000,
        'image' => UploadedFile::fake()->image('es-cendol.png', 2000, 1200),
        'is_active' => true,
        'is_available' => true,
        'is_featured' => false,
    ])->assertRedirect(route('products'));

    $product = Product::query()->firstOrFail();
    $directory = "tenants/{$workspace['tenant']->id}/outlets/{$workspace['outlet']->id}/products/";

    expect($product->image_path)->not->toBeNull()
        ->and(str_starts_with($product->image_path, $directory))->toBeTrue()
        ->and(str_ends_with($product->image_path, '.webp'))->toBeTrue();
    Storage::disk('public')->assertExists($product->image_path);

    $this->actingAs($workspace['user'])->withSession($session)->get(route('products'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('products.0.image_url', Storage::disk('public')->url($product->image_path))
            ->missing('products.0.image_path'),
        );
});

test('product image upload rejects non-image files without storing a file', function () {
    Storage::fake('public');
    $workspace = createOperationalWorkspace(['menu.manage']);

    $response = $this->actingAs($workspace['user'])->withSession([
        'active_tenant_id' => $workspace['tenant']->id,
        'active_outlet_id' => $workspace['outlet']->id,
    ])->from(route('products'))->post(route('products.store'), [
        'name' => 'Produk Tidak Valid',
        'base_price' => 10000,
        'image' => UploadedFile::fake()->create('catatan.txt', 4, 'text/plain'),
        'is_active' => true,
        'is_available' => true,
        'is_featured' => false,
    ]);

    $response->assertRedirect(route('products'))->assertSessionHasErrors('image');
    expect(Product::query()->count())->toBe(0);
    Storage::disk('public')->assertMissing(
        "tenants/{$workspace['tenant']->id}/outlets/{$workspace['outlet']->id}/products",
    );
});

test('catalog manager can update product details and replace its image', function () {
    Storage::fake('public');
    $workspace = createOperationalWorkspace(['menu.manage']);
    $category = Category::factory()->for($workspace['outlet'])->create(['name' => 'Makanan']);
    $product = Product::factory()->for($category)->create([
        'name' => 'Nasi Lama',
        'image_path' => "tenants/{$workspace['tenant']->id}/outlets/{$workspace['outlet']->id}/products/old.webp",
    ]);
    Storage::disk('public')->put($product->image_path, 'old image');
    $session = ['active_tenant_id' => $workspace['tenant']->id, 'active_outlet_id' => $workspace['outlet']->id];

    $this->actingAs($workspace['user'])->withSession($session)
        ->post(route('products.update', $product), [
            '_method' => 'patch',
            'name' => 'Nasi Baru',
            'category_id' => $category->id,
            'description' => 'Dengan sambal segar.',
            'base_price' => 35000,
            'image' => UploadedFile::fake()->image('nasi-baru.png', 1200, 800),
            'is_active' => true,
            'is_available' => false,
            'is_featured' => true,
        ])
        ->assertRedirect(route('products'));

    $updatedProduct = $product->fresh();

    expect($updatedProduct->name)->toBe('Nasi Baru')
        ->and($updatedProduct->slug)->toBe('nasi-baru')
        ->and($updatedProduct->base_price)->toBe(35000)
        ->and($updatedProduct->is_available)->toBeFalse()
        ->and($updatedProduct->is_featured)->toBeTrue()
        ->and($updatedProduct->image_path)->not->toBe($product->getRawOriginal('image_path'));
    Storage::disk('public')->assertMissing($product->getRawOriginal('image_path'));
    Storage::disk('public')->assertExists($updatedProduct->image_path);
});

test('product deletion is scoped and preserves products referenced by orders', function () {
    $workspace = createOperationalWorkspace(['menu.manage']);
    $category = Category::factory()->for($workspace['outlet'])->create();
    $unusedProduct = Product::factory()->for($category)->create();
    $orderedProduct = Product::factory()->for($category)->create();
    $table = DiningTable::factory()->for($workspace['outlet'])->create();
    $order = Order::factory()->for($table, 'table')->create();
    OrderItem::factory()->for($order)->create(['product_id' => $orderedProduct->id]);
    $session = ['active_tenant_id' => $workspace['tenant']->id, 'active_outlet_id' => $workspace['outlet']->id];

    $this->actingAs($workspace['user'])->withSession($session)
        ->delete(route('products.destroy', $unusedProduct))
        ->assertRedirect(route('products'));
    $this->assertDatabaseMissing('products', ['id' => $unusedProduct->id]);

    $this->actingAs($workspace['user'])->withSession($session)
        ->from(route('products'))
        ->delete(route('products.destroy', $orderedProduct))
        ->assertRedirect(route('products'))
        ->assertSessionHasErrors('product');
    $this->assertDatabaseHas('products', ['id' => $orderedProduct->id]);
});

test('table manager can update table details and status without crossing outlet scope', function () {
    $workspace = createOperationalWorkspace(['table.manage']);
    $otherOutlet = Outlet::factory()->for($workspace['tenant'])->create();
    $table = DiningTable::factory()->for($workspace['outlet'])->create(['code' => 'TBL-001']);
    $otherTable = DiningTable::factory()->for($otherOutlet)->create();
    $session = ['active_tenant_id' => $workspace['tenant']->id, 'active_outlet_id' => $workspace['outlet']->id];

    $this->actingAs($workspace['user'])->withSession($session)
        ->patch(route('tables.update', $table), [
            'name' => 'Meja VIP',
            'code' => 'TBL-001',
            'zone' => 'Teras',
            'capacity' => 8,
            'is_active' => false,
        ])
        ->assertRedirect(route('tables'));

    expect($table->fresh()->name)->toBe('Meja VIP')
        ->and($table->fresh()->zone)->toBe('Teras')
        ->and($table->fresh()->capacity)->toBe(8)
        ->and($table->fresh()->is_active)->toBeFalse();

    $this->actingAs($workspace['user'])->withSession($session)
        ->patch(route('tables.update', $otherTable), [
            'name' => 'Tidak boleh',
            'code' => $otherTable->code,
            'zone' => $otherTable->zone,
            'capacity' => $otherTable->capacity,
            'is_active' => false,
        ])
        ->assertNotFound();
});
