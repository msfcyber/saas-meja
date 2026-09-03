<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $seeders = [
            RolesAndPermissionsSeeder::class,
            PlansSeeder::class,
            SuperAdminSeeder::class,
        ];

        if (app()->environment(['local', 'testing'])) {
            $seeders[] = DemoTenantSeeder::class;
        }

        $this->call($seeders);
    }
}
