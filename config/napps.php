<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Association identity
    |--------------------------------------------------------------------------
    */

    'name' => env('NAPPS_NAME', 'NAPPS'),
    'full_name' => env('NAPPS_FULL_NAME', 'National Association of Proprietors of Private Schools'),
    'support_whatsapp' => env('NAPPS_SUPPORT_WHATSAPP'),
    'dpo_email' => env('NAPPS_DPO_EMAIL'),

    /*
    |--------------------------------------------------------------------------
    | One-time passcodes (phone sign-in)
    |--------------------------------------------------------------------------
    */

    'otp' => [
        'length' => 6,
        'ttl_minutes' => (int) env('OTP_TTL_MINUTES', 10),
        'max_attempts' => 5,
        // Requests per phone number per 10 minutes.
        'send_limit' => (int) env('OTP_SEND_LIMIT', 3),
        // Show the code on screen when SMS is only logged (local development only).
        'reveal_in_local' => (bool) env('OTP_REVEAL_IN_LOCAL', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Two-factor authentication
    |--------------------------------------------------------------------------
    | Required for executives, bursars and secretariat. Can only be relaxed
    | outside production (handy for clicking through demo accounts locally).
    */

    'two_factor' => [
        'enforce' => (bool) env('TWO_FACTOR_ENFORCE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS
    |--------------------------------------------------------------------------
    | driver: "log" (development) or "termii".
    */

    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'brand' => env('SMS_BRAND', 'NAPPS'),
        'log_channel' => env('SMS_LOG_CHANNEL', env('LOG_CHANNEL', 'stack')),
        'termii' => [
            'api_key' => env('TERMII_API_KEY'),
            'sender_id' => env('TERMII_SENDER_ID', 'NAPPS'),
            'base_url' => env('TERMII_BASE_URL', 'https://api.ng.termii.com'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Secretariat bootstrap account (created by the database seeder)
    |--------------------------------------------------------------------------
    */

    'secretariat' => [
        'name' => env('SECRETARIAT_NAME', 'NAPPS Secretariat'),
        'phone' => env('SECRETARIAT_PHONE', '+2348000000000'),
        'email' => env('SECRETARIAT_EMAIL'),
    ],

];
