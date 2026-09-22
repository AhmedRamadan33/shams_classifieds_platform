<?php

declare(strict_types=1);

use App\Models\Governorate;
use App\Models\Listing;
use App\Models\User;
use App\Services\Captcha\CaptchaVerifier;
use App\Services\Captcha\NullCaptchaVerifier;
use App\Services\Captcha\TurnstileCaptchaVerifier;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\Fixtures;

function useTurnstile(bool $failOpen = true): void
{
    config([
        'services.captcha.driver' => 'turnstile',
        'services.turnstile.site_key' => '1x00000000000000000000AA',
        'services.turnstile.secret' => 'test-secret',
        'services.turnstile.fail_open' => $failOpen,
    ]);
    app()->forgetInstance(CaptchaVerifier::class);
}

function registrationPayload(array $extra = []): array
{
    return ['name' => 'أحمد', 'phone' => '01012345678', 'password' => 'password123', 'password_confirmation' => 'password123', ...$extra];
}

it('selects the CAPTCHA verifier from configuration', function () {
    expect(app(CaptchaVerifier::class))->toBeInstanceOf(NullCaptchaVerifier::class);

    useTurnstile();

    expect(app(CaptchaVerifier::class))->toBeInstanceOf(TurnstileCaptchaVerifier::class);
});

it('accepts a registration when Cloudflare confirms the token', function () {
    fakeSms();
    Http::fake([TurnstileCaptchaVerifier::ENDPOINT => Http::response(['success' => true])]);
    useTurnstile();

    $this->post('/register', registrationPayload(['cf-turnstile-response' => 'good-token']))
        ->assertRedirect(route('phone.verification.notice'));

    Http::assertSent(fn (HttpRequest $request) => $request->url() === TurnstileCaptchaVerifier::ENDPOINT
        && $request['secret'] === 'test-secret'
        && $request['response'] === 'good-token'
        && $request['remoteip'] === '127.0.0.1');
    expect(User::count())->toBe(1);
});

it('rejects a registration when Cloudflare rejects the token', function () {
    fakeSms();
    Http::fake([TurnstileCaptchaVerifier::ENDPOINT => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']])]);
    useTurnstile();

    $this->post('/register', registrationPayload(['cf-turnstile-response' => 'bad-token']))
        ->assertSessionHas('error', __('app.security.spam_rejected'));

    expect(User::count())->toBe(0);
});

it('rejects a request without a token without calling Cloudflare', function (mixed $token) {
    fakeSms();
    Http::fake();
    useTurnstile();

    $payload = $token === null ? registrationPayload() : registrationPayload(['cf-turnstile-response' => $token]);

    $this->post('/register', $payload)->assertSessionHas('error', __('app.security.spam_rejected'));

    Http::assertNothingSent();
    expect(User::count())->toBe(0);
})->with([
    'missing' => [null],
    'empty' => [''],
    'array' => [['x']],
    'oversized' => [str_repeat('a', 5000)],
]);

it('lets requests through when Cloudflare is unreachable and fail-open is on', function () {
    fakeSms();
    Http::fake(fn () => throw new ConnectionException('timeout'));
    useTurnstile(failOpen: true);

    $this->post('/register', registrationPayload(['cf-turnstile-response' => 'token']))
        ->assertRedirect(route('phone.verification.notice'));
});

it('rejects requests when Cloudflare is unreachable and fail-open is off', function () {
    fakeSms();
    Http::fake(fn () => throw new ConnectionException('timeout'));
    useTurnstile(failOpen: false);

    $this->post('/register', registrationPayload(['cf-turnstile-response' => 'token']))
        ->assertSessionHas('error', __('app.security.spam_rejected'));
});

it('treats a Cloudflare 5xx like an outage', function (bool $failOpen) {
    Http::fake([TurnstileCaptchaVerifier::ENDPOINT => Http::response('', 503)]);
    useTurnstile($failOpen);

    expect(app(CaptchaVerifier::class)->verify(Request::create('/', 'POST', ['cf-turnstile-response' => 'token'])))->toBe($failOpen);
})->with([true, false]);

it('renders the widget and its script only when Turnstile is configured', function () {
    $this->get('/register')->assertDontSee('cf-turnstile')->assertDontSee('challenges.cloudflare.com');

    useTurnstile();

    $this->get('/register')
        ->assertSee('class="cf-turnstile"', false)
        ->assertSee('data-sitekey="1x00000000000000000000AA"', false)
        ->assertSee('data-language="ar"', false)
        ->assertSee('https://challenges.cloudflare.com/turnstile/v0/api.js', false);
});

it('shows the widget on the new listing form but not on the edit form', function () {
    useTurnstile();
    $user = User::factory()->create();

    $this->actingAs($user)->get('/ads/create')->assertSee('class="cf-turnstile"', false);

    $listing = Listing::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user)->get("/ads/{$listing->id}/edit")->assertDontSee('cf-turnstile');
});

it('protects listing creation with the same check', function () {
    Http::fake([TurnstileCaptchaVerifier::ENDPOINT => Http::response(['success' => false])]);
    useTurnstile();
    $tree = Fixtures::carsTree();
    $governorate = Governorate::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post('/ads', Fixtures::listingPayload($tree['leaf'], $governorate, null, ['cf-turnstile-response' => 'bad']))
        ->assertSessionHas('error', __('app.security.spam_rejected'));

    expect(Listing::count())->toBe(0);
});
