<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use Database\Seeders\Support\DemoConversations;

it('never talks about price, condition or selling in a job listing conversation', function (string $slug) {
    $category = Category::query()->firstOrCreate(['slug' => $slug], Category::factory()->raw());
    $seller = User::factory()->create();
    $buyer = User::factory()->create();
    $listing = Listing::factory()->for($category)->for($seller)->create(['price' => null]);

    $conversation = DemoConversations::create($listing, $buyer);

    $body = $conversation->messages->pluck('body')->implode(' ');

    expect($body)->not->toContain('السبب في البيع')
        ->and($body)->not->toContain('استعمال نضيف')
        ->and($body)->not->toContain('آخر سعر')
        ->and($body)->toContain('السيرة الذاتية');
})->with(['job-vacancies', 'job-seekers']);

it('picks the vehicle, real estate or service dialogue for their own categories', function (string $slug, string $needle) {
    $category = Category::query()->firstOrCreate(['slug' => $slug], Category::factory()->raw());
    $listing = Listing::factory()->for($category)->create();

    $conversation = DemoConversations::create($listing, User::factory()->create());

    expect($conversation->messages->pluck('body')->implode(' '))->toContain($needle);
})->with([
    ['cars-for-sale', 'الرخصة سارية'],
    ['villas', 'اللوكيشن'],
    ['maintenance-and-finishing', 'المعاينة'],
]);

it('falls back to the generic for-sale dialogue for a plain goods category', function () {
    $category = Category::query()->firstOrCreate(['slug' => 'mobiles'], Category::factory()->raw());
    $listing = Listing::factory()->for($category)->create();

    $conversation = DemoConversations::create($listing, User::factory()->create());

    expect($conversation->messages->pluck('body')->implode(' '))->toContain('آخر سعر');
});

it('replaces the title and price placeholders with the real listing values', function () {
    $listing = Listing::factory()->create(['title' => 'إعلان اختبار فريد', 'price' => 1234]);

    $conversation = DemoConversations::create($listing, User::factory()->create());

    $body = $conversation->messages->pluck('body')->implode(' ');

    expect($body)->toContain('إعلان اختبار فريد')
        ->and($body)->not->toContain('{title}')
        ->and($body)->not->toContain('{price}');
});
