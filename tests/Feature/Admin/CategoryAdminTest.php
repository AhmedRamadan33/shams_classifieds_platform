<?php

declare(strict_types=1);

use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Resources\Categories\RelationManagers\FieldsRelationManager;
use App\Models\Category;
use App\Models\User;
use App\Services\CategoryTree;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->admin = User::factory()->admin()->create();
});

it('lets an admin open the catalog resources', function () {
    $this->actingAs($this->admin);

    foreach (['categories', 'governorates', 'cities'] as $resource) {
        $this->get("/admin/{$resource}")->assertOk();
        $this->get("/admin/{$resource}/create")->assertOk();
    }
});

it('hides the catalog resources from moderators', function () {
    $this->actingAs(User::factory()->moderator()->create());

    foreach (['categories', 'governorates', 'cities'] as $resource) {
        $this->get("/admin/{$resource}")->assertForbidden();
    }
});

it('lists categories in the admin table', function () {
    $this->actingAs($this->admin);
    Category::create(['name' => 'قسم تجريبي', 'slug' => 'demo']);

    Livewire::test(ListCategories::class)
        ->assertCanSeeTableRecords(Category::all());
});

it('creates a category and refreshes the cached tree', function () {
    $this->actingAs($this->admin);
    CategoryTree::get();

    Livewire::test(CreateCategory::class)
        ->fillForm([
            'name' => 'أجهزة منزلية',
            'slug' => 'appliances',
            'icon' => 'home',
            'sort_order' => 3,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(CategoryTree::get()->pluck('slug')->all())->toContain('appliances');
});

it('validates the category slug', function (string $slug) {
    $this->actingAs($this->admin);

    Livewire::test(CreateCategory::class)
        ->fillForm(['name' => 'قسم', 'slug' => $slug, 'sort_order' => 0])
        ->call('create')
        ->assertHasFormErrors(['slug']);
})->with(['uppercase' => ['Cars'], 'arabic' => ['سيارات'], 'spaces' => ['my slug'], 'underscore' => ['my_slug']]);

it('requires unique category slugs', function () {
    $this->actingAs($this->admin);
    Category::create(['name' => 'موجود', 'slug' => 'taken']);

    Livewire::test(CreateCategory::class)
        ->fillForm(['name' => 'جديد', 'slug' => 'taken', 'sort_order' => 0])
        ->call('create')
        ->assertHasFormErrors(['slug']);
});

it('adds a select field to a category through the fields relation manager', function () {
    $this->actingAs($this->admin);
    $category = Category::create(['name' => 'سيارات', 'slug' => 'cars']);
    CategoryTree::get();

    $manager = fn () => Livewire::test(FieldsRelationManager::class, [
        'ownerRecord' => $category,
        'pageClass' => EditCategory::class,
    ]);

    $manager()
        ->callAction(TestAction::make('create')->table(), [
            'name' => 'اللون', 'key' => 'color', 'type' => 'select', 'options' => [], 'sort_order' => 1,
        ])
        ->assertHasFormErrors(['options']);

    $manager()
        ->callAction(TestAction::make('create')->table(), [
            'name' => 'اللون', 'key' => 'اللون', 'type' => 'text', 'sort_order' => 1,
        ])
        ->assertHasFormErrors(['key']);

    $manager()
        ->callAction(TestAction::make('create')->table(), [
            'name' => 'اللون', 'key' => 'color', 'type' => 'select', 'options' => ['أحمر', 'أزرق'],
            'is_required' => true, 'is_filterable' => true, 'sort_order' => 1,
        ])
        ->assertHasNoFormErrors();

    $field = $category->fresh()->effectiveFields()->sole();

    expect($field->key)->toBe('color')
        ->and($field->optionValues())->toBe(['أحمر', 'أزرق'])
        ->and($field->is_required)->toBeTrue();

    $manager()
        ->callAction(TestAction::make('create')->table(), [
            'name' => 'لون آخر', 'key' => 'color', 'type' => 'text', 'sort_order' => 2,
        ])
        ->assertHasFormErrors(['key']);
});
