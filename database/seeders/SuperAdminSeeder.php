<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('seeders.superadmin.email');
        $password = config('seeders.superadmin.password');

        if (! is_string($email) || trim($email) === '' || ! is_string($password) || $password === '') {
            throw new \RuntimeException('SEED_SUPERADMIN_EMAIL dan SEED_SUPERADMIN_PASSWORD wajib diisi sebelum menjalankan seeder.');
        }

        $email = trim($email);
        $superAdmin = User::query()->firstOrNew(['email' => $email]);
        $superAdmin->forceFill([
            'name' => 'Super Admin',
            'email_verified_at' => now(),
            'password' => Hash::make($password),
            'is_platform_admin' => true,
        ])->save();
    }
}
