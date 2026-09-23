<?php

declare(strict_types=1);

use App\Enums\OtpPurpose;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->sms = fakeSms();
});

it('renders the forgot password page', function () {
    $this->get('/forgot-password')->assertOk()->assertSee(__('app.auth.forgot_title'));
});

it('resets the password through the request -> verify -> new password flow', function () {
    $user = User::factory()->create(['phone' => '+201012345678']);

    $this->post('/forgot-password', ['phone' => '01012345678'])
        ->assertRedirect(route('password.verify'));

    expect($this->sms->count())->toBe(1);

    $this->get('/reset-password')->assertRedirect(route('password.request'));

    $this->post('/forgot-password/verify', ['code' => $this->sms->lastCode()])
        ->assertRedirect(route('password.reset'));

    $this->get('/reset-password')->assertOk();

    $this->post('/reset-password', [
        'password' => 'new-password-456',
        'password_confirmation' => 'new-password-456',
    ])->assertRedirect(route('login'));

    expect(Hash::check('new-password-456', $user->fresh()->password))->toBeTrue();

    $this->post('/login', ['phone' => '01012345678', 'password' => 'new-password-456']);
    $this->assertAuthenticatedAs($user);
});

it('does not reveal whether a phone number is registered', function () {
    $this->post('/forgot-password', ['phone' => '01099999999'])
        ->assertRedirect(route('password.verify'))
        ->assertSessionHasNoErrors();

    expect($this->sms->count())->toBe(0);
});

it('does not send codes to banned users', function () {
    User::factory()->banned()->create(['phone' => '+201012345678']);

    $this->post('/forgot-password', ['phone' => '01012345678'])->assertRedirect(route('password.verify'));

    expect($this->sms->count())->toBe(0);
});

it('rejects a wrong reset code', function () {
    User::factory()->create(['phone' => '+201012345678']);
    $this->post('/forgot-password', ['phone' => '01012345678']);
    $wrong = $this->sms->lastCode() === '000000' ? '111111' : '000000';

    $this->post('/forgot-password/verify', ['code' => $wrong])->assertSessionHasErrors('code');

    $this->get('/reset-password')->assertRedirect(route('password.request'));
});

it('does not accept a registration code for the reset flow', function () {
    $user = User::factory()->unverified()->create(['phone' => '+201012345678']);
    app(OtpService::class)->issue($user->phone, OtpPurpose::Register);
    $registerCode = $this->sms->lastCode();

    $this->post('/forgot-password', ['phone' => '01012345678']);

    $this->post('/forgot-password/verify', ['code' => $registerCode])->assertSessionHasErrors('code');
});

it('validates the new password', function () {
    User::factory()->create(['phone' => '+201012345678']);
    $this->post('/forgot-password', ['phone' => '01012345678']);
    $this->post('/forgot-password/verify', ['code' => $this->sms->lastCode()]);

    $this->post('/reset-password', ['password' => 'short', 'password_confirmation' => 'short'])
        ->assertSessionHasErrors('password');
});

it('expires the permission to choose a new password after 10 minutes', function () {
    User::factory()->create(['phone' => '+201012345678']);
    $this->post('/forgot-password', ['phone' => '01012345678']);
    $this->post('/forgot-password/verify', ['code' => $this->sms->lastCode()]);

    $this->travel(11)->minutes();

    $this->post('/reset-password', ['password' => 'new-password-456', 'password_confirmation' => 'new-password-456'])
        ->assertRedirect(route('password.request'));
});
