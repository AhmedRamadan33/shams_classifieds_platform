<?php

declare(strict_types=1);

use App\Models\User;

beforeEach(function () {
    $this->sms = fakeSms();
});

it('renders the login page', function () {
    $this->get('/login')->assertOk()->assertSee(__('app.auth.login_title'));
});

it('logs in with a phone number and password', function () {
    $user = User::factory()->create(['phone' => '+201012345678']);

    $this->post('/login', ['phone' => '01012345678', 'password' => 'password'])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('logs in with an e-mail address and password, whatever its letter case', function (string $typed) {
    $user = User::factory()->create(['phone' => '+201012345678', 'email' => 'admin@shams.test']);

    $this->post('/login', ['phone' => $typed, 'password' => 'password'])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
})->with(['admin@shams.test', 'Admin@Shams.Test', '  admin@shams.test ']);

it('rejects an unknown e-mail address or a wrong password for a known one', function () {
    User::factory()->create(['phone' => '+201012345678', 'email' => 'admin@shams.test']);

    $this->post('/login', ['phone' => 'nobody@shams.test', 'password' => 'password'])->assertSessionHasErrors('phone');
    $this->post('/login', ['phone' => 'admin@shams.test', 'password' => 'wrong-password'])->assertSessionHasErrors('phone');

    $this->assertGuest();
});

it('blocks a banned user who logs in with an e-mail address', function () {
    User::factory()->banned()->create(['phone' => '+201012345678', 'email' => 'admin@shams.test']);

    $this->post('/login', ['phone' => 'admin@shams.test', 'password' => 'password'])
        ->assertSessionHasErrors(['phone' => __('app.auth.banned')]);

    $this->assertGuest();
});

it('accepts the phone in any supported format', function (string $typed) {
    $user = User::factory()->create(['phone' => '+201012345678']);

    $this->post('/login', ['phone' => $typed, 'password' => 'password']);

    $this->assertAuthenticatedAs($user);
})->with(['٠١٠١٢٣٤٥٦٧٨', '+201012345678', '010 1234 5678']);

it('rejects a wrong password', function () {
    User::factory()->create(['phone' => '+201012345678']);

    $this->post('/login', ['phone' => '01012345678', 'password' => 'wrong-password'])
        ->assertSessionHasErrors(['phone' => trans('auth.failed')]);

    $this->assertGuest();
});

it('rejects an unknown phone number', function () {
    $this->post('/login', ['phone' => '01099999999', 'password' => 'password'])
        ->assertSessionHasErrors('phone');

    $this->assertGuest();
});

it('blocks banned users with an Arabic message', function () {
    User::factory()->banned()->create(['phone' => '+201012345678']);

    $this->post('/login', ['phone' => '01012345678', 'password' => 'password'])
        ->assertSessionHasErrors(['phone' => __('app.auth.banned')]);

    $this->assertGuest();
});

it('sends unverified users to the OTP page instead of logging them in', function () {
    User::factory()->unverified()->create(['phone' => '+201012345678']);

    $this->post('/login', ['phone' => '01012345678', 'password' => 'password'])
        ->assertRedirect(route('phone.verification.notice'));

    $this->assertGuest();
    expect($this->sms->count())->toBe(1);

    $this->post('/verify-phone', ['code' => $this->sms->lastCode()])->assertRedirect(route('dashboard'));
    $this->assertAuthenticated();
});

it('logs out a user who is banned while already signed in', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/dashboard')->assertOk();

    $user->update(['is_banned' => true]);

    $this->get('/dashboard')->assertRedirect(route('login'));
    $this->assertGuest();
});

it('throttles repeated failed logins', function () {
    User::factory()->create(['phone' => '+201012345678']);

    foreach (range(1, 5) as $ignored) {
        $this->post('/login', ['phone' => '01012345678', 'password' => 'wrong-password']);
    }

    $this->post('/login', ['phone' => '01012345678', 'password' => 'password'])
        ->assertSessionHasErrors('phone');

    $this->assertGuest();
});

it('logs out', function () {
    $this->actingAs(User::factory()->create());

    $this->post('/logout')->assertRedirect(route('home'));

    $this->assertGuest();
});

it('redirects guests away from protected pages', function () {
    $this->get('/dashboard')->assertRedirect(route('login'));
    $this->get('/profile')->assertRedirect(route('login'));
});

it('redirects signed-in users away from the login page', function () {
    $this->actingAs(User::factory()->create())->get('/login')->assertRedirect();
});
