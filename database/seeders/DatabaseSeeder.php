<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
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
            AdPackageSeeder::class,
        ]);

        if (config('classifieds.seed_demo_data')) {
            $this->call([DemoSeeder::class, ShowcaseAccountsSeeder::class]);
        }
    }
}
