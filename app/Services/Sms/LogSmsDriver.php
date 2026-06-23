<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

class LogSmsDriver
{
    public function send(string $phone, string $message): void
    {
        Log::info('SMS (log driver)', [
            'phone' => $phone,
            'message' => $message,
        ]);
    }
}
