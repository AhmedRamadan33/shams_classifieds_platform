<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;

/**
 * Default featured-ad packages. Editable from the admin panel afterwards; re-seeding never
 * overwrites a package that already exists (matched by name).
 */
class PackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            ['name' => '3 أيام', 'days' => 3, 'price' => 30, 'sort_order' => 1],
            ['name' => '7 أيام', 'days' => 7, 'price' => 60, 'sort_order' => 2],
            ['name' => '30 يوماً', 'days' => 30, 'price' => 200, 'sort_order' => 3],
        ];

        foreach ($packages as $package) {
            Package::firstOrCreate(['name' => $package['name']], $package);
        }
    }
}
