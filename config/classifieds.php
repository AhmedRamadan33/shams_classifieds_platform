<?php

declare(strict_types=1);

return [

    'listing_duration_days' => (int) env('CLASSIFIEDS_LISTING_DURATION_DAYS', 30),

    'require_review' => (bool) env('CLASSIFIEDS_REQUIRE_REVIEW', true),

    'daily_listing_limit' => (int) env('CLASSIFIEDS_DAILY_LISTING_LIMIT', 10),

    'saved_search_limit' => (int) env('CLASSIFIEDS_SAVED_SEARCH_LIMIT', 20),

    'expiry_reminder_days' => 3,

    'purge_expired_after_days' => 90,

    'renew_window_days' => 7,

    'max_images' => 8,

    'max_image_kb' => 5120,

    'image_min_dimension' => 200,
    'image_max_dimension' => 8000,

    'phone_country_code' => env('CLASSIFIEDS_PHONE_COUNTRY_CODE', '+20'),

    'phone_national_min' => 8,
    'phone_national_max' => 10,

    'currency_label' => env('CLASSIFIEDS_CURRENCY_LABEL', 'ج.م'),

    'currency_code' => env('CLASSIFIEDS_CURRENCY_CODE', 'EGP'),

    'blocked_words' => array_values(array_unique(array_filter([
        'مخدرات', 'حشيش', 'هيروين', 'كوكايين', 'بانجو', 'ترامادول', 'استروكس', 'ماريجوانا',
        'سلاح ناري', 'مسدس', 'بندقية', 'رشاش', 'ذخيرة حية', 'متفجرات',
        'ربح سريع', 'استثمار مضمون', 'تحويل رصيد', 'تهريب', 'تزوير', 'شهادات مضروبة', 'بيع كلاوي', 'بيع كلى',
        ...array_map('trim', explode(',', (string) env('CLASSIFIEDS_BLOCKED_WORDS', ''))),
    ]))),

    'contact_email' => env('CLASSIFIEDS_CONTACT_EMAIL', 'support@example.com'),

    'otp' => [
        'length' => 6,
        'ttl_minutes' => 5,
        'max_attempts' => 5,
        'resend_cooldown_seconds' => 60,
    ],

    'admin' => [
        'name' => env('ADMIN_NAME', 'مدير الموقع'),
        'phone' => env('ADMIN_PHONE'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    'per_page' => 24,

    'honeypot_field' => 'website_url',

];
