<?php

declare(strict_types=1);

use App\Enums\FieldType;
use App\Models\Category;
use Tests\Support\Fixtures;

it('returns the effective fields of a leaf category as JSON', function () {
    $tree = Fixtures::carsTree();
    Fixtures::field($tree['leaf'], 'trim', 'الفئة', FieldType::Text, 9);

    $this->getJson("/api/categories/{$tree['leaf']->id}/fields")
        ->assertOk()
        ->assertJsonPath('category.id', $tree['leaf']->id)
        ->assertJsonCount(6, 'fields')
        ->assertJsonPath('fields.0.key', 'brand')
        ->assertJsonPath('fields.0.type', 'select')
        ->assertJsonPath('fields.0.options', ['تويوتا', 'هيونداي', 'كيا'])
        ->assertJsonPath('fields.0.is_required', true)
        ->assertJsonPath('fields.3.unit', 'كم')
        ->assertJsonPath('fields.5.key', 'trim');
});

it('returns 404 for categories that cannot receive listings', function () {
    $tree = Fixtures::carsTree();

    $this->getJson("/api/categories/{$tree['parent']->id}/fields")->assertNotFound();

    $inactive = Category::factory()->inactive()->create();
    $this->getJson("/api/categories/{$inactive->id}/fields")->assertNotFound();

    $this->getJson('/api/categories/999999/fields')->assertNotFound();
});

it('returns an empty list for a leaf category without fields', function () {
    $category = Category::factory()->create();

    $this->getJson("/api/categories/{$category->id}/fields")->assertOk()->assertExactJson([
        'category' => ['id' => $category->id, 'name' => $category->name],
        'fields' => [],
    ]);
});
