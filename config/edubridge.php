<?php

return [
    'sms' => [
        'driver' => env('EDUBRIDGE_SMS_DRIVER', 'log'),
    ],
    'whatsapp' => [
        'driver' => env('EDUBRIDGE_WHATSAPP_DRIVER', 'log'),
        'enabled' => env('EDUBRIDGE_WHATSAPP_ENABLED', true),
    ],
    'otp' => [
        'length' => 6,
        'expiry_minutes' => 10,
        'resend_cooldown_seconds' => 60,
        'max_attempts' => 5,
        'dev_code' => env('EDUBRIDGE_OTP_DEV_CODE'),
    ],
];
