<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Outlet;
use App\Models\PaymentGatewayCredential;
use App\Models\Product;
use App\Models\TableQrToken;
use App\Models\Tenant;
use App\Services\SubscriptionService;
use Illuminate\Database\Seeder;

class BrowserTestSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::withoutGlobalScopes()->create([
            'name' => 'Kedai E2E Group',
            'slug' => 'kedai-e2e-group',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
        ]);
        app(SubscriptionService::class)->provisionTrial($tenant);

        $outlet = Outlet::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Kedai E2E',
            'slug' => 'kedai-e2e',
            'code' => 'E2E-001',
            'address' => 'Jl. Pengujian No. 1',
            'phone' => '021-000-0000',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'is_active' => true,
            'accepts_orders' => true,
        ]);
        $category = Category::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'outlet_id' => $outlet->getKey(),
            'name' => 'Makanan Utama',
            'slug' => 'makanan-utama',
            'position' => 0,
            'is_active' => true,
        ]);
        Product::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'outlet_id' => $outlet->getKey(),
            'category_id' => $category->getKey(),
            'name' => 'Nasi Bakar E2E',
            'slug' => 'nasi-bakar-e2e',
            'description' => 'Menu khusus untuk pengujian browser.',
            'base_price' => 28000,
            'is_active' => true,
            'is_available' => true,
            'is_featured' => false,
            'position' => 0,
        ]);
        $table = DiningTable::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'outlet_id' => $outlet->getKey(),
            'name' => 'Meja E2E',
            'code' => 'E2E-001',
            'zone' => 'Indoor',
            'capacity' => 4,
            'is_active' => true,
        ]);
        TableQrToken::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'outlet_id' => $outlet->getKey(),
            'table_id' => $table->getKey(),
            'token_hash' => hash('sha256', str_repeat('a', 64)),
        ]);
        PaymentGatewayCredential::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'provider' => 'midtrans',
            'version' => 1,
            'secret' => 'e2e-midtrans-key',
        ]);
    }
}
