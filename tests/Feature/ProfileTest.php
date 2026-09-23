<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('shows the profile page', function () {
    $user = User::factory()->create(['phone' => '+201012345678']);

    $this->actingAs($user)->get('/profile')
        ->assertOk()
        ->assertSee($user->name)
        ->assertSee('+201012345678');
});

it('updates the name but never the phone number', function () {
    $user = User::factory()->create(['phone' => '+201012345678']);

    $this->actingAs($user)
        ->patch('/profile', ['name' => 'اسم جديد', 'phone' => '+201099999999'])
        ->assertRedirect('/profile');

    $user->refresh();

    expect($user->name)->toBe('اسم جديد')
        ->and($user->phone)->toBe('+201012345678');
});

it('validates the name', function () {
    $this->actingAs(User::factory()->create())
        ->patch('/profile', ['name' => ''])
        ->assertSessionHasErrors('name');
});

it('saves an optional e-mail and the notification preferences', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch('/profile', [
        'name' => $user->name, 'email' => 'me@example.com', 'notify_email' => '1', 'notify_whatsapp' => '1',
    ])->assertSessionDoesntHaveErrors();

    $user->refresh();
    expect($user->email)->toBe('me@example.com')
        ->and($user->notify_email)->toBeTrue()
        ->and($user->notify_whatsapp)->toBeTrue();

    $this->actingAs($user)->patch('/profile', ['name' => $user->name, 'email' => 'me@example.com']);

    expect($user->fresh()->notify_email)->toBeFalse()
        ->and($user->fresh()->notify_whatsapp)->toBeFalse();
});

it('requires an e-mail address before turning on e-mail notifications', function () {
    $user = User::factory()->create(['email' => null]);

    $this->actingAs($user)
        ->patch('/profile', ['name' => $user->name, 'notify_email' => '1'])
        ->assertSessionHasErrors('notify_email');

    expect($user->fresh()->notify_email)->toBeFalse();
});

it('rejects an e-mail address already used by another account', function () {
    User::factory()->create(['email' => 'taken@example.com']);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/profile', ['name' => $user->name, 'email' => 'taken@example.com'])
        ->assertSessionHasErrors('email');
});

it('changes the password when the current one is correct', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->put('/password', [
        'current_password' => 'password',
        'password' => 'brand-new-pass',
        'password_confirmation' => 'brand-new-pass',
    ])->assertSessionHasNoErrors();

    expect(Hash::check('brand-new-pass', $user->fresh()->password))->toBeTrue();
});

it('rejects a wrong current password', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->from('/profile')->put('/password', [
        'current_password' => 'wrong',
        'password' => 'brand-new-pass',
        'password_confirmation' => 'brand-new-pass',
    ])->assertSessionHasErrorsIn('updatePassword', 'current_password');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});
