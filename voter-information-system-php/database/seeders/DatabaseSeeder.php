<?php

namespace Database\Seeders;

use App\Services\Auth\Defaults;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** Default roles + first administrator. Demo data: php artisan db:seed --class=DemoSeeder */
    public function run(): void
    {
        app(Defaults::class)->ensure(true);
    }
}
