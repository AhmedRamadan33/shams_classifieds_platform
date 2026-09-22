<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database. Every seeder is idempotent, so running
     * `php artisan migrate:fresh --seed` (or `db:seed`) repeatedly is safe.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            AdminSeeder::class,
            GeographySeeder::class,
            CategorySeeder::class,
            PageSeeder::class,
            PackageSeeder::class,
            PlanSeeder::class,
        ]);
    }
}
