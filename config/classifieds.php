<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Listing lifecycle
    |--------------------------------------------------------------------------
    */

    // Days a listing stays live after approval/renewal.
    'listing_duration_days' => (int) env('CLASSIFIEDS_LISTING_DURATION_DAYS', 30),

    // When true, new and edited listings wait for a moderator (status = pending).
    'require_review' => (bool) env('CLASSIFIEDS_REQUIRE_REVIEW', true),

    // Maximum listings a single user may create in a rolling 24 hours.
    'daily_listing_limit' => (int) env('CLASSIFIEDS_DAILY_LISTING_LIMIT', 10),

    // Maximum saved searches a single user may keep.
    'saved_search_limit' => (int) env('CLASSIFIEDS_SAVED_SEARCH_LIMIT', 20),

    // Send the "expiring soon" reminder this many days before expires_at.
    'expiry_reminder_days' => 3,

    // Expired listings older than this many days are permanently deleted.
    'purge_expired_after_days' => 90,

    // A live listing may be renewed within this many days before it expires.
    'renew_window_days' => 7,

    /*
    |--------------------------------------------------------------------------
    | Images
    |--------------------------------------------------------------------------
    */

    'max_images' => 8,

    // Per-image upload limit, in kilobytes.
    'max_image_kb' => 5120,

    // Accepted image dimensions, in pixels.
    'image_min_dimension' => 200,
    'image_max_dimension' => 8000,

    /*
    |--------------------------------------------------------------------------
    | Locale specific defaults (Egypt). Override through .env, no code changes.
    |--------------------------------------------------------------------------
    */

    'phone_country_code' => env('CLASSIFIEDS_PHONE_COUNTRY_CODE', '+20'),

    // Allowed length of the national part (digits after the country code) for the default country.
    'phone_national_min' => 8,
    'phone_national_max' => 10,

    'currency_label' => env('CLASSIFIEDS_CURRENCY_LABEL', 'ج.م'),

    // ISO 4217 code used in structured data (schema.org Offer.priceCurrency).
    'currency_code' => env('CLASSIFIEDS_CURRENCY_CODE', 'EGP'),

    /*
    |--------------------------------------------------------------------------
    | Moderation
    |--------------------------------------------------------------------------
    */

    // Listings containing any of these words/phrases (compared after Arabic normalization, so
    // spelling variants match) are rejected. Extend it with CLASSIFIEDS_BLOCKED_WORDS="a,b,c"
    // (comma separated) without editing this file.
    'blocked_words' => array_values(array_unique(array_filter([
        // drugs
        'مخدرات', 'حشيش', 'هيروين', 'كوكايين', 'بانجو', 'ترامادول', 'استروكس', 'ماريجوانا',
        // weapons and explosives
        'سلاح ناري', 'مسدس', 'بندقية', 'رشاش', 'ذخيرة حية', 'متفجرات',
        // scams and forbidden services
        'ربح سريع', 'استثمار مضمون', 'تحويل رصيد', 'تهريب', 'تزوير', 'شهادات مضروبة', 'بيع كلاوي', 'بيع كلى',
        ...array_map('trim', explode(',', (string) env('CLASSIFIEDS_BLOCKED_WORDS', ''))),
    ]))),

    // Public contact e-mail shown on the «اتصل بنا» page (see PageSeeder) and used by launch:check.
    'contact_email' => env('CLASSIFIEDS_CONTACT_EMAIL', 'support@example.com'),

    /*
    |--------------------------------------------------------------------------
    | Phone OTP
    |--------------------------------------------------------------------------
    */

    'otp' => [
        'length' => 6,
        'ttl_minutes' => 5,
        'max_attempts' => 5,
        'resend_cooldown_seconds' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Seeded administrator (see AdminSeeder)
    |--------------------------------------------------------------------------
    */

    'admin' => [
        'name' => env('ADMIN_NAME', 'مدير الموقع'),
        'phone' => env('ADMIN_PHONE'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Public pages
    |--------------------------------------------------------------------------
    */

    'per_page' => 24,

    // Spam protection: name of the hidden honeypot field on the register and listing forms.
    'honeypot_field' => 'website_url',

];
