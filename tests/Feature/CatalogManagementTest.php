<?php

use App\Models\Category;
use App\Models\Modifier;
use App\Models\ModifierOption;
use App\Models\Outlet;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

/** @return array{user: User, tenant: Tenant, outlet: Outlet} */
function createCatalogManagementWorkspace(): array
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
        'name' => 'catalog-manager-'.$tenant->id,
        'guard_name' => 'web',
    ]);
    $role->givePermissionTo(Permission::query()->firstOrCreate([
        'name' => 'menu.manage',
        'guard_name' => 'web',
    ]));
    $user->assignRole($role);
    $registrar->setPermissionsTeamId(null);

    return compact('user', 'tenant', 'outlet');
}

/** @param array{tenant: Tenant, outlet: Outlet} $workspace */
function catalogManagementSession(array $workspace): array
{
    return [
        'active_tenant_id' => $workspace['tenant']->id,
        'active_outlet_id' => $workspace['outlet']->id,
    ];
}

test('catalog manager can create update and remove a category in the active outlet', function () {
    $workspace = createCatalogManagementWorkspace();
    $session = catalogManagementSession($workspace);

    $this->actingAs($workspace['user'])->withSession($session)
        ->post(route('categories.store'), [
            'name' => 'Minuman Dingin',
            'description' => 'Minuman segar.',
            'is_active' => true,
            'tenant_id' => Tenant::factory()->create()->id,
        ])
        ->assertRedirect(route('products'));

    $category = Category::query()->where('name', 'Minuman Dingin')->firstOrFail();

    expect($category->tenant_id)->toBe($workspace['tenant']->id)
        ->and($category->outlet_id)->toBe($workspace['outlet']->id)
        ->and($category->slug)->toBe('minuman-dingin');

    $this->actingAs($workspace['user'])->withSession($session)
        ->patch(route('categories.update', $category), [
            'name' => 'Minuman Segar',
            'description' => null,
            'is_active' => false,
        ])
        ->assertRedirect(route('products'));

    expect($category->fresh()->slug)->toBe('minuman-segar')
        ->and($category->fresh()->is_active)->toBeFalse();

    $this->actingAs($workspace['user'])->withSession($session)
        ->delete(route('categories.destroy', $category))
        ->assertRedirect(route('products'));

    $this->assertDatabaseMissing('categories', ['id' => $category->id]);
});

test('categories in use cannot be deleted and catalog records from another outlet are not routable', function () {
    $workspace = createCatalogManagementWorkspace();
    $otherOutlet = Outlet::factory()->for($workspace['tenant'])->create();
    $category = Category::factory()->for($workspace['outlet'])->create();
    Product::factory()->for($category)->create();
    $foreignCategory = Category::factory()->for($otherOutlet)->create();
    $session = catalogManagementSession($workspace);

    $this->actingAs($workspace['user'])->withSession($session)
        ->from(route('products'))
        ->delete(route('categories.destroy', $category))
        ->assertRedirect(route('products'))
        ->assertSessionHasErrors('category');

    $this->actingAs($workspace['user'])->withSession($session)
        ->patch(route('categories.update', $foreignCategory), [
            'name' => 'Tidak boleh',
            'description' => null,
            'is_active' => true,
        ])
        ->assertNotFound();
});

test('catalog manager can manage variants modifiers options and product assignments', function () {
    $workspace = createCatalogManagementWorkspace();
    $category = Category::factory()->for($workspace['outlet'])->create();
    $product = Product::factory()->for($category)->create();
    $session = catalogManagementSession($workspace);

    $this->actingAs($workspace['user'])->withSession($session)
        ->post(route('products.variants.store', $product), [
            'name' => 'Regular',
            'price_delta' => 0,
            'is_default' => true,
            'is_active' => true,
        ])
        ->assertRedirect(route('products'));

    $defaultVariant = ProductVariant::query()->where('name', 'Regular')->firstOrFail();

    $this->actingAs($workspace['user'])->withSession($session)
        ->post(route('products.variants.store', $product), [
            'name' => 'Large',
            'price_delta' => 5000,
            'is_default' => true,
            'is_active' => true,
        ])
        ->assertRedirect(route('products'));

    $largeVariant = ProductVariant::query()->where('name', 'Large')->firstOrFail();

    expect($defaultVariant->fresh()->is_default)->toBeFalse()
        ->and($largeVariant->is_default)->toBeTrue();

    $this->actingAs($workspace['user'])->withSession($session)
        ->patch(route('product-variants.update', $largeVariant), [
            'name' => 'Jumbo',
            'price_delta' => 7000,
            'is_default' => true,
            'is_active' => false,
        ])
        ->assertRedirect(route('products'));

    $this->assertDatabaseHas('product_variants', [
        'id' => $largeVariant->id,
        'name' => 'Jumbo',
        'price_delta' => 7000,
        'is_active' => false,
    ]);

    $this->actingAs($workspace['user'])->withSession($session)
        ->post(route('modifiers.store'), [
            'name' => 'Pilihan Topping',
            'selection_type' => 'multiple',
            'minimum_selections' => 1,
            'maximum_selections' => 2,
            'is_required' => true,
            'is_active' => true,
        ])
        ->assertRedirect(route('products'));

    $modifier = Modifier::query()->where('name', 'Pilihan Topping')->firstOrFail();

    $this->actingAs($workspace['user'])->withSession($session)
        ->patch(route('modifiers.update', $modifier), [
            'name' => 'Pilihan Tambahan',
            'selection_type' => 'multiple',
            'minimum_selections' => 0,
            'maximum_selections' => 3,
            'is_required' => false,
            'is_active' => true,
        ])
        ->assertRedirect(route('products'));

    $this->actingAs($workspace['user'])->withSession($session)
        ->post(route('modifiers.options.store', $modifier), [
            'name' => 'Keju',
            'price_delta' => 3000,
            'is_active' => true,
        ])
        ->assertRedirect(route('products'));

    $option = ModifierOption::query()->where('name', 'Keju')->firstOrFail();

    $this->actingAs($workspace['user'])->withSession($session)
        ->patch(route('modifier-options.update', $option), [
            'name' => 'Keju Extra',
            'price_delta' => 4000,
            'is_active' => false,
        ])
        ->assertRedirect(route('products'));

    $this->actingAs($workspace['user'])->withSession($session)
        ->put(route('products.modifiers.update', $product), [
            'modifier_ids' => [$modifier->id],
        ])
        ->assertRedirect(route('products'));

    $this->assertDatabaseHas('product_modifier', [
        'product_id' => $product->id,
        'modifier_id' => $modifier->id,
        'tenant_id' => $workspace['tenant']->id,
        'outlet_id' => $workspace['outlet']->id,
        'position' => 0,
    ]);

    $this->actingAs($workspace['user'])->withSession($session)
        ->delete(route('modifier-options.destroy', $option))
        ->assertRedirect(route('products'));

    $this->assertDatabaseMissing('modifier_options', ['id' => $option->id]);

    $this->actingAs($workspace['user'])->withSession($session)
        ->delete(route('product-variants.destroy', $largeVariant))
        ->assertRedirect(route('products'));

    $this->assertDatabaseMissing('product_variants', ['id' => $largeVariant->id]);

    $this->actingAs($workspace['user'])->withSession($session)
        ->delete(route('modifiers.destroy', $modifier))
        ->assertRedirect(route('products'));

    $this->assertDatabaseMissing('modifiers', ['id' => $modifier->id]);
    $this->assertDatabaseMissing('product_modifier', [
        'product_id' => $product->id,
        'modifier_id' => $modifier->id,
    ]);
});

test('modifier validation and product assignments are limited to the active outlet', function () {
    $workspace = createCatalogManagementWorkspace();
    $otherOutlet = Outlet::factory()->for($workspace['tenant'])->create();
    $product = Product::factory()->for(Category::factory()->for($workspace['outlet']))->create();
    $foreignModifier = Modifier::factory()->for($otherOutlet)->create();
    $session = catalogManagementSession($workspace);

    $this->actingAs($workspace['user'])->withSession($session)
        ->from(route('products'))
        ->post(route('modifiers.store'), [
            'name' => 'Pilihan Tunggal Salah',
            'selection_type' => 'single',
            'minimum_selections' => 0,
            'maximum_selections' => 2,
            'is_required' => false,
            'is_active' => true,
        ])
        ->assertRedirect(route('products'))
        ->assertSessionHasErrors('maximum_selections');

    $this->actingAs($workspace['user'])->withSession($session)
        ->from(route('products'))
        ->put(route('products.modifiers.update', $product), [
            'modifier_ids' => [$foreignModifier->id],
        ])
        ->assertRedirect(route('products'))
        ->assertSessionHasErrors('modifier_ids.0');

    $this->actingAs($workspace['user'])->withSession($session)
        ->get(route('products'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('products')
            ->has('categories.0.products_count')
            ->has('products.0.variants')
            ->has('products.0.modifier_ids')
            ->has('modifiers', 0),
        );
});
