<?php

return [
    'sms' => [
        // log (development) or http (posts to webhook_url)
        'driver' => env('EDUBRIDGE_SMS_DRIVER', 'log'),
        'webhook_url' => env('EDUBRIDGE_SMS_WEBHOOK_URL'),
        'webhook_token' => env('EDUBRIDGE_SMS_WEBHOOK_TOKEN'),
    ],
    'whatsapp' => [
        'driver' => env('EDUBRIDGE_WHATSAPP_DRIVER', 'log'),
        'enabled' => env('EDUBRIDGE_WHATSAPP_ENABLED', true),
        'webhook_url' => env('EDUBRIDGE_WHATSAPP_WEBHOOK_URL'),
    ],
    'otp' => [
        'length' => (int) env('OTP_LENGTH', 6),
        'expiry_minutes' => (int) env('OTP_EXPIRY_MINUTES', 10),
        'resend_cooldown_seconds' => (int) env('OTP_RESEND_COOLDOWN_SECONDS', 60),
        'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),
        // Fixed code for local development only; ignored outside local/testing.
        'dev_code' => env('EDUBRIDGE_OTP_DEV_CODE'),
    ],
    'fees' => [
        'reminder_days_before' => (int) env('EDUBRIDGE_FEE_REMINDER_DAYS_BEFORE', 3),
        'overdue_every_days' => (int) env('EDUBRIDGE_FEE_OVERDUE_EVERY_DAYS', 7),
        'overdue_stop_after_days' => (int) env('EDUBRIDGE_FEE_OVERDUE_STOP_AFTER_DAYS', 60),
        // Above are defaults; each school can override them in Fee setup.
        'online' => [
            // Razorpay payment links; keys are per school (Fee setup), paid into the school's account.
            'link_expiry_hours' => (int) env('EDUBRIDGE_FEE_LINK_EXPIRY_HOURS', 48),
        ],
    ],
    'attachments' => [
        'disk' => env('EDUBRIDGE_ATTACHMENTS_DISK', 'public'),
        'visibility' => env('EDUBRIDGE_ATTACHMENTS_VISIBILITY', 'private'),
        'temporary_url_minutes' => (int) env('EDUBRIDGE_ATTACHMENTS_URL_TTL', 60),
    ],
];
