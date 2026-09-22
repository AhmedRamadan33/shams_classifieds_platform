<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\CategoryField;
use App\Models\City;
use App\Models\Governorate;
use App\Services\CategoryTree;
use Database\Seeders\CategorySeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\GeographySeeder;
use Illuminate\Support\Facades\Cache;

function makeChain(): array
{
    $root = Category::create(['name' => 'الجذر', 'slug' => 'root']);
    $child = Category::create(['name' => 'الابن', 'slug' => 'child', 'parent_id' => $root->id]);
    $grandchild = Category::create(['name' => 'الحفيد', 'slug' => 'grandchild', 'parent_id' => $child->id]);

    return [$root, $child, $grandchild];
}

function makeField(Category $category, string $key, int $order = 0, string $type = 'text', array $extra = []): CategoryField
{
    return CategoryField::create(array_merge([
        'category_id' => $category->id,
        'name' => $key,
        'key' => $key,
        'type' => $type,
        'sort_order' => $order,
    ], $extra));
}

it('returns own fields plus every ancestor field, parent first then by sort_order', function () {
    [$root, $child, $grandchild] = makeChain();

    makeField($root, 'root_b', 2);
    makeField($root, 'root_a', 1);
    makeField($child, 'child_a', 5);
    makeField($grandchild, 'grand_b', 2);
    makeField($grandchild, 'grand_a', 1);

    expect($grandchild->effectiveFields()->pluck('key')->all())
        ->toBe(['root_a', 'root_b', 'child_a', 'grand_a', 'grand_b'])
        ->and($child->effectiveFields()->pluck('key')->all())->toBe(['root_a', 'root_b', 'child_a'])
        ->and($root->effectiveFields()->pluck('key')->all())->toBe(['root_a', 'root_b']);
});

it('does not leak fields between sibling branches', function () {
    $root = Category::create(['name' => 'جذر', 'slug' => 'root']);
    $left = Category::create(['name' => 'يسار', 'slug' => 'left', 'parent_id' => $root->id]);
    $right = Category::create(['name' => 'يمين', 'slug' => 'right', 'parent_id' => $root->id]);

    makeField($left, 'only_left');
    makeField($right, 'only_right');

    expect($left->effectiveFields()->pluck('key')->all())->toBe(['only_left'])
        ->and($right->effectiveFields()->pluck('key')->all())->toBe(['only_right']);
});

it('knows leaves, descendants and whether a category can receive listings', function () {
    [$root, $child, $grandchild] = makeChain();

    expect($root->isLeaf())->toBeFalse()
        ->and($grandchild->isLeaf())->toBeTrue()
        ->and($root->descendantIds())->toEqualCanonicalizing([$child->id, $grandchild->id])
        ->and($root->descendantIds(includeSelf: true))->toContain($root->id)
        ->and($grandchild->descendantIds())->toBe([])
        ->and($grandchild->isPostable())->toBeTrue()
        ->and($child->isPostable())->toBeFalse();

    // An inactive ancestor makes the whole branch unpostable.
    $root->update(['is_active' => false]);
    expect($grandchild->fresh()->isPostable())->toBeFalse();
});

it('treats a category whose children are all inactive as a leaf', function () {
    [$root, $child] = makeChain();
    $child->update(['is_active' => false]);

    expect($root->fresh()->isLeaf())->toBeTrue();
});

it('caches the active tree and leaves inactive branches out', function () {
    [$root, $child, $grandchild] = makeChain();
    Category::create(['name' => 'مخفي', 'slug' => 'hidden', 'parent_id' => $root->id, 'is_active' => false]);

    $tree = CategoryTree::get();

    expect(Cache::has(CategoryTree::CACHE_KEY))->toBeTrue()
        ->and($tree->pluck('slug')->all())->toBe(['root'])
        ->and($tree->first()->children->pluck('slug')->all())->toBe(['child'])
        ->and($tree->first()->children->first()->children->pluck('slug')->all())->toBe(['grandchild'])
        ->and(CategoryTree::find($grandchild->id)?->slug)->toBe('grandchild')
        ->and(CategoryTree::flatten()->pluck('slug')->all())->toBe(['root', 'child', 'grandchild']);
});

it('flushes the cached tree when a category or a field is created, updated or deleted', function () {
    $root = Category::create(['name' => 'جذر', 'slug' => 'root']);

    $warm = function () {
        CategoryTree::get();
        expect(Cache::has(CategoryTree::CACHE_KEY))->toBeTrue();
    };
    $flushed = fn () => expect(Cache::has(CategoryTree::CACHE_KEY))->toBeFalse();

    $warm();
    $child = Category::create(['name' => 'ابن', 'slug' => 'child', 'parent_id' => $root->id]);
    $flushed();

    $warm();
    $child->update(['name' => 'ابن معدّل']);
    $flushed();

    $warm();
    $field = makeField($root, 'brand');
    $flushed();

    $warm();
    $field->update(['name' => 'الماركة']);
    $flushed();

    $warm();
    $field->delete();
    $flushed();

    $warm();
    $child->delete();
    $flushed();

    expect(CategoryTree::get()->first()->children)->toHaveCount(0);
});

it('makes a newly created category with a select field visible in the cached tree', function () {
    CategoryTree::get(); // warm the cache before the change

    $category = Category::create(['name' => 'أجهزة', 'slug' => 'gadgets']);
    makeField($category, 'color', 1, 'select', ['options' => ['أحمر', 'أزرق']]);

    expect(CategoryTree::get()->pluck('slug')->all())->toContain('gadgets')
        ->and($category->effectiveFields()->first()->optionValues())->toBe(['أحمر', 'أزرق']);
});

it('only keeps options and unit for the field types that use them', function () {
    $category = Category::create(['name' => 'قسم', 'slug' => 'cat']);

    $field = makeField($category, 'color', 0, 'select', ['options' => ['أحمر']]);
    $field->update(['type' => 'text']);

    expect($field->fresh()->options)->toBeNull();

    $number = makeField($category, 'size', 0, 'number', ['unit' => 'م²']);
    $number->update(['type' => 'text']);

    expect($number->fresh()->unit)->toBeNull();
});

it('seeds geography and categories idempotently', function () {
    $this->seed(DatabaseSeeder::class);

    $counts = fn () => [Governorate::count(), City::count(), Category::count(), CategoryField::count()];
    $first = $counts();

    $this->seed(DatabaseSeeder::class);
    $this->seed(GeographySeeder::class);
    $this->seed(CategorySeeder::class);

    expect($counts())->toBe($first)
        ->and($first[0])->toBe(27)
        ->and($first[3])->toBeGreaterThan(20);
});

it('gives seeded child categories the inherited fields of their parent', function () {
    $this->seed(CategorySeeder::class);

    $cars = Category::where('slug', 'cars-for-sale')->sole();
    $keys = $cars->effectiveFields()->pluck('key')->all();

    expect($keys)->toBe(['brand', 'model', 'year', 'mileage', 'transmission', 'fuel_type', 'condition'])
        ->and($cars->effectiveFields()->firstWhere('key', 'brand')->is_required)->toBeTrue()
        ->and($cars->effectiveFields()->firstWhere('key', 'mileage')->unit)->toBe('كم')
        ->and(Category::where('slug', 'other')->sole()->isLeaf())->toBeTrue();
});

it('keeps governorate and city slugs stable and ASCII', function () {
    $this->seed(GeographySeeder::class);

    expect(Governorate::pluck('slug')->every(fn ($slug) => preg_match('/^[a-z0-9-]+$/', $slug) === 1))->toBeTrue()
        ->and(City::pluck('slug')->every(fn ($slug) => preg_match('/^[a-z0-9-]+$/', $slug) === 1))->toBeTrue();
});
