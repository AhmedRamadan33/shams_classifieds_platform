<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    // SMS gateway used for OTP codes: "log" (writes to storage/logs, local only), "twilio" or "vonage".
    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
    ],

    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_TOKEN'),
        'from' => env('TWILIO_FROM'),
        'messaging_service_sid' => env('TWILIO_MESSAGING_SERVICE_SID'),
    ],

    'vonage' => [
        'key' => env('VONAGE_KEY'),
        'secret' => env('VONAGE_SECRET'),
        'from' => env('VONAGE_FROM', 'Shams'),
    ],

    // Bot check for the registration and listing forms. "null" lets every request through;
    // register a real provider in AppServiceProvider (see CaptchaVerifier) to switch it on.
    'captcha' => [
        'driver' => env('CAPTCHA_DRIVER', 'null'),
    ],

    // Cloudflare Turnstile (CAPTCHA_DRIVER=turnstile): free keys from the Cloudflare dashboard.
    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret' => env('TURNSTILE_SECRET_KEY'),
        // When Cloudflare cannot be reached: true = let the request through (honeypot and rate limits still apply).
        'fail_open' => (bool) env('TURNSTILE_FAIL_OPEN', true),
    ],

    // Featured-listing payments: "fake" settles instantly (local/testing only; launch:check fails on
    // it in production), "paymob" charges a real card through Paymob (accept.paymob.com).
    'payments' => [
        'driver' => env('PAYMENT_DRIVER', 'fake'),
    ],

    // WhatsApp copies of notifications (opt-in per user, see User::notify_whatsapp). "log" writes them
    // to storage/logs (local only); "cloud" sends through Meta's WhatsApp Cloud API.
    'whatsapp' => [
        'driver' => env('WHATSAPP_DRIVER', 'log'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
    ],

    'paymob' => [
        'api_key' => env('PAYMOB_API_KEY'),
        'integration_id' => env('PAYMOB_INTEGRATION_ID'),
        'iframe_id' => env('PAYMOB_IFRAME_ID'),
        'hmac_secret' => env('PAYMOB_HMAC_SECRET'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
