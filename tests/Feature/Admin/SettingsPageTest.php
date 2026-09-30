<?php

declare(strict_types=1);

use App\Filament\Pages\Settings;
use App\Models\Setting;
use App\Models\User;
use App\Services\Sms\SmsGateway;
use App\Services\Sms\TwilioSmsGateway;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->admin = User::factory()->admin()->create();
    config(['mail.default' => 'log']);
});

afterEach(function () {
    Cache::forget('app-settings');
});

it('keeps the settings page admin-only', function () {
    $this->get('/admin/settings')->assertRedirect('/admin/login');
    $this->actingAs(User::factory()->moderator()->create())->get('/admin/settings')->assertForbidden();
    $this->actingAs(User::factory()->create())->get('/admin/settings')->assertForbidden();
    $this->actingAs($this->admin)->get('/admin/settings')->assertOk();
});

it('fills the form with the currently active config, and leaves secret fields blank', function () {
    config(['services.sms.driver' => 'log', 'mail.from.address' => 'no-reply@example.com']);

    Livewire::actingAs($this->admin)->test(Settings::class)
        ->assertFormSet(['SMS_DRIVER' => 'log', 'MAIL_FROM_ADDRESS' => 'no-reply@example.com', 'TWILIO_TOKEN' => null]);
});

it('saves settings and immediately overrides config for the rest of the app', function () {
    Livewire::actingAs($this->admin)->test(Settings::class)
        ->fillForm([
            'SMS_DRIVER' => 'twilio',
            'TWILIO_SID' => 'ACtestsid',
            'TWILIO_TOKEN' => 'secret-token-value',
            'TWILIO_FROM' => '+12025550123',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::where('key', 'SMS_DRIVER')->value('value'))->toBe('twilio')
        ->and(Setting::where('key', 'TWILIO_TOKEN')->value('value'))->toBe('secret-token-value')
        ->and(config('services.sms.driver'))->toBe('twilio')
        ->and(config('services.twilio.sid'))->toBe('ACtestsid')
        ->and(app(SmsGateway::class))->toBeInstanceOf(TwilioSmsGateway::class);
});

it('never re-displays a saved secret, and keeps it when the field is left blank on the next save', function () {
    Livewire::actingAs($this->admin)->test(Settings::class)
        ->fillForm(['SMS_DRIVER' => 'twilio', 'TWILIO_SID' => 'ACtestsid', 'TWILIO_TOKEN' => 'original-secret'])
        ->call('save');

    Livewire::actingAs($this->admin)->test(Settings::class)
        ->assertFormSet(['TWILIO_TOKEN' => null])
        ->fillForm(['TWILIO_SID' => 'ACtestsid-updated'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::where('key', 'TWILIO_TOKEN')->value('value'))->toBe('original-secret')
        ->and(Setting::where('key', 'TWILIO_SID')->value('value'))->toBe('ACtestsid-updated')
        ->and(config('services.twilio.token'))->toBe('original-secret');
});

it('clears a non-secret field back to the config default when saved blank', function () {
    Livewire::actingAs($this->admin)->test(Settings::class)
        ->fillForm(['MAIL_FROM_NAME' => 'اسم مؤقت'])
        ->call('save');

    expect(Setting::where('key', 'MAIL_FROM_NAME')->exists())->toBeTrue();

    Livewire::actingAs($this->admin)->test(Settings::class)
        ->fillForm(['MAIL_FROM_NAME' => ''])
        ->call('save');

    expect(Setting::where('key', 'MAIL_FROM_NAME')->exists())->toBeFalse();
});

it('overrides the mail configuration the same way', function () {
    Livewire::actingAs($this->admin)->test(Settings::class)
        ->fillForm([
            'MAIL_MAILER' => 'smtp',
            'MAIL_HOST' => 'smtp.hostinger.com',
            'MAIL_PORT' => '465',
            'MAIL_USERNAME' => 'info@shams.codeversetechno.com',
            'MAIL_PASSWORD' => 'a-real-secret',
            'MAIL_FROM_ADDRESS' => 'info@shams.codeversetechno.com',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(config('mail.default'))->toBe('smtp')
        ->and(config('mail.mailers.smtp.host'))->toBe('smtp.hostinger.com')
        ->and((string) config('mail.mailers.smtp.port'))->toBe('465')
        ->and(config('mail.mailers.smtp.password'))->toBe('a-real-secret')
        ->and(config('mail.from.address'))->toBe('info@shams.codeversetechno.com');
});
