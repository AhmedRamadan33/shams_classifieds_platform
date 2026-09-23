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
    public function register(): void
    {
        $this->app->scoped(FavoriteIds::class);

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

        $this->app->singleton(CaptchaVerifier::class, function (): CaptchaVerifier {
            return match (config('services.captcha.driver')) {
                'turnstile' => new TurnstileCaptchaVerifier(
                    (string) config('services.turnstile.secret'),
                    (bool) config('services.turnstile.fail_open'),
                ),
                default => new NullCaptchaVerifier,
            };
        });

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

    public function boot(): void
    {
        Carbon::setLocale(config('app.locale'));

        Model::preventLazyLoading(! $this->app->isProduction());

        Password::defaults(fn () => Password::min(8));

        Validator::resolver(fn ($translator, array $data, array $rules, array $messages, array $attributes) => new ValidationValidator(
            $translator,
            $data,
            $rules,
            $messages,
            [...(array) __('app.validation_attributes'), ...$attributes],
        ));

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('otp', function (Request $request) {
            $phone = (string) ($request->input('phone')
                ?? $request->session()->get('verify_phone')
                ?? $request->session()->get('reset_phone')
                ?? '');

            $phone = app(PhoneNormalizer::class)->tryNormalize($phone) ?? $phone;

            return [
                Limit::perMinute(10)->by('otp-phone:'.$phone),
                Limit::perMinute(30)->by('otp-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(20)->by('login:'.$request->ip()));

        RateLimiter::for('listings-create', fn (Request $request) => Limit::perMinute(10)->by('listings:'.($request->user()?->id ?? $request->ip())));

        RateLimiter::for('phone-reveal', fn (Request $request) => [
            Limit::perMinute(30)->by('reveal:'.$request->ip()),
            Limit::perHour(300)->by('reveal-hour:'.$request->ip()),
        ]);

        RateLimiter::for('reports', fn (Request $request) => Limit::perMinute(10)->by('reports:'.($request->user()?->id ?? $request->ip())));

        RateLimiter::for('messages', fn (Request $request) => Limit::perMinute(20)->by('messages:'.($request->user()?->id ?? $request->ip())));

        RateLimiter::for('reviews', fn (Request $request) => Limit::perMinute(10)->by('reviews:'.($request->user()?->id ?? $request->ip())));
    }
}
