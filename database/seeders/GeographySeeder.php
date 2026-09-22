<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\City;
use App\Models\Governorate;
use Illuminate\Database\Seeder;

class GeographySeeder extends Seeder
{
    public function run(): void
    {
        $data = require __DIR__.'/data/geography.php';

        $governorateOrder = 0;

        foreach ($data as $governorateSlug => $governorateData) {
            $governorate = Governorate::updateOrCreate(
                ['slug' => $governorateSlug],
                ['name' => $governorateData['name'], 'sort_order' => ++$governorateOrder],
            );

            $cityOrder = 0;

            foreach ($governorateData['cities'] as $citySlug => $cityName) {
                City::updateOrCreate(
                    ['governorate_id' => $governorate->id, 'slug' => $citySlug],
                    ['name' => $cityName, 'sort_order' => ++$cityOrder],
                );
            }
        }
    }
}
