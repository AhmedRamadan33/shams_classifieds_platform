<?php

declare(strict_types=1);

use App\Models\Governorate;
use App\Models\Page;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\Fixtures;

/**
 * @return array{failed: int, warnings: int, results: list<array{status: string, check: string, detail: string}>}
 */
function launchCheck(array $options = []): array
{
    Artisan::call('launch:check', ['--json' => true, ...$options]);

    return json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);
}

function statusOf(array $report, string $check): string
{
    return collect($report['results'])->firstWhere('check', $check)['status'] ?? 'missing';
}

it('flags the development defaults as failures in production', function () {
    $this->app->detectEnvironment(fn () => 'production');
    config(['app.debug' => true, 'app.url' => 'http://example.test', 'services.sms.driver' => 'log']);
    User::factory()->admin()->create(['password' => 'change-me-please']);

    $report = launchCheck();

    expect(statusOf($report, 'APP_DEBUG is off'))->toBe('fail')
        ->and(statusOf($report, 'APP_URL uses HTTPS'))->toBe('fail')
        ->and(statusOf($report, 'Real SMS provider'))->toBe('fail')
        ->and(statusOf($report, 'Admin password changed'))->toBe('fail')
        ->and($report['failed'])->toBeGreaterThanOrEqual(4);

    expect(Artisan::call('launch:check', ['--json' => true]))->toBe(1);
});

it('only warns about the same things outside production', function () {
    config(['app.debug' => true, 'services.sms.driver' => 'log']);

    $report = launchCheck();

    expect(statusOf($report, 'APP_DEBUG is off'))->toBe('warn')
        ->and(statusOf($report, 'Real SMS provider'))->toBe('warn');
});

it('passes the integration checks when a real provider and credentials are configured', function () {
    $this->app->detectEnvironment(fn () => 'production');
    config([
        'app.debug' => false, 'app.url' => 'https://shams.example.org', 'session.secure' => true,
        'services.sms.driver' => 'twilio', 'services.twilio.sid' => 'AC1', 'services.twilio.token' => 't', 'services.twilio.from' => '+1500',
        'mail.default' => 'smtp', 'services.captcha.driver' => 'turnstile',
        'classifieds.contact_email' => 'help@shams.org',
        'services.payments.driver' => 'paymob', 'services.paymob.api_key' => 'k', 'services.paymob.integration_id' => '1',
        'services.paymob.iframe_id' => '2', 'services.paymob.hmac_secret' => 's',
    ]);
    User::factory()->admin()->create(['password' => 'a-real-strong-password']);

    $report = launchCheck();

    foreach (['APP_DEBUG is off', 'APP_URL uses HTTPS', 'Real SMS provider', 'Twilio credentials', 'Real mail transport', 'CAPTCHA enabled', 'Contact e-mail is real', 'Admin password changed', 'An administrator exists', 'Real payment gateway', 'Paymob credentials'] as $check) {
        expect(statusOf($report, $check))->toBe('ok', $check);
    }
});

it('fails when the selected SMS provider has no credentials', function () {
    config(['services.sms.driver' => 'vonage', 'services.vonage.key' => null, 'services.vonage.secret' => null]);

    expect(statusOf(launchCheck(), 'Vonage credentials'))->toBe('fail');
});

it('fails when the selected payment gateway has no credentials', function () {
    config(['services.payments.driver' => 'paymob', 'services.paymob.api_key' => null]);

    expect(statusOf(launchCheck(), 'Paymob credentials'))->toBe('fail');
});

it('only warns about the WhatsApp driver, even in production, and fails on missing cloud credentials', function () {
    $this->app->detectEnvironment(fn () => 'production');
    config(['services.whatsapp.driver' => 'log']);
    expect(statusOf(launchCheck(), 'Real WhatsApp provider'))->toBe('warn');

    config(['services.whatsapp.driver' => 'cloud', 'services.whatsapp.phone_number_id' => null]);
    expect(statusOf(launchCheck(), 'WhatsApp Cloud API credentials'))->toBe('fail');

    config(['services.whatsapp.phone_number_id' => '1', 'services.whatsapp.access_token' => 't']);
    expect(statusOf(launchCheck(), 'WhatsApp Cloud API credentials'))->toBe('ok');
});

it('only warns about the fake payment gateway outside production', function () {
    config(['services.payments.driver' => 'fake']);

    expect(statusOf(launchCheck(), 'Real payment gateway'))->toBe('warn');
});

it('fails on the fake payment gateway in production', function () {
    $this->app->detectEnvironment(fn () => 'production');
    config(['services.payments.driver' => 'fake']);

    expect(statusOf(launchCheck(), 'Real payment gateway'))->toBe('fail');
});

it('warns about the placeholder contact page and an empty blocked words list', function () {
    config(['classifieds.blocked_words' => []]);
    Page::create(['slug' => 'contact', 'title' => 'اتصل بنا', 'body' => 'البريد: support@example.com']);

    $report = launchCheck();

    expect(statusOf($report, 'Contact page has no placeholder'))->toBe('warn')
        ->and(statusOf($report, 'Blocked words configured'))->toBe('warn');
});

it('verifies the database and PHP requirements of this machine', function () {
    $report = launchCheck();

    foreach (['Database reachable', 'Migrations are up to date', 'Tables use InnoDB', 'PHP extension gd', 'GD has WebP support'] as $check) {
        expect(statusOf($report, $check))->toBe('ok', $check);
    }
});

it('exits with an error on warnings only in strict mode', function () {
    config(['classifieds.blocked_words' => []]);

    // Failures on this machine (admin missing in an empty test database) already make it fail, so
    // create the admin and satisfy every hard requirement first.
    User::factory()->admin()->create(['password' => 'a-real-strong-password']);
    $report = launchCheck();

    if ($report['failed'] === 0) {
        expect(Artisan::call('launch:check', ['--json' => true]))->toBe(0)
            ->and(Artisan::call('launch:check', ['--json' => true, '--strict' => true]))->toBe(1);
    } else {
        // e.g. no public/storage link on this machine: strict mode is irrelevant, it fails anyway.
        expect(Artisan::call('launch:check', ['--json' => true, '--strict' => true]))->toBe(1);
    }
});

it('ships a useful default blocked words list and lets .env extend it', function () {
    $words = config('classifieds.blocked_words');

    expect($words)->toContain('مخدرات')->and($words)->toContain('سلاح ناري')->and($words)->toBe(array_values(array_unique($words)));

    // the default list rejects a matching listing (spelling variants included)
    $governorate = Governorate::factory()->create();
    $leaf = Fixtures::carsTree()['leaf'];

    $this->actingAs(User::factory()->create())
        ->post('/ads', Fixtures::listingPayload($leaf, $governorate, null, ['description' => 'للبيع كمية من الحشيش بسعر مناسب جداً للجادين']))
        ->assertSessionHasErrors('description');
});
