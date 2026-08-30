<?php

use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Outlet;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\TableQrToken;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

/**
 * @return array{user: User, tenant: Tenant, outlet: Outlet}
 */
function createQrWorkspace(): array
{
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withTrialSubscription()->create();
    $outlet = Outlet::factory()->for($tenant)->create();
    $tenant->users()->attach($user, ['status' => 'active', 'is_owner' => false, 'joined_at' => now()]);

    $registrar = app(PermissionRegistrar::class);
    $registrar->forgetCachedPermissions();
    $registrar->setPermissionsTeamId($tenant->id);
    $role = Role::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'qr-manager-'.$tenant->id,
        'guard_name' => 'web',
    ]);
    $role->givePermissionTo(Permission::query()->firstOrCreate([
        'name' => 'table.manage',
        'guard_name' => 'web',
    ]));
    $user->assignRole($role);
    $registrar->setPermissionsTeamId(null);

    return compact('user', 'tenant', 'outlet');
}

function createQrTable(array $workspace): DiningTable
{
    return DiningTable::factory()->for($workspace['outlet'])->create();
}

test('creating a table issues a hashed QR artifact and resolves its public menu', function () {
    Storage::fake('public');
    $workspace = createQrWorkspace();
    $category = Category::factory()->for($workspace['outlet'])->create(['name' => 'Makanan Utama']);
    $product = Product::factory()->for($category)->create(['name' => 'Nasi Bakar']);
    $session = [
        'active_tenant_id' => $workspace['tenant']->id,
        'active_outlet_id' => $workspace['outlet']->id,
    ];

    $this->actingAs($workspace['user'])->withSession($session)
        ->post(route('tables.store'), [
            'name' => 'Meja QR',
            'code' => 'TBL-QR1',
            'zone' => 'Indoor',
            'capacity' => 4,
            'is_active' => true,
        ])
        ->assertRedirect(route('tables'));

    $table = DiningTable::query()->firstOrFail();
    $token = $table->activeQrToken;

    expect($token)->not->toBeNull()
        ->and($token->qr_path)->not->toBeNull()
        ->and($token->toArray())->not->toHaveKey('token_hash')
        ->and($token->toArray())->not->toHaveKey('qr_path');
    Storage::disk('public')->assertExists($token->qr_path);

    expect($token->token_hash)->toMatch('/\A[a-f0-9]{64}\z/')
        ->and($token->token_hash)->not->toBe(str_repeat('a', 64));

    $this->actingAs($workspace['user'])->withSession($session)
        ->get(route('tables'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tables.0.qr_url', Storage::disk('public')->url($token->qr_path))
            ->where('tables.0.qr_download_url', route('tables.qr.download', $table))
            ->where('tables.0.qr_print_url', route('tables.qr.print', $table)),
        );

    $plainToken = str_repeat('a', 64);
    $publicToken = TableQrToken::factory()->for($table, 'table')->create([
        'token_hash' => hash('sha256', $plainToken),
    ]);

    $this->get(route('public.qr', ['qrToken' => $plainToken]))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertInertia(fn (Assert $page) => $page
            ->component('customer/menu')
            ->where('access.valid', true)
            ->where('outlet.name', $workspace['outlet']->name)
            ->where('table.name', $table->name)
            ->where('products.0.id', $product->id)
            ->where('products.0.name', 'Nasi Bakar'),
        );

    expect($publicToken->fresh()->last_used_at)->not->toBeNull();
});

test('QR regeneration revokes the old token and replaces its artifact', function () {
    Storage::fake('public');
    $workspace = createQrWorkspace();
    $table = createQrTable($workspace);
    $session = [
        'active_tenant_id' => $workspace['tenant']->id,
        'active_outlet_id' => $workspace['outlet']->id,
    ];

    $this->actingAs($workspace['user'])->withSession($session)
        ->post(route('tables.qr.regenerate', $table))
        ->assertRedirect();

    $oldToken = $table->fresh()->activeQrToken;
    $oldPath = $oldToken->qr_path;

    $this->actingAs($workspace['user'])->withSession($session)
        ->post(route('tables.qr.regenerate', $table))
        ->assertRedirect();

    $newToken = $table->fresh()->activeQrToken;
    $newPath = $newToken->qr_path;

    expect($newToken->id)->not->toBe($oldToken->id)
        ->and($newToken->token_hash)->not->toBe($oldToken->token_hash)
        ->and($oldToken->fresh()->revoked_at)->not->toBeNull()
        ->and($newToken->token_hash)->toMatch('/\A[a-f0-9]{64}\z/');
    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($newPath);
});

test('QR revocation removes the public menu and stored artifact', function () {
    Storage::fake('public');
    $workspace = createQrWorkspace();
    $table = createQrTable($workspace);
    $session = [
        'active_tenant_id' => $workspace['tenant']->id,
        'active_outlet_id' => $workspace['outlet']->id,
    ];

    $this->actingAs($workspace['user'])->withSession($session)
        ->post(route('tables.qr.regenerate', $table));

    $issuedToken = $table->fresh()->activeQrToken;
    $issuedPath = $issuedToken->qr_path;
    $plainToken = str_repeat('c', 64);
    $knownPath = "tenants/{$workspace['tenant']->id}/outlets/{$workspace['outlet']->id}/tables/{$table->id}/known.svg";
    Storage::disk('public')->put($knownPath, '<svg />');
    $token = TableQrToken::factory()->for($table, 'table')->create([
        'token_hash' => hash('sha256', $plainToken),
        'qr_path' => $knownPath,
    ]);

    $this->actingAs($workspace['user'])->withSession($session)
        ->post(route('tables.qr.revoke', $table))
        ->assertRedirect();

    expect($table->fresh()->activeQrToken)->toBeNull()
        ->and($token->fresh()->revoked_at)->not->toBeNull();
    Storage::disk('public')->assertMissing($issuedPath);
    Storage::disk('public')->assertMissing($knownPath);

    $this->get(route('public.qr', ['qrToken' => $plainToken]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('customer/menu')
            ->where('access.valid', false),
        );
});

test('public QR access rejects expired and inactive resources', function () {
    $workspace = createQrWorkspace();
    $table = createQrTable($workspace);
    $plainToken = str_repeat('a', 64);
    $token = TableQrToken::factory()->for($table, 'table')->create([
        'token_hash' => hash('sha256', $plainToken),
    ]);

    $getMenu = fn () => $this->get(route('public.qr', ['qrToken' => $plainToken]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('customer/menu')
            ->where('access.valid', false),
        );

    $token->update(['expires_at' => now()->subSecond()]);
    $getMenu();

    $token->update(['expires_at' => null]);
    $table->update(['is_active' => false]);
    $getMenu();

    $table->update(['is_active' => true]);
    $workspace['outlet']->update(['accepts_orders' => false]);
    $getMenu();

    $workspace['outlet']->update(['accepts_orders' => true]);
    $workspace['tenant']->update(['status' => 'suspended']);
    $getMenu();
});

test('staff can download and print the active QR within the outlet scope', function () {
    Storage::fake('public');
    $workspace = createQrWorkspace();
    $table = createQrTable($workspace);
    $session = [
        'active_tenant_id' => $workspace['tenant']->id,
        'active_outlet_id' => $workspace['outlet']->id,
    ];

    $this->actingAs($workspace['user'])->withSession($session)
        ->post(route('tables.qr.regenerate', $table));

    $this->actingAs($workspace['user'])->withSession($session)
        ->get(route('tables.qr.download', $table))
        ->assertDownload('qr-'.$table->code.'.svg');

    $this->actingAs($workspace['user'])->withSession($session)
        ->get(route('tables.qr.print', $table))
        ->assertOk()
        ->assertSee($table->name)
        ->assertSee('<svg', false);
});

test('public QR menu only exposes active products from its outlet', function () {
    $workspace = createQrWorkspace();
    $otherOutlet = Outlet::factory()->for($workspace['tenant'])->create();
    $table = createQrTable($workspace);
    $category = Category::factory()->for($workspace['outlet'])->create();
    $visibleProduct = Product::factory()->for($category)->create(['name' => 'Produk Outlet Aktif']);
    Product::factory()->for(Category::factory()->for($workspace['outlet']))->unavailable()->create(['name' => 'Produk Habis']);
    Product::factory()->for(Category::factory()->for($otherOutlet))->create(['name' => 'Produk Outlet Lain']);
    $plainToken = str_repeat('b', 64);
    TableQrToken::factory()->for($table, 'table')->create(['token_hash' => hash('sha256', $plainToken)]);

    $this->get(route('public.qr', ['qrToken' => $plainToken]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('customer/menu')
            ->has('products', 1)
            ->where('products.0.id', $visibleProduct->id)
            ->where('products.0.name', 'Produk Outlet Aktif'),
        );
});
