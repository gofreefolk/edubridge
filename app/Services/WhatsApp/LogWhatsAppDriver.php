<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Log;

class LogWhatsAppDriver implements WhatsAppDriver
{
    public function send(string $phone, string $message): bool
    {
        Log::channel('single')->info('WhatsApp message', [
            'phone' => $phone,
            'message' => $message,
        ]);

        return true;
    }
}
