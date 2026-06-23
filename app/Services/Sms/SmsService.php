<?php

namespace App\Services\Sms;

use RuntimeException;

class SmsService
{
    public function __construct(
        private readonly LogSmsDriver $logDriver,
    ) {}

    public function sendOtp(string $phone, string $code): void
    {
        $message = __('edubridge.otp_sms', ['code' => $code, 'app' => config('app.name')]);

        match (config('edubridge.sms.driver')) {
            'log' => $this->logDriver->send($phone, $message),
            default => throw new RuntimeException('Unsupported SMS driver.'),
        };
    }
}
