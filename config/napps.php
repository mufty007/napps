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
    | Online payments
    |--------------------------------------------------------------------------
    | driver: "paystack", or "fake" to simulate checkout on this server
    | (local development only; refused in production).
    */

    'payments' => [
        'driver' => env('PAYMENTS_DRIVER', 'paystack'),
        'currency' => 'NGN',
        // Paystack needs an email; phone-only members get <phone digits>@this domain.
        'fallback_email_domain' => env('PAYMENTS_FALLBACK_EMAIL_DOMAIN', 'members.napps.example'),
        'paystack' => [
            'public_key' => env('PAYSTACK_PUBLIC_KEY'),
            'secret_key' => env('PAYSTACK_SECRET_KEY'),
            'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Membership & dues
    |--------------------------------------------------------------------------
    */

    'membership' => [
        // PRD: the chapter verifies, the state approves. Set false to let the
        // chapter's verification approve the school directly.
        'state_approval_required' => (bool) env('MEMBERSHIP_STATE_APPROVAL', true),
        // Suspend a school automatically once dues are this many days overdue
        // (null = never; executives can always suspend by hand).
        'suspend_after_days_overdue' => is_numeric(env('MEMBERSHIP_SUSPEND_AFTER_DAYS')) && (int) env('MEMBERSHIP_SUSPEND_AFTER_DAYS') > 0
            ? (int) env('MEMBERSHIP_SUSPEND_AFTER_DAYS')
            : null,
        // Open applications one person may have at a time (anti-spam).
        'max_open_applications' => 3,
        // Upload limit for application documents, in kilobytes.
        'document_max_kb' => 5120,
        // Private disk for application documents (never the public disk).
        'documents_disk' => env('MEMBERSHIP_DOCUMENTS_DISK', 'local'),
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
