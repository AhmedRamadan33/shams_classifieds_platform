<?php

declare(strict_types=1);

use App\Enums\OtpPurpose;
use App\Models\OtpCode;
use App\Models\User;
use App\Services\OtpDeliveryException;
use App\Services\OtpService;
use App\Services\Sms\LogSmsGateway;
use App\Services\Sms\SmsDeliveryException;
use App\Services\Sms\SmsGateway;
use App\Services\Sms\TwilioSmsGateway;
use App\Services\Sms\VonageSmsGateway;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('selects the SMS driver from configuration', function (string $driver, string $class) {
    config(['services.sms.driver' => $driver, 'services.twilio.sid' => 'AC1', 'services.twilio.token' => 't', 'services.vonage.key' => 'k']);
    app()->forgetInstance(SmsGateway::class);

    expect(app(SmsGateway::class))->toBeInstanceOf($class);
})->with([
    'log' => ['log', LogSmsGateway::class],
    'twilio' => ['twilio', TwilioSmsGateway::class],
    'vonage' => ['vonage', VonageSmsGateway::class],
    'unknown falls back to log' => ['carrier-pigeon', LogSmsGateway::class],
]);

// ------------------------------------------------------------------- Twilio

it('sends through the Twilio REST API with basic auth', function () {
    Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM1'], 201)]);

    (new TwilioSmsGateway('AC123', 'secret', '+15005550006'))->send('+201012345678', 'رمزك 123456');

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://api.twilio.com/2010-04-01/Accounts/AC123/Messages.json'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('AC123:secret'))
            && $request['To'] === '+201012345678'
            && $request['From'] === '+15005550006'
            && $request['Body'] === 'رمزك 123456';
    });
});

it('prefers a Twilio messaging service over a from number', function () {
    Http::fake(['api.twilio.com/*' => Http::response([], 201)]);

    (new TwilioSmsGateway('AC1', 't', '+1500', 'MG999'))->send('+201012345678', 'x');

    Http::assertSent(fn (Request $request) => $request['MessagingServiceSid'] === 'MG999' && ! isset($request['From']));
});

it('raises SmsDeliveryException when Twilio rejects the message', function () {
    Http::fake(['api.twilio.com/*' => Http::response(['message' => 'Invalid To number'], 400)]);

    (new TwilioSmsGateway('AC1', 't', '+1500'))->send('+201012345678', 'x');
})->throws(SmsDeliveryException::class, 'Invalid To number');

// ------------------------------------------------------------------- Vonage

it('sends Arabic text as unicode through the Vonage SMS API', function () {
    Http::fake(['rest.nexmo.com/*' => Http::response(['messages' => [['status' => '0']]])]);

    (new VonageSmsGateway('key', 'secret', 'Shams'))->send('+201012345678', 'رمز التحقق 123456');

    Http::assertSent(fn (Request $request) => $request['api_key'] === 'key'
        && $request['to'] === '201012345678'
        && $request['type'] === 'unicode'
        && $request['from'] === 'Shams'
        && str_contains($request['text'], '123456'));
});

it('treats a Vonage 200 response with an error status as a failure', function () {
    Http::fake(['rest.nexmo.com/*' => Http::response(['messages' => [['status' => '4', 'error-text' => 'Invalid credentials']]])]);

    (new VonageSmsGateway('bad', 'bad', 'Shams'))->send('+201012345678', 'x');
})->throws(SmsDeliveryException::class, 'Invalid credentials');

it('raises SmsDeliveryException when the provider is unreachable', function () {
    Http::fake(fn () => throw new ConnectionException('timeout'));

    (new VonageSmsGateway('k', 's', 'Shams'))->send('+201012345678', 'x');
})->throws(SmsDeliveryException::class);

// --------------------------------------------------- what the app does on failure

it('removes the unusable code and shows a friendly error when the SMS cannot be sent', function () {
    app()->instance(SmsGateway::class, new class implements SmsGateway
    {
        public function send(string $phone, string $message): void
        {
            throw SmsDeliveryException::provider('test', 'down');
        }
    });

    $this->post('/register', [
        'name' => 'أحمد', 'phone' => '01012345678',
        'password' => 'password123', 'password_confirmation' => 'password123',
    ])->assertSessionHas('error', __('app.otp.delivery_failed'));

    // no orphan code that would block a retry through the 60 second cooldown
    expect(OtpCode::count())->toBe(0);
});

it('lets the user retry immediately after a delivery failure', function () {
    $sms = new class implements SmsGateway
    {
        public bool $fail = true;

        public array $sent = [];

        public function send(string $phone, string $message): void
        {
            if ($this->fail) {
                throw SmsDeliveryException::provider('test', 'down');
            }
            $this->sent[] = $message;
        }
    };
    app()->instance(SmsGateway::class, $sms);

    expect(fn () => app(OtpService::class)->issue('+201012345678', OtpPurpose::Register))->toThrow(OtpDeliveryException::class);

    $sms->fail = false;
    app(OtpService::class)->issue('+201012345678', OtpPurpose::Register); // no cooldown error

    expect($sms->sent)->toHaveCount(1);
});

it('does not break the login flow of unverified users when SMS is down', function () {
    User::factory()->unverified()->create(['phone' => '+201012345678']);
    app()->instance(SmsGateway::class, new class implements SmsGateway
    {
        public function send(string $phone, string $message): void
        {
            throw SmsDeliveryException::provider('test', 'down');
        }
    });

    $this->post('/login', ['phone' => '01012345678', 'password' => 'password'])
        ->assertSessionHas('error', __('app.otp.delivery_failed'));
});
