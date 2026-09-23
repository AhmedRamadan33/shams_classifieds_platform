<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Str;

it('renders notification links as same-origin paths even when stored with another host', function () {
    $user = User::factory()->create();
    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\Notifications\ListingApproved',
        'data' => ['message' => 'تمت الموافقة على إعلانك', 'url' => 'http://some-other-host.test:8000/ad/5/slug?x=1'],
    ]);

    $html = $this->actingAs($user)->get(route('notifications.index'))->assertOk()->getContent();

    expect($html)->toContain('href="/ad/5/slug?x=1"')
        ->and($html)->not->toContain('some-other-host.test');
});

it('falls back to the dashboard when a notification has no url', function () {
    $user = User::factory()->create();
    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\Notifications\ListingApproved',
        'data' => ['message' => 'إشعار بدون رابط'],
    ]);

    $this->actingAs($user)->get(route('notifications.index'))
        ->assertOk()
        ->assertSee('href="/dashboard"', false);
});
