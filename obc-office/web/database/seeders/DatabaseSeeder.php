<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(MasterDataSeeder::class);

        if (! User::where('role', 'super_admin')->exists()) {
            $password = env('ADMIN_PASSWORD') ?: Str::password(14, symbols: false);
            User::create([
                'name' => 'Office Administrator',
                'username' => env('ADMIN_USERNAME', 'admin'),
                'email' => env('ADMIN_EMAIL', 'admin@users.local'),
                'password' => $password,
                'role' => 'super_admin',
                'active' => true,
            ]);
            $this->command?->warn('Super admin created → username: '.env('ADMIN_USERNAME', 'admin')."  password: $password  (change it after first login)");
        }

        if (filter_var(env('SEED_DEMO', false), FILTER_VALIDATE_BOOL)) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
