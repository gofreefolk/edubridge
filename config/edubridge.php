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
    'attachments' => [
        'disk' => env('EDUBRIDGE_ATTACHMENTS_DISK', 'public'),
        'visibility' => env('EDUBRIDGE_ATTACHMENTS_VISIBILITY', 'private'),
        'temporary_url_minutes' => (int) env('EDUBRIDGE_ATTACHMENTS_URL_TTL', 60),
    ],
];
