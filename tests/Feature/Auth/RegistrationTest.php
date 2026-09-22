<?php

declare(strict_types=1);

use App\Models\OtpCode;
use App\Models\User;

beforeEach(function () {
    $this->sms = fakeSms();
});

function registerPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'أحمد محمود',
        'phone' => '01012345678',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ], $overrides);
}

it('renders the registration page in Arabic', function () {
    $this->get('/register')
        ->assertOk()
        ->assertSee(__('app.auth.register_title'));
});

it('creates an unverified user, sends an OTP and does NOT log the user in yet', function () {
    $this->post('/register', registerPayload())
        ->assertRedirect(route('phone.verification.notice'));

    $user = User::where('phone', '+201012345678')->sole();

    expect($user->hasVerifiedPhone())->toBeFalse()
        ->and($user->hasRole('user'))->toBeTrue()
        ->and($this->sms->count())->toBe(1)
        ->and($this->sms->messages[0]['phone'])->toBe('+201012345678');

    $this->assertGuest();
});

it('requires the OTP: protected pages stay closed until the phone is verified', function () {
    $this->post('/register', registerPayload());

    $this->get('/dashboard')->assertRedirect(route('login'));
    $this->assertGuest();
});

it('shows the verification page with the phone number after registering', function () {
    $this->post('/register', registerPayload());

    $this->get('/verify-phone')
        ->assertOk()
        ->assertSee('+201012345678');
});

it('rejects a wrong OTP', function () {
    $this->post('/register', registerPayload());
    $wrong = $this->sms->lastCode() === '000000' ? '111111' : '000000';

    $this->from('/verify-phone')
        ->post('/verify-phone', ['code' => $wrong])
        ->assertRedirect('/verify-phone')
        ->assertSessionHasErrors('code');

    $this->assertGuest();
    expect(User::where('phone', '+201012345678')->sole()->hasVerifiedPhone())->toBeFalse();
});

it('locks the code after 5 wrong attempts so even the correct code no longer works', function () {
    $this->post('/register', registerPayload());
    $code = $this->sms->lastCode();
    $wrong = $code === '000000' ? '111111' : '000000';

    foreach (range(1, 5) as $ignored) {
        $this->post('/verify-phone', ['code' => $wrong])->assertSessionHasErrors('code');
    }

    $this->post('/verify-phone', ['code' => $code])
        ->assertSessionHasErrors(['code' => __('app.auth.code_locked')]);

    $this->assertGuest();
    expect(OtpCode::sole()->attempts)->toBe(5);
});

it('verifies the phone with the correct code and logs the user in', function () {
    $this->post('/register', registerPayload());

    $this->post('/verify-phone', ['code' => $this->sms->lastCode()])
        ->assertRedirect(route('dashboard'));

    $user = User::where('phone', '+201012345678')->sole();

    $this->assertAuthenticatedAs($user);
    expect($user->hasVerifiedPhone())->toBeTrue();
});

it('accepts a code typed with Arabic-Indic digits', function () {
    $this->post('/register', registerPayload());
    $arabic = strtr($this->sms->lastCode(), array_flip(['0', '1', '2', '3', '4', '5', '6', '7', '8', '9']) + []);
    $arabic = strtr($this->sms->lastCode(), ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']);

    $this->post('/verify-phone', ['code' => $arabic])->assertRedirect(route('dashboard'));
    $this->assertAuthenticated();
});

it('rejects an expired code', function () {
    $this->post('/register', registerPayload());
    $code = $this->sms->lastCode();

    $this->travel(6)->minutes();

    $this->post('/verify-phone', ['code' => $code])
        ->assertSessionHasErrors(['code' => __('app.auth.code_expired')]);
    $this->assertGuest();
});

it('can resend the code, but only after the cooldown', function () {
    $this->post('/register', registerPayload());

    $this->post('/verify-phone/resend')->assertSessionHasErrors('code');
    expect($this->sms->count())->toBe(1);

    $this->travel(61)->seconds();

    $this->post('/verify-phone/resend')->assertSessionHasNoErrors();
    expect($this->sms->count())->toBe(2);
});

it('validates the registration fields', function (array $overrides, string $field) {
    $this->post('/register', registerPayload($overrides))->assertSessionHasErrors($field);
    expect(User::count())->toBe(0);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'invalid phone' => [['phone' => '12345'], 'phone'],
    'short password' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
    'password not confirmed' => [['password_confirmation' => 'different123'], 'password'],
]);

it('does not allow registering a phone that already belongs to a verified user', function () {
    User::factory()->create(['phone' => '+201012345678']);

    $this->post('/register', registerPayload(['phone' => '٠١٠١٢٣٤٥٦٧٨']))
        ->assertSessionHasErrors(['phone' => __('app.auth.phone_taken')]);
});

it('lets an abandoned unverified registration be redone with the same phone', function () {
    $this->post('/register', registerPayload(['name' => 'الاسم القديم']));
    $this->travel(61)->seconds();

    $this->post('/register', registerPayload(['name' => 'الاسم الجديد']))
        ->assertRedirect(route('phone.verification.notice'));

    expect(User::count())->toBe(1)
        ->and(User::sole()->name)->toBe('الاسم الجديد');
});
