<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AdPackage;
use Illuminate\Database\Seeder;

class AdPackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            ['name' => 'الرئيسية - 7 أيام', 'placement' => 'home_top', 'duration_days' => 7, 'price' => 200, 'sort_order' => 1],
            ['name' => 'الرئيسية - 30 يوماً', 'placement' => 'home_top', 'duration_days' => 30, 'price' => 650, 'sort_order' => 2],
            ['name' => 'نتائج البحث - 7 أيام', 'placement' => 'search_sidebar', 'duration_days' => 7, 'price' => 100, 'sort_order' => 3],
            ['name' => 'نتائج البحث - 30 يوماً', 'placement' => 'search_sidebar', 'duration_days' => 30, 'price' => 350, 'sort_order' => 4],
            ['name' => 'صفحة الإعلان - 7 أيام', 'placement' => 'listing_sidebar', 'duration_days' => 7, 'price' => 80, 'sort_order' => 5],
            ['name' => 'صفحة الإعلان - 30 يوماً', 'placement' => 'listing_sidebar', 'duration_days' => 30, 'price' => 280, 'sort_order' => 6],
        ];

        foreach ($packages as $package) {
            AdPackage::firstOrCreate(['name' => $package['name']], $package);
        }
    }
}
