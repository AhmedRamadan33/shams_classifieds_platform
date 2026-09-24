<?php

declare(strict_types=1);

use App\Filament\Pages\Auth\Login;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->admin = User::factory()->admin()->create(['phone' => '+201000000000', 'email' => 'admin@shams.test', 'password' => '123456789']);
});

it('signs into the panel with an e-mail address', function (string $typed) {
    Livewire::test(Login::class)
        ->fillForm(['phone' => $typed, 'password' => '123456789'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($this->admin);
})->with(['admin@shams.test', 'Admin@Shams.Test']);

it('still signs into the panel with a phone number', function () {
    Livewire::test(Login::class)
        ->fillForm(['phone' => '01000000000', 'password' => '123456789'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($this->admin);
});

it('refuses a wrong password or an unknown e-mail', function (string $login, string $password) {
    Livewire::test(Login::class)
        ->fillForm(['phone' => $login, 'password' => $password])
        ->call('authenticate')
        ->assertHasErrors(['data.phone']);

    $this->assertGuest();
})->with([
    'wrong password' => ['admin@shams.test', 'nope-nope'],
    'unknown e-mail' => ['nobody@shams.test', '123456789'],
]);

it('does not let a regular user into the panel even with a valid e-mail login', function () {
    User::factory()->create(['email' => 'user1@shams.test', 'password' => '123456789']);

    Livewire::test(Login::class)
        ->fillForm(['phone' => 'user1@shams.test', 'password' => '123456789'])
        ->call('authenticate')
        ->assertHasErrors(['data.phone']);

    $this->assertGuest();
});
