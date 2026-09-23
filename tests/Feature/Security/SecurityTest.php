<?php

declare(strict_types=1);

use App\Enums\OtpPurpose;
use App\Models\Governorate;
use App\Models\Listing;
use App\Models\User;
use App\Services\Captcha\CaptchaVerifier;
use App\Services\Captcha\NullCaptchaVerifier;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\Support\Fixtures;

it('sends the baseline security headers on public, auth and admin pages', function (string $url) {
    $response = $this->get($url);

    expect($response->headers->get('X-Frame-Options'))->toBe('SAMEORIGIN')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin')
        ->and($response->headers->get('Permissions-Policy'))->toContain('camera=()')
        ->and($response->headers->get('Permissions-Policy'))->toContain('geolocation=()');
})->with(['/', '/login', '/category/none', '/admin/login']);

it('sends HSTS only over HTTPS', function () {
    expect($this->get('/')->headers->has('Strict-Transport-Security'))->toBeFalse();

    $secure = $this->withServerVariables(['HTTPS' => 'on'])->get('https://localhost/');
    expect($secure->headers->get('Strict-Transport-Security'))->toContain('max-age=31536000');
});

it('sends security headers on error responses too', function () {
    $this->get('/definitely-not-here')
        ->assertNotFound()
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('defines the named limiters', function (string $name) {
    expect(RateLimiter::limiter($name))->not->toBeNull();
})->with(['otp', 'login', 'listings-create', 'phone-reveal', 'reports', 'messages', 'reviews']);

it('limits OTP requests per phone number regardless of its format', function () {
    fakeSms();
    User::factory()->create(['phone' => '+201012345678']);

    foreach (['01012345678' => 4, '+201012345678' => 3, '٠١٠١٢٣٤٥٦٧٨' => 3] as $format => $times) {
        foreach (range(1, $times) as $ignored) {
            $this->post('/forgot-password', ['phone' => (string) $format])->assertRedirect(route('password.verify'));
        }
    }

    $this->post('/forgot-password', ['phone' => '01012345678'])->assertStatus(429);

    $this->post('/forgot-password', ['phone' => '01099999999'])->assertRedirect(route('password.verify'));
});

it('limits OTP requests per IP address', function () {
    fakeSms();

    foreach (range(1, 30) as $i) {
        $this->post('/forgot-password', ['phone' => '0101000'.str_pad((string) $i, 4, '0', STR_PAD_LEFT)]);
    }

    $this->post('/forgot-password', ['phone' => '01010009999'])->assertStatus(429);
});

it('limits code checks per phone too', function () {
    fakeSms();
    $user = User::factory()->unverified()->create(['phone' => '+201012345678']);
    app(OtpService::class)->issue($user->phone, OtpPurpose::Register);
    $this->withSession(['verify_phone' => $user->phone]);

    foreach (range(1, 10) as $ignored) {
        $this->post('/verify-phone', ['code' => '000000']);
    }

    $this->post('/verify-phone', ['code' => '000000'])->assertStatus(429);
});

it('limits login attempts per IP', function () {
    foreach (range(1, 20) as $i) {
        $this->post('/login', ['phone' => '0101111'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), 'password' => 'x']);
    }

    $this->post('/login', ['phone' => '01011119999', 'password' => 'x'])->assertStatus(429);
});

it('limits listing creation per user', function () {
    $tree = Fixtures::carsTree();
    $governorate = Governorate::factory()->create();
    $user = User::factory()->create();
    config(['classifieds.daily_listing_limit' => 1000]);

    foreach (range(1, 10) as $i) {
        $this->actingAs($user)->post('/ads', Fixtures::listingPayload($tree['leaf'], $governorate, null, ['title' => "إعلان رقم {$i} للاختبار"]))
            ->assertSessionHasNoErrors();
    }

    $this->actingAs($user)->post('/ads', Fixtures::listingPayload($tree['leaf'], $governorate, null, ['title' => 'إعلان زائد عن الحد']))
        ->assertStatus(429);
});

it('limits phone reveals per IP', function () {
    $listing = Listing::factory()->create();

    foreach (range(1, 30) as $ignored) {
        $this->postJson("/ad/{$listing->id}/contact")->assertOk();
    }

    $this->postJson("/ad/{$listing->id}/contact")->assertStatus(429);
});

it('limits chat messages per user', function () {
    $seller = User::factory()->create();
    $buyer = User::factory()->create();
    $listing = Listing::factory()->create(['user_id' => $seller->id, 'status' => 'active']);

    foreach (range(1, 20) as $i) {
        $this->actingAs($buyer)->post("/ad/{$listing->id}/message", ['body' => "رسالة رقم {$i}"])->assertRedirect();
    }

    $this->actingAs($buyer)->post("/ad/{$listing->id}/message", ['body' => 'رسالة زائدة عن الحد'])->assertStatus(429);
});

it('rejects a registration with the honeypot field filled and creates no user', function () {
    fakeSms();

    $this->post('/register', [
        'name' => 'روبوت',
        'phone' => '01012345678',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        config('classifieds.honeypot_field') => 'http://spam.example',
    ])->assertSessionHas('error', __('app.security.spam_rejected'));

    expect(User::count())->toBe(0);
});

it('rejects a listing with the honeypot field filled', function () {
    $tree = Fixtures::carsTree();
    $governorate = Governorate::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post('/ads', Fixtures::listingPayload($tree['leaf'], $governorate, null, [config('classifieds.honeypot_field') => 'x']))
        ->assertSessionHas('error', __('app.security.spam_rejected'));

    expect(Listing::count())->toBe(0);
});

it('accepts real users who leave the honeypot empty', function () {
    fakeSms();

    $this->post('/register', [
        'name' => 'أحمد', 'phone' => '01012345678',
        'password' => 'password123', 'password_confirmation' => 'password123',
        config('classifieds.honeypot_field') => '',
    ])->assertRedirect(route('phone.verification.notice'));

    expect(User::count())->toBe(1);
});

it('renders the honeypot field invisibly in the forms', function () {
    $field = config('classifieds.honeypot_field');

    $this->get('/register')->assertSee('name="'.$field.'"', false)->assertSee('aria-hidden="true"', false)->assertSee('tabindex="-1"', false);
    $this->actingAs(User::factory()->create())->get('/ads/create')->assertSee('name="'.$field.'"', false);
});

it('uses a pass-through CAPTCHA by default and a swapped verifier can block requests', function () {
    expect(app(CaptchaVerifier::class))->toBeInstanceOf(NullCaptchaVerifier::class);

    app()->instance(CaptchaVerifier::class, new class implements CaptchaVerifier
    {
        public function verify(Request $request): bool
        {
            return false;
        }
    });

    $this->post('/register', [
        'name' => 'أحمد', 'phone' => '01012345678',
        'password' => 'password123', 'password_confirmation' => 'password123',
    ])->assertSessionHas('error', __('app.security.spam_rejected'));

    expect(User::count())->toBe(0);
});

it('protects every state-changing route with CSRF and authentication or an explicit public allowlist', function () {
    $publicPost = [
        'register', 'login', 'forgot-password', 'forgot-password/verify', 'forgot-password/resend',
        'reset-password', 'verify-phone', 'verify-phone/resend', 'ad/{listing}/contact',
        'payments/webhook/paymob',
    ];

    $publicApiPost = [
        'api/v1/auth/register', 'api/v1/auth/login', 'api/v1/auth/verify-otp', 'api/v1/auth/resend-otp',
        'api/v1/listings/{listing}/contact',
    ];

    $unprotected = [];

    foreach (Route::getRoutes() as $route) {
        $mutating = array_diff($route->methods(), ['GET', 'HEAD', 'OPTIONS']);
        $uri = $route->uri();

        if ($mutating === [] || str_starts_with($uri, 'livewire') || str_starts_with($uri, 'admin') || str_starts_with($uri, 'filament') || $uri === 'up') {
            continue;
        }

        $middleware = $route->gatherMiddleware();

        if (str_starts_with($uri, 'api/')) {
            if (! in_array($uri, $publicApiPost, true) && ! in_array('auth:sanctum', $middleware, true)) {
                $unprotected[] = "{$uri} (not auth:sanctum and not on the public API allowlist)";
            }

            continue;
        }

        if (! in_array('web', $middleware, true)) {
            $unprotected[] = "{$uri} (no web/CSRF)";
        }

        if (! in_array($uri, $publicPost, true) && ! in_array('auth', $middleware, true)) {
            $unprotected[] = "{$uri} (not authenticated and not on the public allowlist)";
        }
    }

    expect($unprotected)->toBe([]);
});

it('authorizes every listing mutation through the policy', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $listing = Listing::factory()->for($owner)->create(['expires_at' => now()->addDay()]);

    $calls = [
        ['get', "/ads/{$listing->id}/edit"],
        ['put', "/ads/{$listing->id}"],
        ['delete', "/ads/{$listing->id}"],
        ['post', "/ads/{$listing->id}/renew"],
        ['post', "/ads/{$listing->id}/sold"],
    ];

    foreach ($calls as [$method, $url]) {
        $this->actingAs($stranger)->{$method}($url)->assertForbidden();
    }
});

it('never renders user data with unescaped Blade output', function () {
    $offenders = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views'))) as $file) {
        if (str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'mail'.DIRECTORY_SEPARATOR)) {
            continue;
        }

        if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php') && str_contains(file_get_contents($file->getPathname()), '{!!')) {
            $offenders[] = $file->getPathname();
        }
    }

    expect($offenders)->toBe([]);
});

it('escapes hostile listing content everywhere it is displayed', function () {
    $owner = User::factory()->create();
    $hostile = '<img src=x onerror=alert(1)>"\'';
    $listing = Listing::factory()->for($owner)->titled($hostile, 'وصف عادي '.$hostile)->create();

    foreach ([
        $this->get('/'),
        $this->get('/search'),
        $this->get($listing->url()),
        $this->actingAs($owner)->get('/dashboard'),
    ] as $response) {
        expect($response->getContent())->not->toContain('<img src=x onerror=alert(1)>');
    }
});
