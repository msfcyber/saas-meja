<?php

namespace Database\Seeders;

use App\Actions\Tenancy\ProvisionTenantRoles;
use App\Enums\ModifierSelectionType;
use App\Enums\TenantStatus;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Modifier;
use App\Models\ModifierOption;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SubscriptionService;
use App\Services\TableQrCodeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoTenantSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $email = config('seeders.demo_owner.email');
        $password = config('seeders.demo_owner.password');

        if (! is_string($email) || trim($email) === '' || ! is_string($password) || $password === '') {
            throw new \RuntimeException('DEMO_OWNER_EMAIL dan DEMO_OWNER_PASSWORD wajib diisi untuk seeder demo lokal.');
        }

        $email = trim($email);
        $owner = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Nadia Pratama',
                'email_verified_at' => now(),
                'password' => Hash::make($password),
            ],
        );

        $tenant = Tenant::query()->updateOrCreate(
            ['slug' => 'kedai-sore'],
            [
                'name' => 'Kedai Sore Group',
                'status' => TenantStatus::Active,
                'timezone' => 'Asia/Jakarta',
            ],
        );

        app(SubscriptionService::class)->provisionTrial($tenant);

        $tenant->users()->syncWithoutDetaching([
            $owner->id => [
                'status' => 'active',
                'is_owner' => true,
                'joined_at' => now(),
            ],
        ]);

        app(ProvisionTenantRoles::class)->handle($tenant, $owner);

        $outlet = Outlet::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'KDS-JKT-01'],
            [
                'name' => 'Kedai Sore',
                'slug' => 'kedai-sore-jakarta',
                'address' => 'Jl. Sore Hari No. 8, Jakarta Selatan',
                'phone' => '021-555-0108',
                'timezone' => 'Asia/Jakarta',
                'currency' => 'IDR',
                'is_active' => true,
                'accepts_orders' => true,
            ],
        );

        $categories = collect([
            ['name' => 'Makanan Utama', 'slug' => 'makanan-utama'],
            ['name' => 'Camilan', 'slug' => 'camilan'],
            ['name' => 'Minuman', 'slug' => 'minuman'],
            ['name' => 'Pencuci Mulut', 'slug' => 'pencuci-mulut'],
        ])->mapWithKeys(function (array $data, int $position) use ($tenant, $outlet) {
            $category = Category::query()->updateOrCreate(
                ['outlet_id' => $outlet->id, 'slug' => $data['slug']],
                [
                    'tenant_id' => $tenant->id,
                    'name' => $data['name'],
                    'position' => $position,
                    'is_active' => true,
                ],
            );

            return [$data['slug'] => $category];
        });

        $products = [
            ['category' => 'makanan-utama', 'name' => 'Nasi Ayam Kecombrang', 'slug' => 'nasi-ayam-kecombrang', 'price' => 48000, 'featured' => true],
            ['category' => 'makanan-utama', 'name' => 'Sate Maranggi', 'slug' => 'sate-maranggi', 'price' => 56000, 'featured' => true],
            ['category' => 'makanan-utama', 'name' => 'Mie Tek-Tek Kampung', 'slug' => 'mie-tek-tek-kampung', 'price' => 42000, 'featured' => false],
            ['category' => 'camilan', 'name' => 'Tahu Lada Garam', 'slug' => 'tahu-lada-garam', 'price' => 32000, 'featured' => false],
            ['category' => 'minuman', 'name' => 'Es Kopi Aren', 'slug' => 'es-kopi-aren', 'price' => 28000, 'featured' => true],
            ['category' => 'pencuci-mulut', 'name' => 'Klepon Cheesecake', 'slug' => 'klepon-cheesecake', 'price' => 38000, 'featured' => false],
        ];

        $seededProducts = collect($products)->mapWithKeys(function (array $data, int $position) use ($tenant, $outlet, $categories) {
            $product = Product::query()->updateOrCreate(
                ['outlet_id' => $outlet->id, 'slug' => $data['slug']],
                [
                    'tenant_id' => $tenant->id,
                    'category_id' => $categories[$data['category']]->id,
                    'name' => $data['name'],
                    'description' => 'Dibuat segar setelah pesanan diterima.',
                    'base_price' => $data['price'],
                    'is_active' => true,
                    'is_available' => true,
                    'is_featured' => $data['featured'],
                    'position' => $position,
                ],
            );

            ProductVariant::query()->updateOrCreate(
                ['product_id' => $product->id, 'name' => 'Regular'],
                [
                    'tenant_id' => $tenant->id,
                    'outlet_id' => $outlet->id,
                    'price_delta' => 0,
                    'is_default' => true,
                    'is_active' => true,
                    'position' => 0,
                ],
            );

            return [$data['slug'] => $product];
        });

        $spice = Modifier::query()->updateOrCreate(
            ['outlet_id' => $outlet->id, 'name' => 'Level Pedas'],
            [
                'tenant_id' => $tenant->id,
                'selection_type' => ModifierSelectionType::Single,
                'minimum_selections' => 1,
                'maximum_selections' => 1,
                'is_required' => true,
                'is_active' => true,
            ],
        );

        foreach (['Tidak Pedas', 'Sedang', 'Pedas'] as $position => $name) {
            ModifierOption::query()->updateOrCreate(
                ['modifier_id' => $spice->id, 'name' => $name],
                [
                    'tenant_id' => $tenant->id,
                    'outlet_id' => $outlet->id,
                    'price_delta' => 0,
                    'is_active' => true,
                    'position' => $position,
                ],
            );
        }

        foreach (['nasi-ayam-kecombrang', 'mie-tek-tek-kampung'] as $slug) {
            $seededProducts[$slug]->modifiers()->syncWithoutDetaching([
                $spice->id => [
                    'tenant_id' => $tenant->id,
                    'outlet_id' => $outlet->id,
                    'position' => 0,
                ],
            ]);
        }

        foreach (range(1, 12) as $number) {
            $table = DiningTable::query()->updateOrCreate(
                ['outlet_id' => $outlet->id, 'code' => sprintf('TBL-%03d', $number)],
                [
                    'tenant_id' => $tenant->id,
                    'name' => sprintf('Meja %02d', $number),
                    'zone' => $number <= 3 ? 'Teras' : ($number <= 8 ? 'Indoor' : 'Lantai 2'),
                    'capacity' => $number % 3 === 0 ? 6 : 4,
                    'is_active' => true,
                ],
            );

            app(TableQrCodeService::class)->ensure($table);
        }
    }
}
