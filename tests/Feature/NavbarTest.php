<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Schema;

it('shows the browse and advertise links in the navbar to guests and signed-in users', function () {
    $this->get('/')->assertOk()
        ->assertSee('href="'.route('search').'"', false)
        ->assertSee('href="'.route('ad-banners.create').'"', false)
        ->assertSee(__('app.nav.browse'))
        ->assertSee(__('app.nav.advertise'));

    $this->actingAs(User::factory()->create())->get('/')->assertOk()
        ->assertSee('href="'.route('ad-banners.create').'"', false)
        ->assertSee('href="'.route('ad-banners.index').'"', false)
        ->assertSee(__('app.nav.my_banners'));
});

it('has a home link in the desktop navbar for guests and signed-in users, marked current on the home page', function () {
    $nav = fn (string $html): string => preg_match('#<nav[^>]*class="hidden[^"]*lg:flex"[^>]*>(.*?)</nav>#s', $html, $m) ? $m[1] : '';

    $home = $this->get('/')->assertOk()->getContent();

    expect($nav($home))->toContain('href="'.route('home').'"')
        ->and($nav($home))->toContain(__('app.nav.home'))
        ->and($nav($home))->toContain('aria-current="page"');

    $other = $this->get('/search')->assertOk()->getContent();

    expect($nav($other))->toContain('href="'.route('home').'"')
        ->and($nav($other))->not->toContain('aria-current="page"');

    $signedIn = $this->actingAs(User::factory()->create())->get('/search')->assertOk()->getContent();

    expect($nav($signedIn))->toContain('href="'.route('home').'"');
});

it('no longer renders a slider on the home page', function () {
    $this->get('/')->assertOk()->assertDontSee('aria-roledescription="carousel"', false);
});

it('no longer has a hero_slides table', function () {
    expect(Schema::hasTable('hero_slides'))->toBeFalse();
});
