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

it('no longer renders a slider on the home page', function () {
    $this->get('/')->assertOk()->assertDontSee('aria-roledescription="carousel"', false);
});

it('no longer has a hero_slides table', function () {
    expect(Schema::hasTable('hero_slides'))->toBeFalse();
});
