<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CategoryField;
use App\Services\CategoryTree;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $data = require __DIR__.'/data/categories.php';

        $order = 0;

        foreach ($data as $parentData) {
            $parent = Category::updateOrCreate(
                ['slug' => $parentData['slug']],
                [
                    'parent_id' => null,
                    'name' => $parentData['name'],
                    'icon' => $parentData['icon'] ?? null,
                    'sort_order' => ++$order,
                    'is_active' => true,
                ],
            );

            $childOrder = 0;

            foreach ($parentData['children'] as $childSlug => $childName) {
                Category::updateOrCreate(
                    ['slug' => $childSlug],
                    [
                        'parent_id' => $parent->id,
                        'name' => $childName,
                        'sort_order' => ++$childOrder,
                        'is_active' => true,
                    ],
                );
            }

            $fieldOrder = 0;

            foreach ($parentData['fields'] as $field) {
                CategoryField::updateOrCreate(
                    ['category_id' => $parent->id, 'key' => $field['key']],
                    [
                        'name' => $field['name'],
                        'type' => $field['type'],
                        'options' => $field['options'] ?? null,
                        'unit' => $field['unit'] ?? null,
                        'is_required' => $field['required'] ?? false,
                        'is_filterable' => $field['filterable'] ?? false,
                        'sort_order' => ++$fieldOrder,
                    ],
                );
            }
        }

        CategoryTree::flush();
    }
}
