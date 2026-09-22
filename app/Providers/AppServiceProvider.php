<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Captcha\CaptchaVerifier;
use App\Services\Captcha\NullCaptchaVerifier;
use App\Services\Captcha\TurnstileCaptchaVerifier;
use App\Services\Payments\FakePaymentGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymobGateway;
use App\Services\PhoneNormalizer;
use App\Services\Sms\LogSmsGateway;
use App\Services\Sms\SmsGateway;
use App\Services\Sms\TwilioSmsGateway;
use App\Services\Sms\VonageSmsGateway;
use App\Services\WhatsApp\LogWhatsAppGateway;
use App\Services\WhatsApp\WhatsAppCloudApiGateway;
use App\Services\WhatsApp\WhatsAppGateway;
use App\Support\FavoriteIds;
use Carbon\Carbon;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator as ValidationValidator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Loaded lazily, once per request, by the favorite buttons on listing cards.
        $this->app->scoped(FavoriteIds::class);

        // "log" writes the OTP to storage/logs (local development only); Twilio and Vonage send real SMS.
        // Add another provider by implementing SmsGateway and returning it for its driver name here.
        $this->app->singleton(SmsGateway::class, function (): SmsGateway {
            return match (config('services.sms.driver')) {
                'twilio' => new TwilioSmsGateway(
                    (string) config('services.twilio.sid'),
                    (string) config('services.twilio.token'),
                    config('services.twilio.from'),
                    config('services.twilio.messaging_service_sid'),
                ),
                'vonage' => new VonageSmsGateway(
                    (string) config('services.vonage.key'),
                    (string) config('services.vonage.secret'),
                    (string) config('services.vonage.from'),
                ),
                default => new LogSmsGateway,
            };
        });

        // No CAPTCHA by default: the null verifier lets everything through (honeypot and rate limits still
        // apply). CAPTCHA_DRIVER=turnstile enables Cloudflare Turnstile; to add reCAPTCHA or hCaptcha,
        // implement CaptchaVerifier and return it for its driver name here.
        $this->app->singleton(CaptchaVerifier::class, function (): CaptchaVerifier {
            return match (config('services.captcha.driver')) {
                'turnstile' => new TurnstileCaptchaVerifier(
                    (string) config('services.turnstile.secret'),
                    (bool) config('services.turnstile.fail_open'),
                ),
                default => new NullCaptchaVerifier,
            };
        });

        // "fake" settles every payment instantly (local/testing only). PAYMENT_DRIVER=paymob charges
        // a real card through Paymob. Add another gateway by implementing PaymentGateway and
        // returning it for its driver name here.
        $this->app->singleton(PaymentGateway::class, function (): PaymentGateway {
            return match (config('services.payments.driver')) {
                'paymob' => new PaymobGateway(
                    (string) config('services.paymob.api_key'),
                    (string) config('services.paymob.integration_id'),
                    (string) config('services.paymob.iframe_id'),
                ),
                default => $this->app->make(FakePaymentGateway::class),
            };
        });

        // "log" writes WhatsApp notifications to storage/logs (local development only).
        // WHATSAPP_DRIVER=cloud sends them through Meta's WhatsApp Cloud API.
        $this->app->singleton(WhatsAppGateway::class, function (): WhatsAppGateway {
            return match (config('services.whatsapp.driver')) {
                'cloud' => new WhatsAppCloudApiGateway(
                    (string) config('services.whatsapp.phone_number_id'),
                    (string) config('services.whatsapp.access_token'),
                ),
                default => new LogWhatsAppGateway,
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Relative dates ("منذ ساعتين") and month names in Arabic.
        Carbon::setLocale(config('app.locale'));

        // Catch N+1 problems early in development and tests; never break production pages.
        Model::preventLazyLoading(! $this->app->isProduction());

        Password::defaults(fn () => Password::min(8));

        // Our field names take precedence over lang/ar/validation.php, which lang:update rewrites.
        Validator::resolver(fn ($translator, array $data, array $rules, array $messages, array $attributes) => new ValidationValidator(
            $translator,
            $data,
            $rules,
            $messages,
            [...(array) __('app.validation_attributes'), ...$attributes],
        ));

        $this->configureRateLimiting();
    }

    /**
     * Named limiters, applied to routes as `throttle:<name>`.
     */
    private function configureRateLimiting(): void
    {
        // One-time codes: sending and checking. Limited per phone number AND per IP so neither a
        // single number nor a single address can be used to flood SMS or brute-force codes.
        RateLimiter::for('otp', function (Request $request) {
            $phone = (string) ($request->input('phone')
                ?? $request->session()->get('verify_phone')
                ?? $request->session()->get('reset_phone')
                ?? '');

            // Normalized so "010..." and "+2010..." share one bucket.
            $phone = app(PhoneNormalizer::class)->tryNormalize($phone) ?? $phone;

            return [
                Limit::perMinute(10)->by('otp-phone:'.$phone),
                Limit::perMinute(30)->by('otp-ip:'.$request->ip()),
            ];
        });

        // Login attempts; LoginRequest additionally throttles per phone + IP.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(20)->by('login:'.$request->ip()));

        // Posting ads: per user (falls back to IP); the daily listing limit is enforced separately.
        RateLimiter::for('listings-create', fn (Request $request) => Limit::perMinute(10)->by('listings:'.($request->user()?->id ?? $request->ip())));

        // Revealing phone numbers stops scrapers from harvesting them in bulk.
        RateLimiter::for('phone-reveal', fn (Request $request) => [
            Limit::perMinute(30)->by('reveal:'.$request->ip()),
            Limit::perHour(300)->by('reveal-hour:'.$request->ip()),
        ]);

        // Listing reports.
        RateLimiter::for('reports', fn (Request $request) => Limit::perMinute(10)->by('reports:'.($request->user()?->id ?? $request->ip())));

        // Chat messages: generous enough for a real conversation, tight enough to stop flooding.
        RateLimiter::for('messages', fn (Request $request) => Limit::perMinute(20)->by('messages:'.($request->user()?->id ?? $request->ip())));

        // Leaving/editing a seller review.
        RateLimiter::for('reviews', fn (Request $request) => Limit::perMinute(10)->by('reviews:'.($request->user()?->id ?? $request->ip())));
    }
}
