<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

it('registers, sends an OTP, and does not yet return a token', function () {
    $sms = fakeSms();

    $this->postJson('/api/v1/auth/register', [
        'name' => 'أحمد', 'phone' => '01012345678', 'password' => 'password123', 'password_confirmation' => 'password123',
    ])->assertCreated()->assertJsonStructure(['message', 'phone'])->assertJsonMissingPath('token');

    expect($sms->count())->toBe(1)
        ->and(User::where('phone', '+201012345678')->exists())->toBeTrue()
        ->and(User::where('phone', '+201012345678')->first()->hasVerifiedPhone())->toBeFalse();
});

it('validates registration input', function () {
    $this->postJson('/api/v1/auth/register', ['name' => '', 'phone' => 'x', 'password' => 'a'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'phone', 'password']);
});

it('verifies the OTP and returns a usable token', function () {
    $sms = fakeSms();
    $this->postJson('/api/v1/auth/register', [
        'name' => 'أحمد', 'phone' => '01012345678', 'password' => 'password123', 'password_confirmation' => 'password123',
    ]);
    $code = $sms->lastCode();

    $response = $this->postJson('/api/v1/auth/verify-otp', ['phone' => '01012345678', 'code' => $code])
        ->assertOk()
        ->assertJsonStructure(['token', 'data' => ['id', 'name', 'phone', 'phone_verified']]);

    expect($response->json('data.phone_verified'))->toBeTrue();

    $token = $response->json('token');
    $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.name', 'أحمد');
});

it('rejects a wrong OTP', function () {
    fakeSms();
    $this->postJson('/api/v1/auth/register', [
        'name' => 'أحمد', 'phone' => '01012345678', 'password' => 'password123', 'password_confirmation' => 'password123',
    ]);

    $this->postJson('/api/v1/auth/verify-otp', ['phone' => '01012345678', 'code' => '000000'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('code');

    expect(User::where('phone', '+201012345678')->first()->hasVerifiedPhone())->toBeFalse();
});

it('resends the OTP, honoring the cooldown', function () {
    $sms = fakeSms();
    $this->postJson('/api/v1/auth/register', [
        'name' => 'أحمد', 'phone' => '01012345678', 'password' => 'password123', 'password_confirmation' => 'password123',
    ]);

    $this->postJson('/api/v1/auth/resend-otp', ['phone' => '01012345678'])->assertStatus(429);
    expect($sms->count())->toBe(1);

    $this->travel(2)->minutes();
    $this->postJson('/api/v1/auth/resend-otp', ['phone' => '01012345678'])->assertOk();
    expect($sms->count())->toBe(2);
});

it('logs a verified user in with phone and password', function () {
    $user = User::factory()->create(['phone' => '+201012345678', 'password' => 'password123']);

    $response = $this->postJson('/api/v1/auth/login', ['phone' => '01012345678', 'password' => 'password123'])
        ->assertOk()
        ->assertJsonStructure(['token', 'data']);

    expect($response->json('data.id'))->toBe($user->id);
});

it('logs a verified user in with an e-mail address and password', function () {
    $user = User::factory()->create(['email' => 'admin@shams.test', 'password' => 'password123']);

    $response = $this->postJson('/api/v1/auth/login', ['phone' => 'admin@shams.test', 'password' => 'password123'])
        ->assertOk()
        ->assertJsonStructure(['token', 'data']);

    expect($response->json('data.id'))->toBe($user->id);
});

it('rejects a login with the wrong password', function () {
    User::factory()->create(['phone' => '+201012345678', 'password' => 'password123']);

    $this->postJson('/api/v1/auth/login', ['phone' => '01012345678', 'password' => 'wrong'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('phone');
});

it('refuses to log in a user whose phone is not verified yet', function () {
    User::factory()->unverified()->create(['phone' => '+201012345678', 'password' => 'password123']);

    $this->postJson('/api/v1/auth/login', ['phone' => '01012345678', 'password' => 'password123'])
        ->assertStatus(403)
        ->assertJsonPath('phone_verified', false);
});

it('refuses to log in a banned user', function () {
    User::factory()->create(['phone' => '+201012345678', 'password' => 'password123', 'is_banned' => true]);

    $this->postJson('/api/v1/auth/login', ['phone' => '01012345678', 'password' => 'password123'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('phone');
});

it('rejects requests to protected endpoints without a token', function () {
    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    $this->getJson('/api/v1/my/listings')->assertUnauthorized();
});

it('rejects an invalid or revoked token', function () {
    $this->withToken('not-a-real-token')->getJson('/api/v1/auth/me')->assertUnauthorized();
});

it('logs out and revokes the token so it can no longer be used', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    expect(PersonalAccessToken::count())->toBe(1);

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

    expect(PersonalAccessToken::count())->toBe(0);

    $this->app['auth']->forgetGuards();

    $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
});

it('immediately revokes the token of a user who gets banned', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $user->update(['is_banned' => true]);

    $this->withToken($token)->getJson('/api/v1/my/listings')->assertStatus(403);
    expect(PersonalAccessToken::count())->toBe(0);
});
