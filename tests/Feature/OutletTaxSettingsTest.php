<?php

use App\Actions\Tenancy\CreateOwnerWorkspace;
use App\Models\AuditLog;
use App\Models\Outlet;
use App\Models\TaxSetting;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{owner: User, tenant: Tenant, outlet: Outlet}
 */
function createOutletTaxSettingsWorkspace(): array
{
    $owner = User::factory()->create();
    $workspace = app(CreateOwnerWorkspace::class)->handle($owner, [
        'business_name' => 'Tax Settings',
        'outlet_name' => 'Tax Settings Pusat',
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
function outletTaxSettingsSession(array $workspace): array
{
    return [
        'active_tenant_id' => $workspace['tenant']->id,
        'active_outlet_id' => $workspace['outlet']->id,
    ];
}

test('a tax manager can update and view an outlet tax setting', function () {
    $workspace = createOutletTaxSettingsWorkspace();
    $session = outletTaxSettingsSession($workspace);

    $this->actingAs($workspace['owner'])
        ->withSession($session)
        ->patch(route('outlets.tax-settings.update', $workspace['outlet']), [
            'tax_enabled' => true,
            'tax_name' => 'PPN',
            'tax_rate' => '11.25',
            'tax_inclusive' => true,
        ])
        ->assertRedirect(route('outlets'));

    $this->assertDatabaseHas('tax_settings', [
        'tenant_id' => $workspace['tenant']->id,
        'outlet_id' => $workspace['outlet']->id,
        'is_enabled' => true,
        'name' => 'PPN',
        'rate_basis_points' => 1125,
        'is_inclusive' => true,
    ]);

    $this->actingAs($workspace['owner'])
        ->withSession($session)
        ->get(route('outlets'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can_manage_tax', true)
            ->where('outlets.0.tax_settings.tax_enabled', true)
            ->where('outlets.0.tax_settings.tax_name', 'PPN')
            ->where('outlets.0.tax_settings.tax_rate', '11.25')
            ->where('outlets.0.tax_settings.tax_inclusive', true),
        );
});

test('enabled tax settings validate required fields and percent strings', function () {
    $workspace = createOutletTaxSettingsWorkspace();
    $session = outletTaxSettingsSession($workspace);

    $this->actingAs($workspace['owner'])
        ->withSession($session)
        ->from(route('outlets'))
        ->patch(route('outlets.tax-settings.update', $workspace['outlet']), [
            'tax_enabled' => true,
            'tax_name' => '',
            'tax_rate' => '',
            'tax_inclusive' => false,
        ])
        ->assertRedirect(route('outlets'))
        ->assertSessionHasErrors(['tax_name', 'tax_rate']);

    foreach (['0', '100.01', '10.123', 10] as $rate) {
        $this->actingAs($workspace['owner'])
            ->withSession($session)
            ->from(route('outlets'))
            ->patch(route('outlets.tax-settings.update', $workspace['outlet']), [
                'tax_enabled' => true,
                'tax_name' => 'PPN',
                'tax_rate' => $rate,
                'tax_inclusive' => false,
            ])
            ->assertRedirect(route('outlets'))
            ->assertSessionHasErrors('tax_rate');
    }

    $this->assertDatabaseHas('tax_settings', [
        'outlet_id' => $workspace['outlet']->id,
        'is_enabled' => false,
        'rate_basis_points' => 0,
    ]);
});

test('tax setting changes record old and new values without secrets', function () {
    $workspace = createOutletTaxSettingsWorkspace();
    $setting = TaxSetting::withoutGlobalScopes()
        ->where('tenant_id', $workspace['tenant']->id)
        ->where('outlet_id', $workspace['outlet']->id)
        ->firstOrFail();
    $setting->update([
        'is_enabled' => true,
        'name' => 'PPN lama',
        'rate_basis_points' => 1000,
        'is_inclusive' => false,
    ]);

    $this->actingAs($workspace['owner'])
        ->withSession(outletTaxSettingsSession($workspace))
        ->patch(route('outlets.tax-settings.update', $workspace['outlet']), [
            'tax_enabled' => true,
            'tax_name' => 'PPN baru',
            'tax_rate' => '10.5',
            'tax_inclusive' => true,
        ])
        ->assertRedirect(route('outlets'));

    $log = AuditLog::withoutGlobalScopes()
        ->where('event', 'tax_setting.updated')
        ->where('outlet_id', $workspace['outlet']->id)
        ->latest('id')
        ->firstOrFail();

    expect($log->actor_id)->toBe($workspace['owner']->id)
        ->and($log->auditable_type)->toBe(TaxSetting::class)
        ->and($log->auditable_id)->toBe($setting->id)
        ->and($log->old_values)->toBe([
            'is_enabled' => true,
            'name' => 'PPN lama',
            'rate_basis_points' => 1000,
            'is_inclusive' => false,
        ])
        ->and($log->new_values)->toBe([
            'is_enabled' => true,
            'name' => 'PPN baru',
            'rate_basis_points' => 1050,
            'is_inclusive' => true,
        ])
        ->and(array_key_exists('server_key', $log->new_values ?? []))->toBeFalse()
        ->and(array_key_exists('credentials', $log->new_values ?? []))->toBeFalse();
});

test('tax settings cannot be updated across tenants', function () {
    $workspace = createOutletTaxSettingsWorkspace();
    $foreignTenant = Tenant::factory()->create();
    $foreignOutlet = Outlet::factory()->for($foreignTenant)->create();
    $foreignSetting = TaxSetting::factory()->for($foreignOutlet)->enabled()->create();

    $this->actingAs($workspace['owner'])
        ->withSession(outletTaxSettingsSession($workspace))
        ->patch(route('outlets.tax-settings.update', $foreignOutlet), [
            'tax_enabled' => false,
            'tax_inclusive' => false,
        ])
        ->assertNotFound();

    expect($foreignSetting->fresh()->is_enabled)->toBeTrue()
        ->and($foreignSetting->fresh()->rate_basis_points)->toBe(1000);
});

test('disabling tax normalizes all stored tax details', function () {
    $workspace = createOutletTaxSettingsWorkspace();
    $setting = TaxSetting::withoutGlobalScopes()
        ->where('outlet_id', $workspace['outlet']->id)
        ->firstOrFail();
    $setting->update([
        'is_enabled' => true,
        'name' => 'PPN lama',
        'rate_basis_points' => 1000,
        'is_inclusive' => true,
    ]);

    $this->actingAs($workspace['owner'])
        ->withSession(outletTaxSettingsSession($workspace))
        ->patch(route('outlets.tax-settings.update', $workspace['outlet']), [
            'tax_enabled' => false,
            'tax_name' => 'Nilai usang',
            'tax_rate' => '999.999',
            'tax_inclusive' => true,
        ])
        ->assertRedirect(route('outlets'));

    $this->assertDatabaseHas('tax_settings', [
        'tenant_id' => $workspace['tenant']->id,
        'outlet_id' => $workspace['outlet']->id,
        'is_enabled' => false,
        'name' => null,
        'rate_basis_points' => 0,
        'is_inclusive' => false,
    ]);
});

test('the tax settings endpoint requires the tax permission', function () {
    $workspace = createOutletTaxSettingsWorkspace();
    $member = User::factory()->create();
    $workspace['tenant']->users()->attach($member, [
        'status' => 'active',
        'is_owner' => false,
        'joined_at' => now(),
    ]);

    $this->actingAs($member)
        ->withSession(outletTaxSettingsSession($workspace))
        ->patch(route('outlets.tax-settings.update', $workspace['outlet']), [
            'tax_enabled' => false,
            'tax_inclusive' => false,
        ])
        ->assertForbidden();
});
