<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlansSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = config('subscriptions.default_plan', []);
        $defaults = is_array($defaults) ? $defaults : [];
        $code = (string) ($defaults['code'] ?? 'tumbuh');

        Plan::query()->firstOrCreate(
            ['code' => $code],
            [
                'name' => (string) ($defaults['name'] ?? 'Tumbuh'),
                'description' => (string) ($defaults['description'] ?? 'Plan awal untuk bisnis F&B yang sedang bertumbuh.'),
                'price' => (int) ($defaults['price'] ?? 299000),
                'currency' => (string) ($defaults['currency'] ?? 'IDR'),
                'billing_interval' => (string) ($defaults['billing_interval'] ?? 'monthly'),
                'limits' => is_array($defaults['limits'] ?? null) ? $defaults['limits'] : [],
                'features' => is_array($defaults['features'] ?? null) ? $defaults['features'] : [],
                'is_active' => true,
                'position' => 0,
            ],
        );
    }
}
