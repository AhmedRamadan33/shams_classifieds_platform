<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            ['name' => 'أساسي', 'duration_days' => 30, 'daily_listing_limit' => 20, 'price' => 100, 'sort_order' => 1],
            ['name' => 'احترافي', 'duration_days' => 30, 'daily_listing_limit' => 50, 'price' => 250, 'sort_order' => 2],
            ['name' => 'أعمال', 'duration_days' => 90, 'daily_listing_limit' => 100, 'price' => 600, 'sort_order' => 3],
        ];

        foreach ($plans as $plan) {
            Plan::firstOrCreate(['name' => $plan['name']], $plan);
        }
    }
}
