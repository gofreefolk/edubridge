<?php

namespace App\Services\Sms;

class SmsService
{
    public function __construct(
        private readonly SmsDriver $driver,
    ) {}

    public function sendOtp(string $phone, string $code): void
    {
        $message = __('edubridge.otp_sms', [
            'code' => $code,
            'app' => config('app.name'),
            'minutes' => config('edubridge.otp.expiry_minutes'),
        ]);

        $this->driver->send($phone, $message);
    }
}
